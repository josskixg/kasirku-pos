<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * KasirKu 58mm ESC/POS Thermal Byte Stream Encoder, Dynamic QRIS TLV Builder & HPP Margin Engine.
 *
 * Ponytail: generates raw ESC/POS bytes for 58mm (32-char) thermal printers; add 80mm (48-char) profile when needed.
 */
final class EscPosEncoderService
{
    public const LINE_WIDTH_58MM = 32;

    /**
     * Format integer IDR value into Indonesian Rupiah display string (e.g., "Rp 25.000").
     */
    public function formatIdr(int $amount): string
    {
        $sign = $amount < 0 ? '-' : '';
        return $sign . 'Rp ' . number_format(abs($amount), 0, ',', '.');
    }

    /**
     * Calculate Subtotal, Discount, PPN Tax, Grand Total, HPP (COGS), and Net Profit.
     *
     * @param array<int, array{sku?: string, name: string, price: int, hpp: int, qty: int}> $items
     * @return array{
     *   subtotal: int,
     *   discount: int,
     *   tax_rate_percent: float,
     *   tax_amount: int,
     *   total: int,
     *   hpp_total: int,
     *   gross_profit: int,
     *   net_profit: int,
     *   margin_percent: float,
     *   item_count: int
     * }
     */
    public function calculateOrderSummary(array $items, int $discount = 0, float $taxRatePercent = 0.0): array
    {
        if ($discount < 0) {
            throw new InvalidArgumentException('Discount cannot be negative.');
        }
        if ($taxRatePercent < 0.0 || $taxRatePercent > 100.0) {
            throw new InvalidArgumentException('Tax rate must be between 0 and 100.');
        }

        $subtotal = 0;
        $hppTotal = 0;
        $itemCount = 0;

        foreach ($items as $item) {
            $qty = max(0, (int) ($item['qty'] ?? 0));
            if ($qty === 0) {
                continue;
            }
            $price = max(0, (int) ($item['price'] ?? 0));
            $hpp = max(0, (int) ($item['hpp'] ?? 0));

            $subtotal += $price * $qty;
            $hppTotal += $hpp * $qty;
            $itemCount += $qty;
        }

        $appliedDiscount = min($subtotal, $discount);
        $taxableAmount = max(0, $subtotal - $appliedDiscount);
        $taxAmount = (int) round($taxableAmount * ($taxRatePercent / 100.0));
        $total = $taxableAmount + $taxAmount;
        $netProfit = $taxableAmount - $hppTotal;
        $marginPercent = $taxableAmount > 0
            ? round(($netProfit / $taxableAmount) * 100.0, 2)
            : 0.0;

        return [
            'subtotal' => $subtotal,
            'discount' => $appliedDiscount,
            'tax_rate_percent' => $taxRatePercent,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'hpp_total' => $hppTotal,
            'gross_profit' => $subtotal - $hppTotal,
            'net_profit' => $netProfit,
            'margin_percent' => $marginPercent,
            'item_count' => $itemCount,
        ];
    }

    /**
     * Build dynamic EMVCo QRIS payload with Tag 54 transaction amount and CRC16-CCITT (0xFFFF).
     */
    public function buildDynamicQrisPayload(string $merchantName, string $merchantCity, int $amount, string $orderId): string
    {
        $cleanMerchant = substr(strtoupper(trim($merchantName ?: 'KASIRKU UMKM')), 0, 25);
        $cleanCity = substr(strtoupper(trim($merchantCity ?: 'JAKARTA')), 0, 15);
        $cleanOrder = substr(preg_replace('/[^A-Za-z0-9\-]/', '', $orderId) ?: 'ORD01', 0, 20);

        $tlv = static fn (string $tag, string $val): string =>
            $tag . str_pad((string) strlen($val), 2, '0', STR_PAD_LEFT) . $val;

        $gui = $tlv('00', 'ID.CO.QRIS.WWW') . $tlv('01', '936009140000001234') . $tlv('03', 'UMI');
        $additional = $tlv('01', $cleanOrder);

        $payloadWithoutCrc =
            $tlv('00', '01') .
            $tlv('01', '12') .
            $tlv('26', $gui) .
            $tlv('52', '5812') .
            $tlv('53', '360') .
            $tlv('54', (string) max(0, $amount)) .
            $tlv('58', 'ID') .
            $tlv('59', $cleanMerchant) .
            $tlv('60', $cleanCity) .
            $tlv('62', $additional) .
            '6304';

        return $payloadWithoutCrc . $this->crc16Ccitt($payloadWithoutCrc);
    }

    /**
     * Compute uppercase 4-hex CRC16-CCITT (polynomial 0x1021, initial 0xFFFF).
     */
    public function crc16Ccitt(string $data): string
    {
        $crc = 0xFFFF;
        $len = strlen($data);
        for ($i = 0; $i < $len; $i++) {
            $crc ^= (ord($data[$i]) << 8);
            for ($b = 0; $b < 8; $b++) {
                $crc = ($crc & 0x8000) ? (($crc << 1) ^ 0x1021) & 0xFFFF : ($crc << 1) & 0xFFFF;
            }
        }
        return strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Encode 58mm thermal receipt into raw ESC/POS binary string and hex inspection payload.
     *
     * @param array<int, array{sku?: string, name: string, price: int, hpp: int, qty: int}> $items
     * @return array{
     *   raw_bytes: string,
     *   byte_length: int,
     *   hex_preview: string,
     *   text_preview: string,
     *   qris_payload: string,
     *   summary: array<string, int|float>
     * }
     */
    public function encodeReceipt58mm(
        string $storeName,
        string $orderId,
        array $items,
        int $discount = 0,
        float $taxRatePercent = 0.0,
        string $paymentMethod = 'QRIS',
        int $cashReceived = 0
    ): array {
        if (empty($items)) {
            throw new InvalidArgumentException('Cannot encode receipt for empty item list.');
        }

        $summary = $this->calculateOrderSummary($items, $discount, $taxRatePercent);
        $qrisPayload = $this->buildDynamicQrisPayload($storeName, 'INDONESIA', $summary['total'], $orderId);

        $padLine = static function (string $left, string $right, int $width = self::LINE_WIDTH_58MM): string {
            $maxLeft = max(1, $width - strlen($right) - 1);
            $trimmedLeft = substr($left, 0, $maxLeft);
            $spaces = max(1, $width - strlen($trimmedLeft) - strlen($right));
            return $trimmedLeft . str_repeat(' ', $spaces) . $right . "\n";
        };

        $divider = str_repeat('-', self::LINE_WIDTH_58MM) . "\n";
        $doubleDivider = str_repeat('=', self::LINE_WIDTH_58MM) . "\n";

        $buf = '';
        $textLines = [];

        // ESC @ (Initialize printer) + ESC a 1 (Center align) + ESC ! 0x18 (Double height/width bold)
        $buf .= "\x1B\x40\x1B\x61\x01\x1B\x21\x18";
        $cleanStore = strtoupper(substr(trim($storeName ?: 'KASIRKU POS'), 0, 28));
        $buf .= $cleanStore . "\x1B\x21\x00\n";
        $buf .= "OFFLINE-FIRST UMKM RETAIL ENGINE\n";
        $buf .= $divider;

        $textLines[] = str_pad($cleanStore, self::LINE_WIDTH_58MM, ' ', STR_PAD_BOTH);
        $textLines[] = str_pad('OFFLINE-FIRST UMKM RETAIL ENGINE', self::LINE_WIDTH_58MM, ' ', STR_PAD_BOTH);
        $textLines[] = rtrim($divider, "\n");

        // Left align metadata
        $buf .= "\x1B\x61\x00";
        $orderLine = $padLine('Order: #' . $orderId, strtoupper($paymentMethod));
        $buf .= $orderLine;
        $buf .= $divider;
        $textLines[] = rtrim($orderLine, "\n");
        $textLines[] = rtrim($divider, "\n");

        foreach ($items as $item) {
            $qty = max(0, (int) ($item['qty'] ?? 0));
            if ($qty === 0) {
                continue;
            }
            $price = max(0, (int) ($item['price'] ?? 0));
            $nameLine = substr((string) $item['name'], 0, self::LINE_WIDTH_58MM) . "\n";
            $qtyPriceLine = $padLine(
                sprintf('  %d x %s', $qty, $this->formatIdr($price)),
                $this->formatIdr($qty * $price)
            );
            $buf .= $nameLine . $qtyPriceLine;
            $textLines[] = rtrim($nameLine, "\n");
            $textLines[] = rtrim($qtyPriceLine, "\n");
        }

        $buf .= $divider;
        $textLines[] = rtrim($divider, "\n");

        $subLine = $padLine('Subtotal', $this->formatIdr($summary['subtotal']));
        $buf .= $subLine;
        $textLines[] = rtrim($subLine, "\n");

        if ($summary['discount'] > 0) {
            $discLine = $padLine('Diskon', '-' . $this->formatIdr($summary['discount']));
            $buf .= $discLine;
            $textLines[] = rtrim($discLine, "\n");
        }

        if ($summary['tax_amount'] > 0) {
            $taxLabel = 'PPN (' . rtrim(rtrim(number_format($summary['tax_rate_percent'], 1), '0'), '.') . '%)';
            $taxLine = $padLine($taxLabel, $this->formatIdr($summary['tax_amount']));
            $buf .= $taxLine;
            $textLines[] = rtrim($taxLine, "\n");
        }

        $buf .= $doubleDivider;
        $textLines[] = rtrim($doubleDivider, "\n");

        // Bold Total: ESC E 1 ... ESC E 0
        $totalLine = $padLine('TOTAL BAYAR', $this->formatIdr($summary['total']));
        $buf .= "\x1B\x45\x01" . $totalLine . "\x1B\x45\x00";
        $textLines[] = rtrim($totalLine, "\n");

        if ($cashReceived > 0) {
            $change = max(0, $cashReceived - $summary['total']);
            $cashLine = $padLine('Tunai', $this->formatIdr($cashReceived));
            $changeLine = $padLine('Kembali', $this->formatIdr($change));
            $buf .= $cashLine . $changeLine;
            $textLines[] = rtrim($cashLine, "\n");
            $textLines[] = rtrim($changeLine, "\n");
        }

        $buf .= $divider;
        $buf .= "\x1B\x61\x01"; // Center footer
        $buf .= "Terima Kasih - Lunas (" . strtoupper($paymentMethod) . ")\n\n";
        // GS V A 0x10 (Partial cut with feed)
        $buf .= "\x1D\x56\x41\x10";

        $textLines[] = rtrim($divider, "\n");
        $textLines[] = str_pad('Terima Kasih - Lunas (' . strtoupper($paymentMethod) . ')', self::LINE_WIDTH_58MM, ' ', STR_PAD_BOTH);

        $hexChunks = str_split(strtoupper(bin2hex($buf)), 2);

        return [
            'raw_bytes' => $buf,
            'byte_length' => strlen($buf),
            'hex_preview' => implode(' ', array_slice($hexChunks, 0, 48)) . (count($hexChunks) > 48 ? ' ...' : ''),
            'text_preview' => implode("\n", $textLines),
            'qris_payload' => $qrisPayload,
            'summary' => $summary,
        ];
    }

    /**
     * Aggregate multiple completed orders into a daily Shift Closing Z-Report.
     *
     * @param array<int, array{order_id: string, payment_method?: string, items: array<int, array{name: string, price: int, hpp: int, qty: int}>, discount?: int, tax_rate_percent?: float}> $orders
     * @return array{
     *   shift_code: string,
     *   total_orders: int,
     *   total_items_sold: int,
     *   gross_revenue: int,
     *   total_discount: int,
     *   total_tax: int,
     *   net_sales: int,
     *   total_hpp: int,
     *   net_profit: int,
     *   margin_percent: float,
     *   payment_breakdown: array<string, int>
     * }
     */
    public function generateShiftZReport(string $shiftCode, array $orders): array
    {
        $grossRevenue = 0;
        $totalDiscount = 0;
        $totalTax = 0;
        $netSales = 0;
        $totalHpp = 0;
        $netProfit = 0;
        $totalItemsSold = 0;
        $paymentBreakdown = [
            'QRIS' => 0,
            'CASH' => 0,
            'TRANSFER' => 0,
        ];

        foreach ($orders as $order) {
            $summary = $this->calculateOrderSummary(
                $order['items'] ?? [],
                (int) ($order['discount'] ?? 0),
                (float) ($order['tax_rate_percent'] ?? 0.0)
            );

            $grossRevenue += $summary['subtotal'];
            $totalDiscount += $summary['discount'];
            $totalTax += $summary['tax_amount'];
            $netSales += $summary['total'];
            $totalHpp += $summary['hpp_total'];
            $netProfit += $summary['net_profit'];
            $totalItemsSold += $summary['item_count'];

            $method = strtoupper((string) ($order['payment_method'] ?? 'QRIS'));
            $paymentBreakdown[$method] = ($paymentBreakdown[$method] ?? 0) + $summary['total'];
        }

        $taxableSales = max(0, $grossRevenue - $totalDiscount);
        $marginPercent = $taxableSales > 0
            ? round(($netProfit / $taxableSales) * 100.0, 2)
            : 0.0;

        return [
            'shift_code' => $shiftCode,
            'total_orders' => count($orders),
            'total_items_sold' => $totalItemsSold,
            'gross_revenue' => $grossRevenue,
            'total_discount' => $totalDiscount,
            'total_tax' => $totalTax,
            'net_sales' => $netSales,
            'total_hpp' => $totalHpp,
            'net_profit' => $netProfit,
            'margin_percent' => $marginPercent,
            'payment_breakdown' => $paymentBreakdown,
        ];
    }
}

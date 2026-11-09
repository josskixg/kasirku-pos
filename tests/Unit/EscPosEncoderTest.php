<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/EscPosEncoderService.php';
require_once __DIR__ . '/../../app/Http/Controllers/PosSyncController.php';

use App\Http\Controllers\PosSyncController;
use App\Services\EscPosEncoderService;

function assertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

function assertTrue(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$encoder = new EscPosEncoderService();

// 1. Test IDR currency formatter
assertSame('Rp 25.000', $encoder->formatIdr(25000), 'IDR formatting should use dot thousands separator');

// 2. Test Order Summary + HPP (COGS) + Tax & Net Profit calculation
$sampleItems = [
    ['sku' => 'KOP-001', 'name' => 'Kopi Susu Gula Aren', 'price' => 18000, 'hpp' => 7500, 'qty' => 2],
    ['sku' => 'ROT-002', 'name' => 'Roti Bakar Coklat Keju', 'price' => 15000, 'hpp' => 6000, 'qty' => 1],
];

$summary = $encoder->calculateOrderSummary($sampleItems, 3000, 11.0);
assertSame(51000, $summary['subtotal'], 'Subtotal should be 2*18000 + 1*15000 = 51000');
assertSame(3000, $summary['discount'], 'Discount should be 3000');
assertSame(5280, $summary['tax_amount'], '11% PPN on 48000 should be 5280');
assertSame(53280, $summary['total'], 'Grand total with tax should be 53280');
assertSame(21000, $summary['hpp_total'], 'Total HPP should be 2*7500 + 6000 = 21000');
assertSame(27000, $summary['net_profit'], 'Net profit (excluding tax) should be 48000 - 21000 = 27000');
assertSame(56.25, $summary['margin_percent'], 'Net margin percent on 48000 should be 56.25%');

// 3. Test ESC/POS 58mm Binary Stream & Dynamic QRIS TLV CRC16
$receipt = $encoder->encodeReceipt58mm('KEDAI KUJANG', 'ORD-2026-001', $sampleItems, 3000, 11.0, 'QRIS', 60000);
assertTrue($receipt['byte_length'] > 120, 'ESC/POS 58mm byte stream should exceed 120 bytes');
assertSame("\x1B\x40", substr($receipt['raw_bytes'], 0, 2), 'ESC/POS stream must start with ESC @ (0x1B 0x40)');
assertSame("\x1D\x56\x41\x10", substr($receipt['raw_bytes'], -4), 'ESC/POS stream must end with GS V A 0x10 partial cut');
assertTrue(str_contains($receipt['qris_payload'], '540553280'), 'Dynamic QRIS must embed Tag 54 length 05 value 53280');
assertSame(
    substr($receipt['qris_payload'], -4),
    $encoder->crc16Ccitt(substr($receipt['qris_payload'], 0, -4)),
    'QRIS CRC16-CCITT checksum at tail must match payload'
);

// 4. Test Batch Sync Controller & Inventory Deduction + Shift Z-Report
$controller = new PosSyncController($encoder);
$batchResult = $controller->syncOfflineBatch([
    'store_name' => 'KEDAI KUJANG BANDUNG',
    'shift_code' => 'SHIFT-20261109-PAGI',
    'inventory' => [
        'KOP-001' => 30,
        'ROT-002' => 15,
    ],
    'orders' => [
        [
            'order_id' => 'ORD-2026-001',
            'payment_method' => 'QRIS',
            'discount' => 3000,
            'tax_rate_percent' => 11.0,
            'items' => $sampleItems,
        ],
        [
            'order_id' => 'ORD-2026-002',
            'payment_method' => 'CASH',
            'discount' => 0,
            'tax_rate_percent' => 0.0,
            'cash_received' => 20000,
            'items' => [
                ['sku' => 'KOP-001', 'name' => 'Kopi Susu Gula Aren', 'price' => 18000, 'hpp' => 7500, 'qty' => 1],
            ],
        ],
    ],
]);

assertSame('synced', $batchResult['status'], 'Batch sync status should be synced');
assertSame(2, $batchResult['synced_count'], 'Synced order count should be 2');
assertSame(27, $batchResult['updated_inventory']['KOP-001'], 'KOP-001 stock should deduct 3 units (30 -> 27)');
assertSame(14, $batchResult['updated_inventory']['ROT-002'], 'ROT-002 stock should deduct 1 unit (15 -> 14)');
assertSame(37500, $batchResult['z_report']['net_profit'], 'Shift Z-Report combined net profit should be 27000 + 10500 = 37500');

echo "PASS: All KasirKu POS PHP 8.2 unit tests (ESC/POS 58mm, QRIS TLV CRC16, HPP Margin & Batch Sync) verified.\n";

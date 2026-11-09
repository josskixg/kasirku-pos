<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\EscPosEncoderService;
use InvalidArgumentException;

/**
 * Laravel 11 Offline-to-Cloud POS Batch Sync, Stock Deduction & Shift Z-Report Controller.
 *
 * Ponytail: synchronous batch ingestion for UMKM single-store setup; move to queued jobs above 500 outlets.
 */
final class PosSyncController
{
    public function __construct(
        private readonly EscPosEncoderService $encoder = new EscPosEncoderService()
    ) {
    }

    /**
     * Handle batch offline orders synced from PWA IndexedDB/localStorage queue.
     *
     * @param array{
     *   store_name?: string,
     *   shift_code?: string,
     *   inventory?: array<string, int>,
     *   orders?: array<int, array{
     *     order_id: string,
     *     payment_method?: string,
     *     discount?: int,
     *     tax_rate_percent?: float,
     *     cash_received?: int,
     *     items: array<int, array{sku: string, name: string, price: int, hpp: int, qty: int}>
     *   }>
     * } $payload
     * @return array<string, mixed>
     */
    public function syncOfflineBatch(array $payload): array
    {
        $storeName = trim((string) ($payload['store_name'] ?? 'KASIRKU POS'));
        $shiftCode = trim((string) ($payload['shift_code'] ?? 'SHIFT-' . date('Ymd-A')));
        $orders = $payload['orders'] ?? [];
        $inventory = $payload['inventory'] ?? [];

        if (!is_array($orders) || empty($orders)) {
            throw new InvalidArgumentException('Batch sync payload must contain at least one offline order.');
        }

        $syncedOrders = [];
        foreach ($orders as $order) {
            $orderId = (string) ($order['order_id'] ?? '');
            if ($orderId === '') {
                throw new InvalidArgumentException('Each synced order requires a non-empty order_id.');
            }

            $items = $order['items'] ?? [];
            foreach ($items as $item) {
                $sku = (string) ($item['sku'] ?? '');
                $qty = max(0, (int) ($item['qty'] ?? 0));
                if ($sku !== '' && array_key_exists($sku, $inventory)) {
                    $inventory[$sku] = max(0, (int) $inventory[$sku] - $qty);
                }
            }

            $encoded = $this->encoder->encodeReceipt58mm(
                $storeName,
                $orderId,
                $items,
                (int) ($order['discount'] ?? 0),
                (float) ($order['tax_rate_percent'] ?? 0.0),
                (string) ($order['payment_method'] ?? 'QRIS'),
                (int) ($order['cash_received'] ?? 0)
            );

            $syncedOrders[] = [
                'order_id' => $orderId,
                'byte_length' => $encoded['byte_length'],
                'hex_preview' => $encoded['hex_preview'],
                'qris_payload' => $encoded['qris_payload'],
                'summary' => $encoded['summary'],
            ];
        }

        $zReport = $this->encoder->generateShiftZReport($shiftCode, $orders);

        return [
            'status' => 'synced',
            'store_name' => $storeName,
            'synced_count' => count($syncedOrders),
            'updated_inventory' => $inventory,
            'orders' => $syncedOrders,
            'z_report' => $zReport,
        ];
    }
}

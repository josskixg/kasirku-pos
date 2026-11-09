<p align="center">
  <img src="logo.svg" width="112" height="112" alt="KasirKu POS Logo">
</p>

<h1 align="center">KasirKu POS — Laravel 11 Offline-First PWA & Bluetooth 58mm ESC/POS Retail Engine</h1>

<p align="center">
  <strong>Commercial-Grade UMKM Point of Sale, Real-Time HPP (COGS) Margin Telemetry, Dynamic EMVCo QRIS Generator & Direct Web Bluetooth 58mm Thermal Printer Engine.</strong>
</p>

<p align="center">
  <a href="https://josskixg.github.io/kasirku-pos/"><img src="https://img.shields.io/badge/Live_Demo-Supabase_Console-3ECF8E?style=for-the-badge&logo=supabase&logoColor=052e16" alt="Live Demo"></a>
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?style=for-the-badge&logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Laravel-11.x-FF2D20?style=for-the-badge&logo=laravel&logoColor=white" alt="Laravel 11">
  <img src="https://img.shields.io/badge/Protocol-ESC%2FPOS_58mm_%2B_QRIS-10B981?style=for-the-badge" alt="ESC/POS 58mm">
  <img src="https://img.shields.io/badge/Design_System-Supabase_Dark_%2B_Light-171717?style=for-the-badge" alt="Supabase Design">
</p>

---

## Live Interactive Console (`Operate & Monitor` Surface)

- **Live Demo (GitHub Pages):** [**https://josskixg.github.io/kasirku-pos/**](https://josskixg.github.io/kasirku-pos/)
- **Theme Support:** Built on the **Supabase Dark (`#171717` / `#3ecf8e`)** design system with a persistent **Light Mode toggle** (`#f8fafc` / `#059669`) for outdoor food stalls and sunlit retail counters.

---

## Why KasirKu POS? (Problem vs. Solution)

| UMKM / Developer Pain Point | KasirKu POS Commercial Engine Solution |
| :--- | :--- |
| **Monthly SaaS POS fees (Rp 150k–300k/outlet/month)** drain small FnB & retail margins. | **Zero recurring fees.** Self-hosted Laravel 11 + Offline-First PWA (`localStorage` / IndexedDB queue) that runs on any Rp 1 jutaan Android tablet or phone. |
| **Internet drops freeze cloud checkouts** during peak lunch/dinner rush. | **100% Offline-First.** Cashiers ring up orders, compute HPP profit, and print 58mm Bluetooth receipts offline, then batch-sync to `/api/v1/pos/sync-batch` when online. |
| **Blind revenue without HPP (COGS) tracking** hides true net profit. | **Built-in HPP & Margin Engine.** Every SKU tracks selling price vs. modal HPP, calculating real-time Gross Revenue, COGS, Net Profit, and Shift Z-Reports. |
| **Proprietary printer drivers** require paid wrapper apps. | **Native ESC/POS 58mm Byte Encoder.** Generates raw binary sequences (`0x1B 0x40`, alignment, bold totals, `GS V A 0x10` paper cut) + **Dynamic QRIS TLV CRC16-CCITT** directly in PHP 8.2 and Vanilla JS. |

---

## Architecture & Repository Structure

```text
kasirku-pos/
├── app/
│   ├── Http/Controllers/
│   │   └── PosSyncController.php        # Offline-to-Cloud batch sync, stock deduction & Shift Z-Report API
│   └── Services/
│       └── EscPosEncoderService.php     # PHP 8.2 58mm ESC/POS byte encoder, Dynamic QRIS TLV & HPP engine
├── assets/
│   └── brand/                           # Full SVG/PNG/ICO/WebManifest brand kit (100/100 svg_audit score)
├── database/
│   └── migrations/
│       └── 2026_11_09_000001_create_kasirku_pos_tables.php
├── routes/
│   └── api.php                          # POST /api/v1/pos/sync-batch endpoint definition
├── tests/
│   └── Unit/
│       └── EscPosEncoderTest.php        # Standalone PHP 8.2 assertion suite (ESC/POS + QRIS CRC16 + Z-Report)
├── DESIGN.md                            # Google DESIGN.md token specification (Supabase Dark + Light Theme)
├── composer.json                        # Composer package manifest (josskixg/kasirku-pos)
├── index.html                           # Responsive Operate & Monitor PWA Console (Dark/Light + Hex Inspector)
├── pos-core.js                          # Shared zero-dependency browser/Node ESC/POS & QRIS engine
└── test.js                              # Node.js assertion test suite for client POS core
```

---

## ESC/POS 58mm Binary Protocol & Dynamic QRIS Specification

`EscPosEncoderService` and `pos-core.js` generate deterministic 32-character (58mm roll) thermal byte streams and EMVCo QRIS payloads:

| Command / Tag | Hex / TLV Sequence | Function in KasirKu POS |
| :--- | :--- | :--- |
| `ESC @` | `0x1B 0x40` | Initialize thermal printer buffer and reset formatting state |
| `ESC a n` | `0x1B 0x61 0x01` / `0x00` | Center-align store header & footer (`0x01`), left-align item rows (`0x00`) |
| `ESC ! n` | `0x1B 0x21 0x18` | Double-height + bold emphasis for store banner |
| `ESC E n` | `0x1B 0x45 0x01` / `0x00` | Toggle bold emphasis on `TOTAL BAYAR` line |
| `GS V A n` | `0x1D 0x56 0x41 0x10` | Feed paper and trigger partial thermal cutter |
| `QRIS Tag 54` | `54` + `LL` + `Amount` | Inject exact transaction total into dynamic EMVCo QRIS string |
| `QRIS Tag 63` | `6304` + `CRC16-CCITT` | Polynomial `0x1021` (`0xFFFF` seed) checksum verification |

---

## Quick Start & Verification

### 1. Run PHP 8.2 Backend Unit Tests (`EscPosEncoderService` & `PosSyncController`)

```bash
php tests/Unit/EscPosEncoderTest.php
```
Expected output:
```text
PASS: All KasirKu POS PHP 8.2 unit tests (ESC/POS 58mm, QRIS TLV CRC16, HPP Margin & Batch Sync) verified.
```

### 2. Run Node.js Offline PWA Core Tests

```bash
node test.js
```
Expected output:
```text
PASS: All POS core & ESC/POS 58mm encoder tests verified.
```

### 3. Laravel 11 Batch Sync Usage

```php
use App\Http\Controllers\PosSyncController;
use App\Services\EscPosEncoderService;

$controller = new PosSyncController(new EscPosEncoderService());

$response = $controller->syncOfflineBatch([
    'store_name' => 'KEDAI KUJANG BANDUNG',
    'shift_code' => 'SHIFT-20261109-PAGI',
    'inventory'  => ['KOP-001' => 50, 'ROT-002' => 25],
    'orders'     => [
        [
            'order_id'         => 'ORD-2026-001',
            'payment_method'   => 'QRIS',
            'discount'         => 3000,
            'tax_rate_percent' => 11.0,
            'items'            => [
                ['sku' => 'KOP-001', 'name' => 'Kopi Susu Gula Aren', 'price' => 18000, 'hpp' => 7500, 'qty' => 2],
            ],
        ],
    ],
]);

// Returns updated stock, raw ESC/POS byte stream, validated QRIS payload, and Shift Z-Report
```

---

## License

Released under the [MIT License](composer.json). Crafted for Indonesian UMKM, FnB outlets, and freelance Laravel developers.

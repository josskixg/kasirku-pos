// KasirKu Offline-First POS Engine, Dynamic QRIS TLV & ESC/POS 58mm Encoder (Zero Dependencies)
// Ponytail: uses localStorage/IndexedDB for single-device offline store; add WebRTC/REST sync when multi-cashier needed.

function formatIDR(amount) {
  const num = Math.round(Number(amount) || 0);
  const sign = num < 0 ? '-' : '';
  return sign + 'Rp ' + Math.abs(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function calculateSummary(items, discount = 0, taxRatePercent = 0) {
  let revenue = 0;
  let cost = 0;
  let itemCount = 0;
  for (const item of items) {
    const qty = Math.max(0, Number(item.qty) || 0);
    if (qty === 0) continue;
    revenue += (Number(item.price) || 0) * qty;
    cost += (Number(item.hpp) || 0) * qty;
    itemCount += qty;
  }
  const appliedDiscount = Math.min(revenue, Math.max(0, Number(discount) || 0));
  const netRevenue = Math.max(0, revenue - appliedDiscount);
  const taxRate = Math.max(0, Number(taxRatePercent) || 0);
  const taxAmount = Math.round(netRevenue * (taxRate / 100));
  const grandTotal = netRevenue + taxAmount;
  const profit = netRevenue - cost;
  const marginPercent = netRevenue > 0 ? Number(((profit / netRevenue) * 100).toFixed(1)) : 0;

  return {
    subtotal: revenue,
    discount: appliedDiscount,
    taxRatePercent: taxRate,
    taxAmount,
    total: grandTotal,
    hppTotal: cost,
    profit,
    marginPercent,
    itemCount
  };
}

function crc16Ccitt(str) {
  let crc = 0xFFFF;
  for (let i = 0; i < str.length; i++) {
    crc ^= (str.charCodeAt(i) << 8);
    for (let b = 0; b < 8; b++) {
      crc = (crc & 0x8000) ? ((crc << 1) ^ 0x1021) & 0xFFFF : (crc << 1) & 0xFFFF;
    }
  }
  return crc.toString(16).toUpperCase().padStart(4, '0');
}

function buildDynamicQris(storeName, orderId, amount) {
  const tlv = (tag, val) => tag + String(val.length).padStart(2, '0') + val;
  const merchant = String(storeName || 'KASIRKU UMKM').toUpperCase().slice(0, 25);
  const ord = String(orderId || 'ORD-001').replace(/[^A-Za-z0-9-]/g, '').slice(0, 20);
  const gui = tlv('00', 'ID.CO.QRIS.WWW') + tlv('01', '936009140000001234') + tlv('03', 'UMI');
  const body =
    tlv('00', '01') +
    tlv('01', '12') +
    tlv('26', gui) +
    tlv('52', '5812') +
    tlv('53', '360') +
    tlv('54', String(Math.max(0, Math.round(Number(amount) || 0)))) +
    tlv('58', 'ID') +
    tlv('59', merchant) +
    tlv('60', 'INDONESIA') +
    tlv('62', tlv('01', ord)) +
    '6304';
  return body + crc16Ccitt(body);
}

function encodeEscPos58mm(storeName, orderId, items, discount = 0, taxRatePercent = 0, paymentMethod = 'QRIS', cashReceived = 0) {
  const summary = calculateSummary(items, discount, taxRatePercent);
  const enc = new TextEncoder();
  const lines = [];
  const padLine = (left, right, width = 32) => {
    const r = String(right);
    const l = String(left).slice(0, Math.max(1, width - r.length - 1));
    const spaces = Math.max(1, width - l.length - r.length);
    return l + ' '.repeat(spaces) + r + '\n';
  };

  lines.push('\x1B\x40'); // ESC @ Initialize printer
  lines.push('\x1B\x61\x01'); // ESC a 1 Center align
  lines.push('\x1B\x21\x18' + String(storeName || 'KASIRKU POS').toUpperCase().slice(0, 28) + '\x1B\x21\x00\n');
  lines.push('OFFLINE-FIRST UMKM RETAIL ENGINE\n');
  lines.push('--------------------------------\n');
  lines.push('\x1B\x61\x00'); // ESC a 0 Left align
  lines.push(padLine('Order: #' + orderId, String(paymentMethod || 'QRIS').toUpperCase()));
  lines.push('--------------------------------\n');

  for (const item of items) {
    const qty = Math.max(0, Number(item.qty) || 0);
    if (!qty) continue;
    lines.push(String(item.name).slice(0, 32) + '\n');
    lines.push(padLine(`  ${qty} x ${formatIDR(item.price)}`, formatIDR(qty * item.price)));
  }

  lines.push('--------------------------------\n');
  lines.push(padLine('Subtotal', formatIDR(summary.subtotal)));
  if (summary.discount > 0) {
    lines.push(padLine('Diskon', '-' + formatIDR(summary.discount)));
  }
  if (summary.taxAmount > 0) {
    lines.push(padLine(`PPN (${summary.taxRatePercent}%)`, formatIDR(summary.taxAmount)));
  }
  lines.push('================================\n');
  lines.push('\x1B\x45\x01' + padLine('TOTAL BAYAR', formatIDR(summary.total)) + '\x1B\x45\x00');
  if (Number(cashReceived) > 0) {
    const change = Math.max(0, Number(cashReceived) - summary.total);
    lines.push(padLine('Tunai', formatIDR(cashReceived)));
    lines.push(padLine('Kembali', formatIDR(change)));
  }
  lines.push('--------------------------------\n');
  lines.push('\x1B\x61\x01Terima Kasih - Lunas!\n\n\n\x1D\x56\x41\x10'); // GS V A 0x10 Partial cut

  return enc.encode(lines.join(''));
}

function bytesToHexDump(uint8Arr, maxBytes = 64) {
  if (!uint8Arr || !uint8Arr.length) return '';
  const slice = uint8Arr.slice(0, maxBytes);
  const hex = Array.from(slice).map(b => b.toString(16).toUpperCase().padStart(2, '0')).join(' ');
  return uint8Arr.length > maxBytes ? `${hex} ... (+${uint8Arr.length - maxBytes} B)` : hex;
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { formatIDR, calculateSummary, crc16Ccitt, buildDynamicQris, encodeEscPos58mm, bytesToHexDump };
}

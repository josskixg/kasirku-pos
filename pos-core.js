// KasirKu Offline-First POS Engine & ESC/POS 58mm Encoder (Zero Dependencies)
// Ponytail: uses localStorage/IndexedDB for single-device offline store; add WebRTC/REST sync when multi-cashier needed.

function formatIDR(amount) {
  return 'Rp ' + Math.round(Number(amount) || 0).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function calculateSummary(items, discount = 0) {
  let revenue = 0;
  let cost = 0;
  for (const item of items) {
    const qty = Math.max(0, Number(item.qty) || 0);
    revenue += (Number(item.price) || 0) * qty;
    cost += (Number(item.hpp) || 0) * qty;
  }
  const netRevenue = Math.max(0, revenue - (Number(discount) || 0));
  return {
    subtotal: revenue,
    discount: Number(discount) || 0,
    total: netRevenue,
    hppTotal: cost,
    profit: netRevenue - cost
  };
}

function encodeEscPos58mm(storeName, orderId, items, discount = 0) {
  const summary = calculateSummary(items, discount);
  const enc = new TextEncoder();
  const lines = [];
  const padLine = (left, right, width = 32) => {
    const l = String(left).slice(0, width - String(right).length - 1);
    const spaces = Math.max(1, width - l.length - String(right).length);
    return l + ' '.repeat(spaces) + String(right) + '\n';
  };

  lines.push('\x1B\x40'); // Init printer
  lines.push('\x1B\x61\x01'); // Align center
  lines.push('\x1B\x21\x10' + (storeName || 'KASIRKU POS') + '\x1B\x21\x00\n');
  lines.push('--------------------------------\n');
  lines.push('\x1B\x61\x00'); // Align left
  lines.push(padLine('Order:', '#' + orderId));
  lines.push('--------------------------------\n');

  for (const item of items) {
    lines.push(item.name.slice(0, 32) + '\n');
    lines.push(padLine(`  ${item.qty} x ${formatIDR(item.price)}`, formatIDR(item.qty * item.price)));
  }

  lines.push('--------------------------------\n');
  if (summary.discount > 0) {
    lines.push(padLine('Diskon', '-' + formatIDR(summary.discount)));
  }
  lines.push('\x1B\x21\x08' + padLine('TOTAL', formatIDR(summary.total)) + '\x1B\x21\x00');
  lines.push('--------------------------------\n');
  lines.push('\x1B\x61\x01Terima Kasih!\n\n\n\x1D\x56\x41\x10'); // Cut paper

  return enc.encode(lines.join(''));
}

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { formatIDR, calculateSummary, encodeEscPos58mm };
}

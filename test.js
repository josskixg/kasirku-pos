const assert = require('assert');
const { formatIDR, calculateSummary, encodeEscPos58mm } = require('./pos-core.js');

assert.strictEqual(formatIDR(25000), 'Rp 25.000');

const items = [
  { name: 'Kopi Susu Gula Aren', price: 18000, hpp: 7500, qty: 2 },
  { name: 'Roti Bakar Coklat', price: 15000, hpp: 6000, qty: 1 }
];

const res = calculateSummary(items, 3000);
assert.strictEqual(res.subtotal, 51000);
assert.strictEqual(res.total, 48000);
assert.strictEqual(res.hppTotal, 21000);
assert.strictEqual(res.profit, 27000);

const receiptBytes = encodeEscPos58mm('KEDAI KUJANG', 'ORD-001', items, 3000);
assert(receiptBytes instanceof Uint8Array);
assert(receiptBytes.length > 50);
assert.strictEqual(receiptBytes[0], 0x1B); // ESC
assert.strictEqual(receiptBytes[1], 0x40); // @

console.log('PASS: All POS core & ESC/POS 58mm encoder tests verified.');

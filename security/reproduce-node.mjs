// Runs real Express routers against an in-memory database substitute only.
// No production database or remote services are contacted.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import vm from 'node:vm';
const require = createRequire(new URL('../apps/webhook-service/package.json', import.meta.url));
const express = require('express');
const rows = new Map([[1, { id: 1, product_id: 'fixture', product_title: 'Synthetic product', price_cents: 29900, customer_contact: 'fixture@example.invalid', status: 'pending' }]]);
const pool = { async query(sql, values) {
  if (sql.startsWith('UPDATE')) {
    const row = rows.get(Number(values[0]));
    if (row) row.status = 'paid';
    return [{ affectedRows: row ? 1 : 0 }];
  }
  if (sql.startsWith('INSERT')) {
    const id = rows.size + 1;
    rows.set(id, { id, product_id: values[0], product_title: values[1], price_cents: values[2], customer_contact: values[3], status: 'pending' });
    return [{ insertId: id }];
  }
  if (sql.startsWith('SELECT')) return [rows.has(Number(values[0])) ? [rows.get(Number(values[0]))] : []];
  throw new Error('Unexpected SQL');
} };
function loadRouter(file, name) {
  const original = readFileSync(new URL(`../apps/webhook-service/src/routes/${file}`, import.meta.url), 'utf8');
  const source = original.replace(/^import .*;\r?\n/gm, '').replace(`export const ${name}`, `const ${name}`);
  return vm.runInNewContext(`${source}\n${name}`, { Router: express.Router, pool, console: { log() {} } });
}
const app = express();
app.use(express.json());
app.use('/orders', loadRouter('orders.js', 'ordersRouter'));
app.use('/webhooks', loadRouter('webhook.js', 'webhookRouter'));
const server = app.listen(0, '127.0.0.1');
await new Promise(resolve => server.once('listening', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const evidence = [];
async function request(path, body) {
  const response = await fetch(base + path, body === undefined ? {} : { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
  return { status: response.status, body: await response.json() };
}
try {
  const read = await request('/orders/1');
  assert.equal(read.status, 200);
  assert.equal(read.body.customer_contact, 'fixture@example.invalid');
  evidence.push({ finding: 'NODE-READ', reproduced: true, anonymousRead: read });
  const paid = await request('/webhooks/payment-slip', { orderId: 1 });
  assert.equal(paid.status, 200);
  assert.equal(rows.get(1).status, 'paid');
  evidence.push({ finding: 'NODE-PAYMENT', reproduced: true, unsignedPayment: paid });
  const replay = await request('/webhooks/payment-slip', { orderId: 1 });
  assert.equal(replay.status, 200);
  evidence.push({ finding: 'NODE-PAYMENT', replayAccepted: true });
  for (const priceCents of [1, -100]) {
    const result = await request('/orders', { productId: 'fixture', productTitle: 'Synthetic product', priceCents, customerContact: 'fixture@example.invalid' });
    assert.equal(result.status, 201);
    assert.equal(result.body.price_cents, priceCents);
    evidence.push({ finding: 'NODE-PRICE', reproduced: true, result });
  }
  const invalid = await request('/orders', {});
  assert.equal(invalid.status, 400);
  evidence.push({ control: 'Missing required fields rejected', status: invalid.status });
  console.log(JSON.stringify({ mode: 'real Express routers; database substitute; loopback only', evidence }, null, 2));
} finally {
  server.closeAllConnections();
  await new Promise(resolve => server.close(resolve));
}

import assert from 'node:assert/strict';
import express from 'express';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';
function router(file, name) {
  const source = readFileSync(new URL(`../apps/webhook-service/src/routes/${file}`, import.meta.url), 'utf8')
    .replace(/^import .*;\r?\n/gm, '').replace(`export const ${name}`, `const ${name}`);
  return vm.runInNewContext(`${source}\n${name}`, { Router: express.Router, pool: { query: async () => [{ affectedRows: 1 }] }, console: { log() {} } });
}
const ordersRouter = router('orders.js', 'ordersRouter');
const webhookRouter = router('webhook.js', 'webhookRouter');
const app = express();
app.use(express.json());
app.use('/orders', ordersRouter);
app.use('/webhooks', webhookRouter);
const server = app.listen(0, '127.0.0.1');
await new Promise(resolve => server.once('listening', resolve));
try {
  for (const [method, path, body] of [
    ['POST', '/webhooks/payment-slip', { orderId: 1 }],
    ['POST', '/orders', { productId: 'fixture', productTitle: 'Fixture', priceCents: -100, customerContact: 'fixture@example.invalid' }],
    ['GET', '/orders/1'],
    ['POST', '/webhooks/notify', {}],
  ]) {
    const response = await fetch(`http://127.0.0.1:${server.address().port}${path}`, { method, headers: { 'Content-Type': 'application/json' }, body: body ? JSON.stringify(body) : undefined });
    assert.equal(response.status, 503, `${method} ${path} must fail closed`);
    console.log(`PASS ${method} ${path} disabled`);
  }
} finally {
  server.closeAllConnections();
  await new Promise(resolve => server.close(resolve));
}

// Local-only, non-destructive HTTP regression checks. No valid user credentials.
import assert from 'node:assert/strict';
const base = 'http://localhost:8080/wp-json/nexus/v1';
const call = (path, options = {}) => fetch(base + path, { ...options, signal: AbortSignal.timeout(15000) });
assert.equal((await call('')).status, 200, 'WordPress must boot');
assert.equal((await call('/orders', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ product_id: 1, phone: '0000000000' }) })).status, 401);
assert.equal((await call('/orders/track?query=NX-ABCDEF')).status, 401);
const allowed = await call('/logout', { method: 'OPTIONS', headers: { Origin: 'http://localhost:4321', 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'X-Nexus-Token' } });
assert.equal(allowed.status, 200);
assert.equal(allowed.headers.get('access-control-allow-origin'), 'http://localhost:4321');
assert.ok(allowed.headers.get('access-control-allow-headers').includes('X-Nexus-Token'));
const denied = await call('/me', { headers: { Origin: 'https://untrusted.example.invalid' } });
assert.notEqual(denied.headers.get('access-control-allow-origin'), 'https://untrusted.example.invalid');
assert.notEqual(denied.headers.get('access-control-allow-origin'), '*');
assert.ok(denied.headers.get('cache-control').includes('no-store'));
const pma = await fetch('http://localhost:8082/', { signal: AbortSignal.timeout(10000) });
const html = await pma.text();
assert.match(html, /name="pma_username"/);
assert.doesNotMatch(html, /id="pma_navigation"/);
console.log('PASS live bootstrap, authorization, CORS, private caching, phpMyAdmin login');
if (process.argv.includes('--rate-limit')) {
  const statuses = await Promise.all(Array.from({ length: 12 }, (_, index) => call('/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'CF-Connecting-IP': `192.0.2.${index + 1}`, 'X-Forwarded-For': `192.0.2.${index + 1}` },
    body: JSON.stringify({ username: 'security_audit_nonexistent_20260910', password: 'not-a-real-password' }),
  }).then(response => response.status)));
  assert.ok(statuses.every(status => [401, 429].includes(status)));
  assert.ok(statuses.filter(status => status === 401).length <= 5, 'concurrent calls must not exceed the limit');
  assert.ok(statuses.filter(status => status === 429).length >= 7, 'forged IP headers must not evade the limit');
  console.log('PASS 12 concurrent logins with forged IP headers:', JSON.stringify(statuses));
}

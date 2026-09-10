// Read-only probes of this project's known local services. No credentials sent.
const targets = [
  ['frontend', 'http://localhost:4321/'],
  ['wordpress-routes', 'http://localhost:8080/wp-json/nexus/v1'],
  ['anonymous-me', 'http://localhost:8080/wp-json/nexus/v1/me'],
  ['nonexistent-order', 'http://localhost:8080/wp-json/nexus/v1/orders/track?query=NX-AUDIT-NONEXISTENT-20260910'],
  ['phpmyadmin', 'http://localhost:8082/'],
  ['webhook-health', 'http://localhost:4000/health'],
];
for (const [name, url] of targets) {
  try {
    const response = await fetch(url, { redirect: 'manual', signal: AbortSignal.timeout(7000) });
    const body = await response.text();
    console.log(JSON.stringify({
      name, status: response.status,
      headers: Object.fromEntries(['content-security-policy', 'x-frame-options', 'x-content-type-options', 'referrer-policy', 'access-control-allow-origin'].map(key => [key, response.headers.get(key)])),
      devClient: body.includes('/@vite/client'),
      phpmyadminLoginForm: /name="pma_username"/.test(body),
      phpmyadminAuthenticatedUI: /route=\/logout|route=%2Flogout|id="pma_navigation"/.test(body),
      routes: name === 'wordpress-routes' && response.headers.get('content-type')?.includes('application/json') ? Object.keys(JSON.parse(body).routes || {}) : undefined,
    }));
  } catch (error) {
    console.log(JSON.stringify({ name, error: error.message }));
  }
}
try {
  const response = await fetch('http://localhost:8080/wp-json/nexus/v1/logout', {
    method: 'OPTIONS',
    headers: { Origin: 'http://localhost:4321', 'Access-Control-Request-Method': 'POST', 'Access-Control-Request-Headers': 'X-Nexus-Token' },
    signal: AbortSignal.timeout(7000),
  });
  console.log(JSON.stringify({ name: 'logout-preflight', status: response.status, allowHeaders: response.headers.get('access-control-allow-headers'), allowOrigin: response.headers.get('access-control-allow-origin') }));
} catch (error) {
  console.log(JSON.stringify({ name: 'logout-preflight', error: error.message }));
}

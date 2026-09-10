import assert from 'node:assert/strict';
import { safeArticleHtml } from '../apps/web/src/lib/safe-html.mjs';
const output = safeArticleHtml('<p>Readable <strong>article</strong></p><script>alert(1)</script><img src="https://example.invalid/a.png" onerror="alert(2)"><a href="javascript:alert(3)">link</a><svg onload="alert(4)"></svg>');
assert.ok(output.includes('<strong>article</strong>'));
assert.ok(output.includes('https://example.invalid/a.png'));
assert.ok(!/script|onerror|onload|javascript:|<svg/.test(output));
console.log('PASS article formatting preserved and executable HTML removed');

import assert from 'node:assert/strict';

// Run inside the frontend container: this measures internal HTTP, not WAN speed.
const started = performance.now();
const results = await Promise.all(Array.from({ length: 8 }, async () => {
  const response = await fetch('http://backend/up', { signal: AbortSignal.timeout(10000) });
  await response.text();
  assert.equal(response.status, 200);
  return Math.round(performance.now() - started);
}));

console.log(JSON.stringify({ requests: results.length, completionMs: results }));
assert.ok(Math.max(...results) < 1500, 'Internal health requests queued behind idle HTTP connections');

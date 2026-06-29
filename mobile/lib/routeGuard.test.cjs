const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const test = require('node:test');
const ts = require('typescript');

function loadRouteGuard() {
  const source = readFileSync(join(__dirname, 'routeGuard.ts'), 'utf8');
  const { outputText } = ts.transpileModule(source, {
    compilerOptions: {
      module: ts.ModuleKind.CommonJS,
      target: ts.ScriptTarget.ES2020,
    },
  });
  const module = { exports: {} };
  new Function('exports', 'module', outputText)(module.exports, module);
  return module.exports;
}

test('authenticated users stay on private routes', () => {
  const { getAuthRedirect } = loadRouteGuard();

  assert.equal(getAuthRedirect(true, '/(tabs)'), null);
  assert.equal(getAuthRedirect(true, '/settings'), null);
});

test('guest users are sent to login from private routes', () => {
  const { getAuthRedirect } = loadRouteGuard();

  assert.equal(getAuthRedirect(false, '/(tabs)'), '/login');
});

test('authenticated users leave public auth screens', () => {
  const { getAuthRedirect } = loadRouteGuard();

  assert.equal(getAuthRedirect(true, '/login'), '/(tabs)');
  assert.equal(getAuthRedirect(true, '/forgot-password'), '/(tabs)');
});

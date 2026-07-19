const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');

test('meal prep and accomplishments are separate primary tabs', () => {
  const layout = fs.readFileSync(path.join(root, 'app', '(tabs)', '_layout.tsx'), 'utf8');
  const prep = fs.readFileSync(path.join(root, 'app', '(tabs)', 'prep.tsx'), 'utf8');

  assert.match(layout, /name="prep"/);
  assert.match(layout, /name="accomplishments"/);
  assert.doesNotMatch(prep, /<AccomplishmentSection/);
});

test('menu cycle uses a controlled day picker and keeps snapshot profiles', () => {
  const menu = fs.readFileSync(path.join(root, 'app', '(tabs)', 'menu.tsx'), 'utf8');

  assert.match(menu, /<DayPicker/);
  assert.match(menu, /entry\.po_snapshot/);
  assert.match(menu, /Planned population/);
  assert.match(menu, /Total served/);
});

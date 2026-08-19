const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../app/(tabs)/procurement.tsx'), 'utf8');

test('mobile receiving keeps vendor scope and value comparison clear', () => {
  for (const label of [
    'Change vendor for all',
    'Change vendor',
    'Planned purchase',
    'Actual purchased',
    'Calculation details',
    'Calculated need',
    'Not reviewed',
  ]) assert.match(source, new RegExp(label));
  assert.match(source, /item_id/);
  assert.doesNotMatch(source, /Calculated:/);
});

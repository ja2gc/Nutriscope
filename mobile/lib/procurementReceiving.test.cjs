const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const assert = require('node:assert/strict');

const source = fs.readFileSync(path.join(__dirname, '../app/(tabs)/procurement.tsx'), 'utf8');

test('mobile receiving keeps vendor scope and shows compact actual PO item fields', () => {
  for (const label of [
    'Change vendor for all',
    'Change vendor',
    'Actual Quantity',
    'Unit',
    'Actual Cost/unit',
    'Actual Total',
  ]) assert.match(source, new RegExp(label));
  for (const label of ['Calculation details', 'Planned purchase:', 'Actual purchased:']) {
    assert.match(source, new RegExp(label));
  }
  for (const label of ['Calculated need:', 'Quantity difference:', 'Cost difference:']) {
    assert.doesNotMatch(source, new RegExp(label));
  }
  assert.doesNotMatch(source, /Not reviewed|>Reviewed</);
  assert.doesNotMatch(source, /actual_values_confirmed|actual_values_reviewed/);
  assert.match(source, /item_id/);
  assert.match(source, /'Content-Type': 'multipart\/form-data'/);
  assert.match(source, /staleTime: 0/);
  assert.match(source, /purchaseOrderStatusLabel\(po\.lifecycle_status\)/);
  assert.doesNotMatch(source, />\{po\.lifecycle_status\}</);
  assert.match(source, /group\.status === 'received' \|\| Boolean\(group\.received_at\)/);
  assert.match(source, /Could not load vendors/);
  assert.match(source, /consumedTargetPo/);
  assert.doesNotMatch(source, /Calculated:/);
});

test('mobile actual PO fields show two decimals and keep full precision until edited', () => {
  assert.match(source, /maximumFractionDigits:\s*2/);
  assert.match(source, /qtyEdited:\s*false/);
  assert.match(source, /Show planned total/);
  assert.match(source, /priceEdited:\s*false/);
  assert.match(source, /actual_qty:\s*actuals\[item\.id\]\?\.qtyEdited\s*\?\s*Number\(actuals\[item\.id\]\.qty\)\s*:\s*Number\(item\.actual_qty\)/);
  assert.match(source, /actual_unit_price:\s*actuals\[item\.id\]\?\.priceEdited\s*\?\s*Number\(actuals\[item\.id\]\.price\)\s*:\s*Number\(item\.actual_unit_price\)/);
});

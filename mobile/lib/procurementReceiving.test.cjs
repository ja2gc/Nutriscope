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
    assert.doesNotMatch(source, new RegExp(label));
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
  assert.match(source, /po\.display_name/);
  assert.doesNotMatch(source, />\{po\.po_number\}</);
  assert.doesNotMatch(source, />\{po\.lifecycle_status\}</);
  assert.match(source, /group\.status === 'received' \|\| Boolean\(group\.received_at\)/);
  assert.match(source, /Could not load vendors/);
  assert.match(source, /consumedTargetPo/);
  assert.doesNotMatch(source, /Calculated:/);
  assert.doesNotMatch(source, /Mark vendor received/);
  assert.doesNotMatch(source, /status: 'received'/);
  assert.match(source, /<Text className="text-sm font-semibold text-white">Save<\/Text>/);
  assert.doesNotMatch(source, /Save actuals and optional OR|Save optional OR/);
  assert.match(source, /Unsaved vendor changes/);
  assert.match(source, /Save your changes before leaving/);
  assert.match(source, /usePreventRemove/);
  const tabsLayout = fs.readFileSync(path.join(__dirname, '../app/(tabs)/_layout.tsx'), 'utf8');
  assert.match(tabsLayout, /screenListeners=/);
  assert.match(tabsLayout, /handleVendorReceivingTabPress/);
  assert.match(source, /registerVendorReceivingTabGuard/);
  assert.doesNotMatch(source, /tabsNavigation\.addListener\('tabPress'/);
  assert.match(source, /Save/);
  assert.match(source, /\(group\.items \?\? \[\]\)\.map\(\(item, idx\)/);
  assert.doesNotMatch(source, /itemPager|setItemPage/);
});

test('mobile actual PO fields show two decimals and keep full precision until edited', () => {
  assert.match(source, /maximumFractionDigits:\s*2/);
  assert.match(source, /qtyEdited:\s*false/);
  assert.match(source, /Current total/);
  assert.match(source, /po\.current_total_amount/);
  assert.match(source, /priceEdited:\s*false/);
  assert.match(source, /receipt_total:\s*Number\(draft\.total\)/);
  assert.match(source, /totalEdited:\s*false/);
  assert.match(source, /Math\.round\(Number\(draft\.total\) \/ price \* 1000\) \/ 1000/);
  assert.match(source, /actual_qty:\s*draft\.qtyEdited\s*\?\s*Number\(draft\.qty\)\s*:\s*Number\(item\.actual_qty\)/);
  assert.match(source, /actual_unit_price:\s*draft\.priceEdited\s*\?\s*Number\(draft\.price\)\s*:\s*Number\(item\.actual_unit_price\)/);
});

test('mobile receipt total supports a saved accessible automatic mode independent of item scaling', () => {
  assert.match(source, /accessibilityRole="checkbox"/);
  assert.match(source, /accessibilityLabel="Use calculated total"/);
  assert.match(source, /accessibilityLabel="Actual receipt total"/);
  assert.match(source, /use_calculated_total: useCalculatedTotal/);
  assert.match(source, /setReceiptTotal\(value\); setUseCalculatedTotal\(false\)/);
  assert.match(source, /useCalculatedTotal \? calculatedReceiptTotal : receiptTotal/);
});

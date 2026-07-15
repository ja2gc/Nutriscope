const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const test = require('node:test');

const announcements = readFileSync(join(__dirname, '..', 'app', 'announcements.tsx'), 'utf8');
const procurement = readFileSync(join(__dirname, '..', 'app', '(tabs)', 'procurement.tsx'), 'utf8');

test('numeric API ids are converted before entering string route-selection state', () => {
  assert.match(announcements, /setSelectedId\(String\(item\.id\)\)/);
  assert.match(procurement, /setSelectedPoId\(String\(po\.id\)\)/);
});

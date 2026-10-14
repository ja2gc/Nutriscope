const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { pathToFileURL } = require('node:url');
const test = require('node:test');

const root = join(__dirname, '..');
const read = (path) => readFileSync(join(root, path), 'utf8');

test('service-day navigation follows Manila today and calendar boundaries', async () => {
  const dates = await import(pathToFileURL(join(__dirname, 'localDate.ts')).href);
  assert.equal(dates.todayManilaKey(new Date('2026-10-13T16:00:00Z')), '2026-10-14');
  assert.equal(dates.todayManilaKey(new Date('2026-10-14T15:59:59Z')), '2026-10-14');
  assert.equal(dates.addCalendarDays('2026-12-31', 1), '2027-01-01');
  assert.equal(dates.addCalendarDays('2027-01-01', -1), '2026-12-31');
});

test('announcement detail fills the screen and has a visible Back action', () => {
  const screen = read('components/AnnouncementsScreen.tsx');
  assert.match(screen, /visible=\{selected !== null\}[^>]*presentationStyle="fullScreen"/);
  assert.match(screen, /Back to announcements/);
  assert.doesNotMatch(screen, /max-h-\[82%\]/);
});

test('SOP history opens selectable versions with Back to list', () => {
  const screen = read('app/sop-history.tsx');
  const announcements = read('components/AnnouncementsScreen.tsx');
  assert.match(announcements, /router\.push\('\/sop-history'/);
  assert.match(screen, /setSelectedHistory\(version\)/);
  assert.match(screen, /Back to SOP history/);
  assert.match(screen, /selectedHistory\.body/);
});

test('daily FSS screens allow one-day navigation and show saved state', () => {
  const prep = read('app/(tabs)/prep.tsx');
  const accomplishment = read('app/(tabs)/accomplishments.tsx');
  const menu = read('components/menu/DayPicker.tsx');
  for (const screen of [prep, accomplishment, menu]) {
    assert.match(screen, /Previous day/);
    assert.match(screen, /Next day/);
  }
  assert.doesNotMatch(prep, /disabled=\{!cycle\}/);
  assert.match(prep, /Not logged/);
  assert.match(accomplishment, /Not logged/);
  assert.match(accomplishment, /Off duty/);
});

test('FSS service screens select the Manila date and show unplanned weeks plainly', () => {
  const menu = read('app/(tabs)/menu.tsx');
  const prep = read('app/(tabs)/prep.tsx');
  const accomplish = read('app/(tabs)/accomplishments.tsx');
  assert.match(menu, /useState\(todayManilaKey\)/);
  assert.match(menu, /listMenuCycles\(1, false, selectedDate\)/);
  assert.match(menu, /Menu not planned for this week yet/);
  assert.match(menu, /days=\{DAYS\}/);
  assert.match(prep, /listMenuCycles\(1, false, selectedDate\)/);
  assert.match(prep, /useFocusEffect/);
  assert.match(prep, /Menu not planned for this week yet/);
  assert.match(prep, /This service date is not active yet/);
  assert.doesNotMatch(prep, /outside the selected menu week/);
  assert.match(accomplish, /todayManilaKey\(\)/);
  assert.match(accomplish, /useFocusEffect/);
});

test('PO detail exposes lifecycle and vendor evidence without item calculations', () => {
  const screen = read('app/(tabs)/procurement.tsx');
  assert.match(screen, /Status: \{purchaseOrderStatusLabel\(po\.lifecycle_status\)\}/);
  assert.match(screen, /Receipt \{countReceipts\(group\) \? 'uploaded' : 'missing'\}/);
  assert.match(screen, /Proof \{countProofs\(group\) \? 'uploaded' : 'missing'\}/);
  for (const label of ['Calculation details', 'Planned purchase:', 'Actual purchased:', 'Actual Total']) {
    assert.doesNotMatch(screen, new RegExp(label));
  }
});

test('dashboard shows two latest announcements compactly without decorative icons', () => {
  const dashboard = read('app/(tabs)/index.tsx');
  assert.match(dashboard, /per_page: 2, sort: 'latest'/);
  assert.match(dashboard, /latestAnnouncements\.map/);
  assert.match(dashboard, /View image/);
  assert.match(dashboard, /Next action/);
  assert.match(dashboard, /flex-row gap-3/);
  assert.doesNotMatch(dashboard, /<Bell|<Pin|<ShoppingBag|<CalendarDays|<ClipboardList/);
});

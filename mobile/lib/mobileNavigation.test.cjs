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

test('menu cycle is read-only and sends operational counts to meal prep', () => {
  const menu = fs.readFileSync(path.join(root, 'app', '(tabs)', 'menu.tsx'), 'utf8');
  const prep = fs.readFileSync(path.join(root, 'app', '(tabs)', 'prep.tsx'), 'utf8');

  assert.match(menu, /<DayPicker/);
  assert.match(menu, /Planned population/);
  assert.match(menu, /Actual served population is recorded in Meal Prep/);
  assert.match(prep, /Record actual served/);
  assert.match(prep, /setServedPopulation/);
});

test('daily log supports past dates and a clear return to today', () => {
  const accomplish = fs.readFileSync(path.join(root, 'app', '(tabs)', 'accomplishments.tsx'), 'utf8');

  assert.match(accomplish, /maximumDate=\{dateFromKey\(today\)\}/);
  assert.match(accomplish, />Today<\/Text>/);
  assert.match(accomplish, /goToday/);
  assert.match(accomplish, /service_date: selectedDate/);
});

test('final navigation separates daily tabs, header actions, and account menu', () => {
  const tabs = fs.readFileSync(path.join(root, 'app', '(tabs)', '_layout.tsx'), 'utf8');
  const header = fs.readFileSync(path.join(root, 'components', 'AppHeader.tsx'), 'utf8');
  const menu = fs.readFileSync(path.join(root, 'components', 'AccountMenu.tsx'), 'utf8');

  ['index', 'menu', 'prep', 'accomplishments', 'procurement', 'announcements'].forEach((name) => assert.match(tabs, new RegExp(`name="${name}"`)));
  assert.ok(tabs.indexOf('name="index"') < tabs.indexOf('name="announcements"'));
  assert.ok(tabs.indexOf('name="announcements"') < tabs.indexOf('name="menu"'));
  assert.match(tabs, /title: 'Announcement'/);
  assert.doesNotMatch(tabs, /Notices/);
  assert.match(tabs, /Newspaper/);
  assert.match(header, /<Bell/);
  assert.match(header, /<UserCircle/);
  assert.doesNotMatch(header, /Megaphone|Newspaper/);
  ['Profile', 'Notifications', 'Help', 'Settings', 'Check for updates', 'Sign out'].forEach((label) => assert.match(menu, new RegExp(label)));
  assert.doesNotMatch(menu, /Announcements & SOP/);
});

test('announcement page separates announcements and SOP into internal tabs', () => {
  const screen = fs.readFileSync(path.join(root, 'app', 'announcements.tsx'), 'utf8');
  const notifications = fs.readFileSync(path.join(root, 'app', 'notifications.tsx'), 'utf8');

  assert.match(screen, /accessibilityRole="tablist"/);
  assert.match(screen, />\s*Announcements\s*<\/Text>/);
  assert.match(screen, />SOP<\/Text>/);
  assert.match(screen, /section === 'sop'/);
  assert.match(notifications, /pathname: '\/\(tabs\)\/announcements'/);
});

test('report details can prepare and open or save the authenticated PDF', () => {
  const screen = fs.readFileSync(path.join(root, 'app', 'reports.tsx'), 'utf8');
  const reports = fs.readFileSync(path.join(root, 'lib', 'reports.ts'), 'utf8');

  assert.match(screen, /Open or save PDF/);
  assert.match(reports, /accomplishment_report\/prepare/);
  assert.match(reports, /Authorization: `Bearer \$\{token\}`/);
  assert.match(reports, /Sharing\.shareAsync/);
});

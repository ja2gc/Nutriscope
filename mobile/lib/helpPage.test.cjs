const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const read = (relativePath) => {
  const target = path.join(root, relativePath);
  return fs.existsSync(target) ? fs.readFileSync(target, 'utf8') : '';
};

test('FSS Help stays in the account menu while Announcement is the second bottom tab', () => {
  const settings = read('app/settings.tsx');
  const layout = read('app/_layout.tsx');
  const tabs = read('app/(tabs)/_layout.tsx');

  assert.match(settings, /Help & Support/);
  assert.match(settings, /router\.push\('\/help'(?: as Href)?\)/);
  assert.match(layout, /name="help"/);
  assert.match(tabs, /name="announcements"/);
  assert.match(tabs, /title: 'Announcement'/);
  assert.equal((tabs.match(/<Tabs\.Screen/g) || []).length, 6);
});

test('mobile Help contains only Shared and FSS guidance', () => {
  const content = read('lib/helpContent.ts');

  assert.match(content, /shared-forgot-password/);
  assert.match(content, /fss-main-tabs/);
  assert.match(content, /fss-no-active-menu/);
  assert.match(content, /fss-camera-upload/);
  assert.doesNotMatch(content, /fss-inventory-update/);
  assert.doesNotMatch(content, /role:\s*['"](?:RND|Admin)['"]/);
  assert.doesNotMatch(content, /View all roles|All roles|role switch/i);
});

test('mobile Help uses reusable accessible search and disclosures', () => {
  const screen = read('app/help.tsx');
  const search = read('components/SearchInput.tsx');
  const questions = read('components/help/HelpQuestionList.tsx');

  assert.match(screen, /<SearchInput label="Search help"/);
  assert.match(search, /accessibilityLabel=\{label\}/);
  assert.match(screen, /HelpQuestionList/);
  assert.match(screen, /Clear search/);
  assert.match(questions, /accessibilityRole="button"/);
  assert.match(questions, /accessibilityState=\{\{ expanded/);
  assert.match(questions, /useState<Set<string>>/);
  assert.match(questions, /min-h-12/);
});

test('APK build identity is incremented', () => {
  const appConfig = JSON.parse(read('app.json'));

  assert.equal(appConfig.expo.version, '1.2.2');
  assert.equal(appConfig.expo.android.versionCode, 6);
  assert.deepEqual(appConfig.expo.platforms, ['android', 'ios']);
  assert.deepEqual(appConfig.expo.android.blockedPermissions, ['android.permission.RECORD_AUDIO']);
});

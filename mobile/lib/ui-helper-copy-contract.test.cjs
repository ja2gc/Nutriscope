const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const read = (...parts) => fs.readFileSync(path.join(root, ...parts), 'utf8');

const auditedSource = [
  read('app', 'login.tsx'),
  read('app', 'help.tsx'),
  read('app', '(tabs)', 'procurement.tsx'),
  read('app', '(tabs)', 'prep.tsx'),
  read('app', '(tabs)', 'menu.tsx'),
  read('app', 'food-details.tsx'),
].join('\n');

test('mobile screens omit redundant role and purpose summaries', () => {
  const redundantCopy = [
    'FSS guidance',
    'Food service operations',
    'Sign in to today’s kitchen and procurement workspace.',
    'Secure Connection • Activity Logs Active',
    'Find answers for your account and Food Service Staff workflows.',
    'Open purchase events, save OR numbers, and upload receipt/proof images.',
    'Review the planned meals, then record the actual patient population served.',
    'Read-only weekly plan',
    'Actual served population is recorded in Meal Prep.',
    'read-only for Food Service Staff',
  ];
  const remaining = redundantCopy.filter((copy) => auditedSource.includes(copy));
  assert.deepEqual(remaining, []);
});

test('mobile screens retain actionable safety and state guidance', () => {
  const procurement = read('app', '(tabs)', 'procurement.tsx');
  const help = read('app', 'help.tsx');
  const foodDetails = read('app', 'food-details.tsx');

  assert.match(procurement, /Receipt, proof, and reviewed actual values are required\. OR number is optional\./);
  assert.match(help, /Never share passwords, verification codes, or unnecessary patient information\./);
  assert.ok(
    foodDetails.includes('No planned population is set for this slot. Baseline recipe quantities are shown.'),
    'Missing role-neutral planned-population guidance.',
  );
});

test('Android release metadata advances for the mobile copy update', () => {
  const appConfig = JSON.parse(read('app.json'));
  const release = JSON.parse(read('release.json'));
  assert.equal(appConfig.expo.version, '1.2.6');
  assert.equal(appConfig.expo.android.versionCode, 10);
  assert.equal(release.version, '1.2.6');
  assert.equal(release.version_code, 10);
  assert.equal(release.artifact_url, 'https://expo.dev/artifacts/eas/cfU02kFfg9_zcLVBBETnlQ3O81OkksfDzCfNArH1zPg.apk');
  assert.equal(release.sha256, 'faabbd706896cf6422018da10e8646955516bf1e14791e42843be7f50d159d5f');
});

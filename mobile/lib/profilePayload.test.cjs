const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { pathToFileURL } = require('node:url');
const test = require('node:test');

const profile = readFileSync(join(__dirname, '..', 'app', 'profile.tsx'), 'utf8');
const login = readFileSync(join(__dirname, '..', 'app', 'login.tsx'), 'utf8');

test('unchanged legacy profile names are omitted from unrelated updates', async () => {
  const contract = await import(pathToFileURL(join(__dirname, 'personName.ts')).href);
  const legacy = { first_name: 'Juan Dela Cruz', last_name: null, name: 'Juan Dela Cruz' };

  assert.equal(contract.changedPersonNameFields(legacy, 'Juan Dela Cruz', ''), null);
  assert.deepEqual(contract.changedPersonNameFields(legacy, 'Juan', 'Dela Cruz'), {
    first_name: 'Juan',
    last_name: 'Dela Cruz',
  });
  assert.throws(() => contract.changedPersonNameFields(legacy, 'Juan', ''), /first and last name/i);
});

test('profile uses separate accessible fields and the paired-change payload helper', () => {
  assert.match(profile, /const \[firstName, setFirstName\] = useState\(''\)/);
  assert.match(profile, /const \[lastName, setLastName\] = useState\(''\)/);
  assert.match(profile, /label="First name"/);
  assert.match(profile, /label="Last name"/);
  assert.match(profile, /accessibilityLabel=\{label\}/);
  assert.match(profile, /changedPersonNameFields\(user, firstName, lastName\)/);
  assert.match(profile, /\.\.\.\(nameFields \?\? \{\}\)/);
  assert.doesNotMatch(profile, /name:\s*name\.trim\(\)/);
});

test('login uses the shared response type while keeping SecureStore token persistence', () => {
  assert.match(login, /api\.post<LoginResponse>\('\/api\/auth\/login'/);
  assert.match(login, /await setToken\(token\)/);
});

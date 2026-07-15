const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { pathToFileURL } = require('node:url');
const test = require('node:test');

const profile = readFileSync(join(__dirname, '..', 'app', 'profile.tsx'), 'utf8');
const auth = readFileSync(join(__dirname, 'auth.ts'), 'utf8');

test('mobile auth DTO exposes split, display, and deprecated person names', () => {
  assert.match(auth, /interface UserProfile[\s\S]*?first_name:\s*string\s*\|\s*null;/);
  assert.match(auth, /interface UserProfile[\s\S]*?last_name:\s*string\s*\|\s*null;/);
  assert.match(auth, /interface UserProfile[\s\S]*?display_name:\s*string;/);
  assert.match(auth, /interface UserProfile[\s\S]*?name:\s*string;/);
  assert.match(profile, /import[\s\S]*?UserProfile[\s\S]*?from '\.\.\/lib\/auth'/);
});

test('mobile person display keeps legacy text and never guesses a split', async () => {
  const contract = await import(pathToFileURL(join(__dirname, 'personName.ts')).href);

  assert.equal(contract.personDisplayName({
    first_name: 'Legacy Name',
    last_name: null,
    display_name: '  Legacy  Name  ',
    name: 'Legacy Name',
  }), '  Legacy  Name  ');
  assert.equal(contract.personDisplayName({
    first_name: 'Maria Luisa',
    last_name: 'De la Cruz',
    name: 'Deprecated',
  }), 'Maria Luisa De la Cruz');
  assert.equal(contract.personDisplayName({ first_name: 'Madonna', last_name: null, name: 'Madonna' }), 'Madonna');
});

test('mobile deliberate name writes normalize valid pairs and reject invalid pairs', async () => {
  const contract = await import(pathToFileURL(join(__dirname, 'personName.ts')).href);

  assert.deepEqual(contract.requiredPersonNameFields('  Maria   Luisa ', ' De la Cruz '), {
    first_name: 'Maria Luisa',
    last_name: 'De la Cruz',
  });
  assert.throws(() => contract.requiredPersonNameFields('Maria', ' '), /first and last name/i);
  assert.throws(() => contract.requiredPersonNameFields('Maria\u0000', 'Cruz'), /control/i);
  assert.throws(() => contract.requiredPersonNameFields('M'.repeat(256), 'Cruz'), /255/);
});

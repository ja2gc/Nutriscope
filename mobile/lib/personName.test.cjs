const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const test = require('node:test');

const profile = readFileSync(join(__dirname, '..', 'app', 'profile.tsx'), 'utf8');

test('mobile profile baseline retains deprecated name DTO and payload', () => {
  assert.match(profile, /interface UserProfile[\s\S]*?name:\s*string;/);
  assert.match(profile, /async function updateProfile\(body:\s*\{[\s\S]*?name:\s*string;/);
  assert.match(profile, /profileMutation\.mutate\(\{[\s\S]*?name:\s*name\.trim\(\)/);
});

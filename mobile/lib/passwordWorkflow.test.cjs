const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');

const root = path.resolve(__dirname, '..');
const source = (relativePath) => fs.readFileSync(path.join(root, relativePath), 'utf8');

test('mobile forgot password requests sign-in email and explains recovery delivery', () => {
  const forgot = source('app/forgot-password.tsx');

  assert.match(forgot, /Sign-in Email/);
  assert.match(forgot, /verified recovery email saved for that account/);
  assert.doesNotMatch(forgot, /Enter your verified recovery email/);
});

test('mobile account setup resumes at OTP after password stage', () => {
  const setup = source('app/account-setup.tsx');

  assert.match(setup, /must_change_password/);
  assert.match(setup, /recovery-email\/verify/);
  assert.match(setup, /Verification code/);
  assert.match(setup, /Send another code/);
  assert.match(setup, /Do later/);
  assert.doesNotMatch(setup, /No email code is needed/);
});

test('mobile profile keeps OTP available until recovery setup is verified', () => {
  const profile = source('app/profile.tsx');

  assert.doesNotMatch(profile, /No verification code is needed/);
  assert.doesNotMatch(profile, /!user\?\.must_set_recovery_email\s*&&/);
  assert.match(profile, /Verify recovery email/);
});

test('mobile header reminder uses persistent onboarding state', () => {
  const header = source('components/AppHeader.tsx');

  assert.match(header, /user\?\.onboarding_required && user\.onboarding_skipped/);
  assert.match(header, /router\.push\('\/profile'\)/);
});

const assert = require('node:assert/strict');
const { test } = require('node:test');

const maxBytes = 5 * 1024 * 1024;
const options = {
  maxBytes,
  allowedTypes: ['image/jpeg', 'image/png', 'image/webp'],
};

test('mobile upload validation accepts allowed images at the byte limit', async () => {
  const { validateUploadFile } = await import('./uploadValidation.ts');

  assert.deepEqual(validateUploadFile({ size: maxBytes, type: 'image/jpeg', name: 'receipt.jpg' }, options), { valid: true });
});

test('mobile upload validation rejects oversized and unsupported files', async () => {
  const { validateUploadFile } = await import('./uploadValidation.ts');

  assert.equal(validateUploadFile({ size: maxBytes + 1, type: 'image/jpeg', name: 'large.jpg' }, options).valid, false);
  assert.equal(validateUploadFile({ size: 12, type: 'image/gif', name: 'animated.gif' }, options).valid, false);
});

test('mobile upload validation enforces batch count including files already selected', async () => {
  const { validateUploadFiles } = await import('./uploadValidation.ts');
  const files = Array.from({ length: 2 }, (_, index) => ({
    size: 12,
    type: 'image/jpeg',
    name: `receipt-${index}.jpg`,
  }));

  assert.equal(validateUploadFiles(files, { ...options, maxFiles: 15 }, 14).valid, false);
});

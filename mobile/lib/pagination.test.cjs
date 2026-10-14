const assert = require('node:assert/strict');
const { readFileSync } = require('node:fs');
const { join } = require('node:path');
const { pathToFileURL } = require('node:url');
const test = require('node:test');

test('mobile lists use five items and keep a zero-state page', async () => {
  const pages = await import(pathToFileURL(join(__dirname, 'pagination.ts')).href);
  assert.equal(pages.MOBILE_PAGE_SIZE, 5);
  assert.deepEqual(pages.paginateItems([], 3), { items: [], page: 1, pages: 1, total: 0 });
  assert.deepEqual(pages.paginateItems([1, 2, 3, 4, 5, 6], 2), { items: [6], page: 2, pages: 2, total: 6 });
  assert.deepEqual(pages.paginateItems([1, 2], 9), { items: [1, 2], page: 1, pages: 1, total: 2 });
  const footer = readFileSync(join(__dirname, '../components/MobilePagination.tsx'), 'utf8');
  assert.match(footer, /Page \{page\} of \{pages\}/);
  assert.match(footer, /Previous/);
  assert.match(footer, /Next/);
});

test('FSS item detail lists show the pager', () => {
  for (const path of ['app/food-details.tsx', 'app/help.tsx', 'components/help/HelpQuestionList.tsx', 'app/(tabs)/prep.tsx', 'app/(tabs)/menu.tsx', 'app/(tabs)/procurement.tsx']) {
    assert.match(readFileSync(join(__dirname, '..', path), 'utf8'), /<MobilePagination/, path);
  }
});

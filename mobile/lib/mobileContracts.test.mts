import assert from 'node:assert/strict';
import test from 'node:test';

const contracts = await import('./mobileContracts.ts').catch(() => ({}));

test('private API images use the server URL with bearer authentication', () => {
  assert.equal(typeof contracts.authenticatedImageSource, 'function');
  assert.deepEqual(
    contracts.authenticatedImageSource(
      'https://nutriscope.live/api/',
      '/api/fss/purchase-order-attachments/attachment-uuid/file',
      'secret-token',
    ),
    {
      uri: 'https://nutriscope.live/api/fss/purchase-order-attachments/attachment-uuid/file',
      headers: { Authorization: 'Bearer secret-token' },
    },
  );
});

test('selected report download keeps the selected public report id', () => {
  assert.equal(typeof contracts.reportDownloadPath, 'function');
  assert.equal(
    contracts.reportDownloadPath('report-public-uuid'),
    '/api/fss/reports/report-public-uuid/download',
  );
});

test('all supplier pages are collected without dropping later choices', async () => {
  assert.equal(typeof contracts.collectAllPages, 'function');
  const requested: number[] = [];
  const rows = await contracts.collectAllPages(async (page: number) => {
    requested.push(page);
    return {
      data: [{ id: `supplier-${page}` }],
      meta: { current_page: page, per_page: 10, total: 3, last_page: 3 },
    };
  });

  assert.deepEqual(requested, [1, 2, 3]);
  assert.deepEqual(rows, [
    { id: 'supplier-1' },
    { id: 'supplier-2' },
    { id: 'supplier-3' },
  ]);
});

test('absolute API URLs normalize origins that include an api suffix', () => {
  assert.equal(typeof contracts.absoluteApiUrl, 'function');
  assert.equal(
    contracts.absoluteApiUrl('https://nutriscope.live/api', '/api/fss/reports/r1/download'),
    'https://nutriscope.live/api/fss/reports/r1/download',
  );
});

test('served population must be a positive whole number', () => {
  assert.equal(typeof contracts.isValidServedPopulation, 'function');
  assert.equal(contracts.isValidServedPopulation(1), true);
  assert.equal(contracts.isValidServedPopulation(120), true);
  assert.equal(contracts.isValidServedPopulation(0), false);
  assert.equal(contracts.isValidServedPopulation(-1), false);
  assert.equal(contracts.isValidServedPopulation(1.5), false);
  assert.equal(contracts.isValidServedPopulation(Number.NaN), false);
});

test('mobile notifications route every generated FSS notification to its record', () => {
  assert.deepEqual(
    contracts.mobileNotificationTarget({ type: 'announcement', source_module: 'announcements', sourceId: 'announcement-uuid' }),
    { pathname: '/(tabs)/announcements', params: { announcementId: 'announcement-uuid' } },
  );
  assert.deepEqual(
    contracts.mobileNotificationTarget({ type: 'po_awaiting_receipt', source_module: 'food_service', sourceId: 'po-uuid' }),
    { pathname: '/(tabs)/procurement', params: { poId: 'po-uuid' } },
  );
  assert.deepEqual(
    contracts.mobileNotificationTarget({ type: 'accomplishment_report', source_module: 'reports', sourceId: null }),
    { pathname: '/(tabs)/accomplishments', params: { section: 'reports' } },
  );
});

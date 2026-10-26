import { beforeEach, describe, expect, test, vi } from 'vitest';
import { apiFetch } from '@/lib/apiFetch';
import { listCatalog } from './fsCatalogService';

vi.mock('@/lib/apiFetch', () => ({ apiFetch: vi.fn() }));

describe('paginated food service catalog', () => {
  beforeEach(() => {
    vi.resetAllMocks();
    vi.mocked(apiFetch).mockResolvedValue(new Response(JSON.stringify({ data: [], meta: { current_page: 1, per_page: 10, total: 0, last_page: 1 } })));
  });

  test('forwards catalog page size and grocery filter', async () => {
    const meta = { current_page: 2, per_page: 5, total: 12, last_page: 3 };
    vi.mocked(apiFetch).mockResolvedValueOnce(new Response(JSON.stringify({ data: [{ id: 'rice' }], meta })));
    const result = await listCatalog('ingredient', 2, 'milk', 'auto', 5);
    expect(apiFetch).toHaveBeenCalledWith('/api/fss/fs-items/catalog?page=2&limit=5&kind=ingredient&search=milk&grocery=auto');
    expect(result.meta).toEqual(meta);
    expect(result.data).toEqual([{ id: 'rice' }]);
  });

  test('keeps the ten item default page size for existing catalog callers', async () => {
    const result = await listCatalog('ingredient');
    expect(apiFetch).toHaveBeenCalledWith('/api/fss/fs-items/catalog?page=1&limit=10&kind=ingredient');
    expect(result.meta.per_page).toBe(10);
  });
});

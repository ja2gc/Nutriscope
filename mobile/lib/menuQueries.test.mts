import { QueryClient, QueryObserver } from '@tanstack/react-query';
import { afterEach, expect, test, vi } from 'vitest';
import { getMenuCycle, listMenuCycles } from './foodService';
import { menuCycleQuery, menuDateQuery } from './menuQueries';

vi.mock('./foodService', () => ({ getMenuCycle: vi.fn(), listMenuCycles: vi.fn() }));
afterEach(() => vi.resetAllMocks());

test('changing a day keeps the known week visible while checking the date', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  const week = { id: 'week-one', name: 'Weekly menu', is_active: true, status: 'active', week_start_date: '2026-10-26' };
  client.setQueryData(['fss-menu-date', '2026-10-30'], week);
  let finish!: (value: unknown) => void;
  vi.mocked(listMenuCycles).mockReturnValue(new Promise(resolve => { finish = resolve as typeof finish; }));
  const observer = new QueryObserver(client, { ...menuDateQuery('2026-10-30'), enabled: false });
  const unsubscribe = observer.subscribe(() => {});
  observer.setOptions(menuDateQuery('2026-10-29'));
  expect(observer.getCurrentResult().data).toEqual(week);
  expect(observer.getCurrentResult().isLoading).toBe(false);
  expect(observer.getCurrentResult().isPlaceholderData).toBe(true);
  expect(listMenuCycles).toHaveBeenCalledWith(1, false, '2026-10-29');
  finish({ data: [{ ...week, id: 'updated-week' }] });
  await client.fetchQuery(menuDateQuery('2026-10-29'));
  expect(observer.getCurrentResult().data?.id).toBe('updated-week');
  expect(observer.getCurrentResult().isPlaceholderData).toBe(false);
  unsubscribe();
  client.clear();
});

test('crossing a week boundary waits for its own date result', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  client.setQueryData(['fss-menu-date', '2026-10-30'], {
    id: 'week-one', name: 'Weekly menu', is_active: true, status: 'active', week_start_date: '2026-10-26',
  });
  let finish!: (value: unknown) => void;
  vi.mocked(listMenuCycles).mockReturnValue(new Promise(resolve => { finish = resolve as typeof finish; }));
  const observer = new QueryObserver(client, { ...menuDateQuery('2026-10-30'), enabled: false });
  const unsubscribe = observer.subscribe(() => {});
  observer.setOptions(menuDateQuery('2026-11-02'));
  expect(observer.getCurrentResult().data).toBeUndefined();
  expect(observer.getCurrentResult().isLoading).toBe(true);
  finish({ data: [] });
  await client.fetchQuery(menuDateQuery('2026-11-02'));
  expect(observer.getCurrentResult().data).toBeNull();
  unsubscribe();
  client.clear();
});

test('menu and preparation observers share one pending cycle request', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  let finish!: (value: { id: string }) => void;
  vi.mocked(getMenuCycle).mockReturnValue(new Promise(resolve => { finish = resolve as typeof finish; }));
  const menu = client.fetchQuery(menuCycleQuery('cycle-one'));
  const preparation = client.fetchQuery(menuCycleQuery('cycle-one'));
  expect(getMenuCycle).toHaveBeenCalledTimes(1);
  finish({ id: 'cycle-one' });
  expect(await menu).toEqual(await preparation);
  expect(client.getQueryData(menuCycleQuery('cycle-one').queryKey)).toEqual({ id: 'cycle-one' });
  client.clear();
});

test('switching cycles isolates data and cancels abandoned downloads', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  let signal: AbortSignal | undefined;
  vi.mocked(getMenuCycle).mockImplementation((_id, receivedSignal) => {
    signal = receivedSignal;
    return new Promise(() => {});
  });
  const pending = client.fetchQuery(menuCycleQuery('cycle-one')).catch(() => null);
  await client.cancelQueries({ queryKey: menuCycleQuery('cycle-one').queryKey });
  expect(signal?.aborted).toBe(true);
  expect(client.getQueryData(menuCycleQuery('cycle-two').queryKey)).toBeUndefined();
  await pending;
  client.clear();
});

test('a failed background refresh retains cycle data until retry or logout', async () => {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  vi.mocked(getMenuCycle).mockResolvedValueOnce({ id: 'cycle-one' } as never);
  await client.fetchQuery(menuCycleQuery('cycle-one'));
  vi.mocked(getMenuCycle).mockRejectedValueOnce(new Error('Slow connection'));
  await expect(client.fetchQuery(menuCycleQuery('cycle-one'))).rejects.toThrow('Slow connection');
  expect(client.getQueryData(menuCycleQuery('cycle-one').queryKey)).toEqual({ id: 'cycle-one' });
  expect(client.getQueryState(menuCycleQuery('cycle-one').queryKey)?.status).toBe('error');
  client.clear();
  expect(client.getQueryData(menuCycleQuery('cycle-one').queryKey)).toBeUndefined();
});

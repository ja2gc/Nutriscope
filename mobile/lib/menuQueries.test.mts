import { QueryClient } from '@tanstack/react-query';
import { afterEach, expect, test, vi } from 'vitest';
import { getMenuCycle } from './foodService';
import { menuCycleQuery } from './menuQueries';

vi.mock('./foodService', () => ({ getMenuCycle: vi.fn() }));
afterEach(() => vi.resetAllMocks());

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

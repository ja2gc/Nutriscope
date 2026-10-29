import { queryOptions } from '@tanstack/react-query';
import { getMenuCycle } from './foodService';

export function menuCycleQuery(cycleId: string) {
  return queryOptions({
    queryKey: ['fs-cycle', cycleId],
    queryFn: ({ signal }) => getMenuCycle(cycleId, signal),
  });
}

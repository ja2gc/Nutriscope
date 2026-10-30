import { queryOptions } from '@tanstack/react-query';
import { getMenuCycle, listMenuCycles } from './foodService';
import { addCalendarDays } from './localDate';

export function menuDateQuery(selectedDate: string) {
  return queryOptions({
    queryKey: ['fss-menu-date', selectedDate],
    queryFn: async () => (await listMenuCycles(1, false, selectedDate)).data[0] ?? null,
    placeholderData: (previous) => previous?.week_start_date
      && selectedDate >= previous.week_start_date
      && selectedDate <= addCalendarDays(previous.week_start_date, 6)
      ? previous : undefined,
  });
}

export function menuCycleQuery(cycleId: string) {
  return queryOptions({
    queryKey: ['fs-cycle', cycleId],
    queryFn: ({ signal }) => getMenuCycle(cycleId, signal),
  });
}

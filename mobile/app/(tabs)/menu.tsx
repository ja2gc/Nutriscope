import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { router, type Href, useFocusEffect } from 'expo-router';
import { CalendarDays, ChevronLeft, ChevronRight, Utensils } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { DayPicker } from '../../components/menu/DayPicker';
import { MobilePagination } from '../../components/MobilePagination';
import { DAYS, listMenuCycles, MEALS, MEAL_LABELS, MenuCycle, MenuDay } from '../../lib/foodService';
import { menuCycleQuery, menuDateQuery } from '../../lib/menuQueries';
import { getNextPageParam } from '../../lib/pagination';
import { paginateItems } from '../../lib/pagination';
import { useMobileServerPage } from '../../lib/useMobileServerPage';
import { addCalendarDays, dateFromKey, todayManilaKey } from '../../lib/localDate';

function coversToday(start: string | null): boolean {
  if (!start) return false;
  const today = todayManilaKey();
  return start <= today && today <= addCalendarDays(start, 6);
}

function CycleDetail({ cycleId, selectedDate, onDateChange, onBack }: { cycleId: string; selectedDate: string; onDateChange: (date: string) => void; onBack: () => void }) {
  const [linePage, setLinePage] = useState(1);
  const { data: cycle, isLoading, isError, refetch } = useQuery(menuCycleQuery(cycleId));
  const selectedDay = DAYS[(dateFromKey(selectedDate).getDay() + 6) % 7];
  const selectDate = (date: string) => { setLinePage(1); onDateChange(date); };

  if (isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator color="#087F5B" /></View>;
  if (!cycle) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Utensils color="#DC2626" size={40} /><Text className="mt-3 text-base font-medium text-[#263D35]">Could not load this menu cycle</Text><View className="mt-5 flex-row gap-3"><TouchableOpacity className="min-h-12 justify-center rounded-xl border border-[#D9E3DD] px-5" onPress={onBack}><Text className="font-semibold text-[#263D35]">Back</Text></TouchableOpacity><TouchableOpacity className="min-h-12 justify-center rounded-xl bg-[#087F5B] px-5" onPress={() => refetch()}><Text className="font-semibold text-white">Retry</Text></TouchableOpacity></View></View>;
  const byDay: Record<string, MenuDay[]> = {};
  (cycle.days ?? []).forEach((entry) => { (byDay[entry.day_of_week] ??= []).push(entry); });
  const planned = Number(byDay[selectedDay]?.[0]?.estimate_population ?? 0);
  const lines = paginateItems([...(byDay[selectedDay] ?? [])].sort((a, b) => MEALS.indexOf(a.meal_type) - MEALS.indexOf(b.meal_type) || a.line_order - b.line_order), linePage);

  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ paddingBottom: 32 }}>
    <View className="min-h-14 flex-row items-center gap-2 border-b border-[#E2EAE5] bg-white px-4"><TouchableOpacity onPress={onBack} className="h-12 w-12 items-center justify-center" accessibilityLabel="Back to weekly menus"><ChevronLeft color="#263D35" size={22} /></TouchableOpacity><Text className="flex-1 text-base font-extrabold text-[#16352B]" numberOfLines={1}>{cycle.name}</Text>{coversToday(cycle.week_start_date) && <View className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1"><Text className="text-[10px] font-bold text-emerald-700">This week</Text></View>}</View>
    {isError && <TouchableOpacity onPress={() => { void refetch(); }} className="mx-4 min-h-12 justify-center"><Text className="text-sm text-red-700">Could not refresh this menu. Showing previously loaded data. Tap to retry.</Text></TouchableOpacity>}
    <DayPicker days={DAYS} weekStartDate={cycle.week_start_date} selectedDay={selectedDay} onSelect={(day) => selectDate(addCalendarDays(cycle.week_start_date!, DAYS.indexOf(day)))} onPrevious={() => selectDate(addCalendarDays(selectedDate, -1))} onNext={() => selectDate(addCalendarDays(selectedDate, 1))} />
    {selectedDate > todayManilaKey() && <View className="mx-4 mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3"><Text className="text-sm text-amber-800">This service date is not active yet.</Text></View>}
    <View className="mx-4 mt-4 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-xs font-bold uppercase tracking-widest text-[#6B7F77]">Planned population</Text><Text className="mt-1 text-3xl font-extrabold text-[#087F5B] tabular-nums">{planned}</Text></View>
    <View className="mx-4 mt-3 overflow-hidden rounded-[22px] border border-[#E2EAE5] bg-white">
      {lines.total === 0 ? <Text className="p-6 text-center text-sm text-[#7A8D85]">{cycle.days?.length ? 'No meals planned for this day.' : 'Menu not planned for this week yet.'}</Text> : lines.items.map((entry, index, pageLines) => {
        const name = entry.po_snapshot?.name ?? entry.recipe?.name ?? entry.fs_item?.name ?? 'Meal details';
        return <View key={entry.id} className="border-b border-[#EDF2EF] px-4 py-3">{index === 0 || pageLines[index - 1].meal_type !== entry.meal_type ? <Text className="text-[10px] font-bold uppercase tracking-wider text-[#7A8D85]">{MEAL_LABELS[entry.meal_type]}</Text> : null}<TouchableOpacity onPress={() => router.push({ pathname: '/food-details', params: { cycleId: cycle.id, lineId: entry.id } } as unknown as Href)} className="min-h-12 flex-row items-center py-1"><View className="flex-1"><Text className="text-sm font-bold text-[#263D35]">{name}</Text>{entry.recipe?.portion_label ? <Text className="mt-0.5 text-xs text-[#4D7464]">{entry.recipe.portion_label}</Text> : null}</View><ChevronRight color="#7A8D85" size={18} /></TouchableOpacity></View>;
      })}
      <View className="px-4"><MobilePagination page={lines.page} pages={lines.pages} total={lines.total} onPrevious={() => setLinePage(lines.page - 1)} onNext={() => setLinePage(lines.page + 1)} /></View>
    </View>
  </ScrollView>;
}

export default function MenuScreen() {
  const insets = useSafeAreaInsets();
  const [todayKey, setTodayKey] = useState(todayManilaKey);
  const [selectedDate, setSelectedDate] = useState(todayManilaKey);
  const [showWeeks, setShowWeeks] = useState(false);
  useFocusEffect(useCallback(() => {
    const today = todayManilaKey();
    setTodayKey(today);
    setSelectedDate(today);
    setShowWeeks(false);
  }, []));
  const selectedQuery = useQuery(menuDateQuery(selectedDate));
  const query = useInfiniteQuery({ queryKey: ['fs-cycles'], queryFn: ({ pageParam }) => listMenuCycles(pageParam), initialPageParam: 1, getNextPageParam });
  const weekPager = useMobileServerPage(query.data, query.fetchNextPage);
  const cycles = [...weekPager.items].sort((a: MenuCycle, b: MenuCycle) => Number(coversToday(b.week_start_date)) - Number(coversToday(a.week_start_date)) || (b.week_start_date ?? '').localeCompare(a.week_start_date ?? ''));
  if (!showWeeks && selectedQuery.data) return <CycleDetail cycleId={selectedQuery.data.id} selectedDate={selectedDate} onDateChange={setSelectedDate} onBack={() => setShowWeeks(true)} />;
  if (!showWeeks && selectedQuery.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator size="large" color="#087F5B" /></View>;
  if (!showWeeks && selectedQuery.isError) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Text className="text-center text-sm text-red-700">Could not load the menu for this date.</Text><TouchableOpacity onPress={() => selectedQuery.refetch()} className="mt-4 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6"><Text className="font-bold text-white">Retry</Text></TouchableOpacity></View>;
  if (!showWeeks) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Text className="text-center text-sm text-[#6B7F77]">{selectedDate > todayKey ? 'This service date is not active yet.' : 'Menu not planned for this week yet.'}</Text><View className="mt-4 flex-row gap-3"><TouchableOpacity onPress={() => setSelectedDate(addCalendarDays(selectedDate, -1))} className="min-h-12 min-w-12 items-center justify-center rounded-xl border border-[#D9E3DD]" accessibilityLabel="Previous day"><ChevronLeft color="#087F5B" size={22} /></TouchableOpacity><TouchableOpacity onPress={() => setSelectedDate(addCalendarDays(selectedDate, 1))} className="min-h-12 min-w-12 items-center justify-center rounded-xl border border-[#D9E3DD]" accessibilityLabel="Next day"><ChevronRight color="#087F5B" size={22} /></TouchableOpacity></View><TouchableOpacity onPress={() => setShowWeeks(true)} className="mt-4 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6"><Text className="font-bold text-white">Browse weekly menus</Text></TouchableOpacity></View>;
  if (query.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator size="large" color="#087F5B" /></View>;
  if (query.isError && !query.data) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Utensils color="#DC2626" size={40} /><Text className="mt-3 text-base font-medium text-[#263D35]">Could not load menu cycles</Text><TouchableOpacity className="mt-5 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6" onPress={() => query.refetch()}><Text className="font-semibold text-white">Retry</Text></TouchableOpacity></View>;

  return <FlatList className="bg-[#F4F7F5]" data={cycles} keyExtractor={(cycle) => cycle.id} contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16, flexGrow: 1 }} renderItem={({ item }) => <TouchableOpacity onPress={() => { setSelectedDate(item.week_start_date ?? todayKey); setShowWeeks(false); }} className="mb-3 min-h-16 flex-row items-center rounded-2xl border border-[#E2EAE5] bg-white p-4"><View className="mr-3 h-10 w-10 items-center justify-center rounded-xl bg-[#EAF7F1]"><CalendarDays color="#087F5B" size={20} /></View><View className="flex-1"><Text className="text-sm font-bold text-[#16352B]">{item.name}</Text><Text className="mt-0.5 text-[11px] text-[#7A8D85]">{coversToday(item.week_start_date) ? 'This week' : item.week_start_date ?? 'Date not set'}</Text></View><ChevronRight color="#7A8D85" size={18} /></TouchableOpacity>} ListHeaderComponent={query.isError ? <TouchableOpacity onPress={() => { void query.refetch(); }} className="min-h-12 justify-center"><Text className="text-sm text-red-700">Could not refresh menus. Tap to retry.</Text></TouchableOpacity> : null} ListFooterComponent={<MobilePagination page={weekPager.page} pages={weekPager.pages} total={weekPager.total} onPrevious={weekPager.previous} onNext={() => void weekPager.next()} loading={query.isFetchingNextPage} error={query.isFetchNextPageError} />} ListEmptyComponent={<View className="items-center justify-center py-20"><CalendarDays color="#D1D5DB" size={40} /><Text className="mt-4 text-sm text-[#7A8D85]">No weekly menus yet.</Text></View>} />;
}

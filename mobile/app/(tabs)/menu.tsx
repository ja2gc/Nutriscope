import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { router, type Href } from 'expo-router';
import { CalendarDays, ChevronLeft, ChevronRight, Utensils } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import { ActivityIndicator, FlatList, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { DayPicker } from '../../components/menu/DayPicker';
import { PaginatedListFooter } from '../../components/PaginatedListFooter';
import { DAYS, getMenuCycle, listMenuCycles, MEALS, MEAL_LABELS, MenuCycle, MenuDay } from '../../lib/foodService';
import { flattenUniquePages, getNextPageParam } from '../../lib/pagination';

function CycleDetail({ cycleId, onBack }: { cycleId: string; onBack: () => void }) {
  const [selectedDay, setSelectedDay] = useState('');
  const { data: cycle, isLoading } = useQuery({ queryKey: ['fs-cycle', cycleId], queryFn: () => getMenuCycle(cycleId) });

  useEffect(() => {
    if (!cycle) return;
    const available = DAYS.filter((day) => cycle.days?.some((entry) => entry.day_of_week === day));
    if (selectedDay && available.includes(selectedDay)) return;
    const todayName = new Date().toLocaleDateString('en-US', { weekday: 'long' });
    setSelectedDay(available.includes(todayName) ? todayName : available[0] ?? '');
  }, [cycle, selectedDay]);

  if (isLoading || !cycle) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator color="#087F5B" /></View>;
  const byDay: Record<string, MenuDay[]> = {};
  (cycle.days ?? []).forEach((entry) => { (byDay[entry.day_of_week] ??= []).push(entry); });
  const availableDays = DAYS.filter((day) => byDay[day]?.length);
  const planned = Number(byDay[selectedDay]?.[0]?.estimate_population ?? 0);

  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ paddingBottom: 32 }}>
    <View className="min-h-14 flex-row items-center gap-2 border-b border-[#E2EAE5] bg-white px-4"><TouchableOpacity onPress={onBack} className="h-11 w-11 items-center justify-center" accessibilityLabel="Back to menu cycles"><ChevronLeft color="#263D35" size={22} /></TouchableOpacity><View className="flex-1"><Text className="text-base font-extrabold text-[#16352B]" numberOfLines={1}>{cycle.name}</Text><Text className="text-xs text-[#6B7F77]">Read-only weekly plan</Text></View>{cycle.is_active && <View className="rounded-full border border-emerald-200 bg-emerald-50 px-2 py-1"><Text className="text-[10px] font-bold text-emerald-700">Active</Text></View>}</View>
    {selectedDay && <DayPicker days={availableDays} weekStartDate={cycle.week_start_date} selectedDay={selectedDay} onSelect={setSelectedDay} />}
    <View className="mx-4 mt-4 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-xs font-bold uppercase tracking-widest text-[#6B7F77]">Planned population</Text><Text className="mt-1 text-3xl font-extrabold text-[#087F5B] tabular-nums">{planned}</Text><Text className="mt-1 text-xs text-[#6B7F77]">Actual served population is recorded in Meal Prep.</Text></View>
    <View className="mx-4 mt-3 overflow-hidden rounded-[22px] border border-[#E2EAE5] bg-white">
      {(byDay[selectedDay] ?? []).length === 0 ? <Text className="p-6 text-center text-sm text-[#7A8D85]">No meals planned for this day.</Text> : MEALS.filter((meal) => byDay[selectedDay].some((entry) => entry.meal_type === meal)).map((meal) => {
        const entry = byDay[selectedDay].find((row) => row.meal_type === meal)!;
        const name = entry.po_snapshot?.name ?? entry.recipe?.name ?? entry.fs_item?.name ?? 'Meal details';
        return <TouchableOpacity key={meal} onPress={() => router.push({ pathname: '/food-details', params: { cycleId: cycle.id, day: selectedDay, meal } } as unknown as Href)} className="min-h-16 flex-row items-center border-b border-[#EDF2EF] px-4 py-2"><View className="flex-1"><Text className="text-[10px] font-bold uppercase tracking-wider text-[#7A8D85]">{MEAL_LABELS[meal]}</Text><Text className="mt-1 text-sm font-bold text-[#263D35]">{name}</Text></View><ChevronRight color="#7A8D85" size={18} /></TouchableOpacity>;
      })}
    </View>
  </ScrollView>;
}

export default function MenuScreen() {
  const insets = useSafeAreaInsets();
  const [openId, setOpenId] = useState<string | null>(null);
  const [autoOpened, setAutoOpened] = useState(false);
  const query = useInfiniteQuery({ queryKey: ['fs-cycles'], queryFn: ({ pageParam }) => listMenuCycles(pageParam), initialPageParam: 1, getNextPageParam });
  const cycles = flattenUniquePages(query.data?.pages).sort((a: MenuCycle, b: MenuCycle) => Number(b.is_active) - Number(a.is_active));

  useEffect(() => {
    if (autoOpened || openId || cycles.length === 0) return;
    const active = cycles.find((cycle) => cycle.is_active);
    if (active) { setAutoOpened(true); setOpenId(active.id); }
  }, [autoOpened, cycles, openId]);
  if (openId) return <CycleDetail cycleId={openId} onBack={() => setOpenId(null)} />;
  if (query.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator size="large" color="#087F5B" /></View>;
  if (query.isError) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Utensils color="#DC2626" size={40} /><Text className="mt-3 text-base font-medium text-[#263D35]">Could not load menu cycles</Text><TouchableOpacity className="mt-5 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6" onPress={() => query.refetch()}><Text className="font-semibold text-white">Retry</Text></TouchableOpacity></View>;

  return <FlatList className="bg-[#F4F7F5]" data={cycles} keyExtractor={(cycle) => cycle.id} contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16, flexGrow: 1 }} renderItem={({ item }) => <TouchableOpacity onPress={() => setOpenId(item.id)} className="mb-3 min-h-16 flex-row items-center rounded-2xl border border-[#E2EAE5] bg-white p-4"><View className="mr-3 h-10 w-10 items-center justify-center rounded-xl bg-[#EAF7F1]"><CalendarDays color="#087F5B" size={20} /></View><View className="flex-1"><Text className="text-sm font-bold text-[#16352B]">{item.name}</Text><Text className="mt-0.5 text-[11px] capitalize text-[#7A8D85]">{item.is_active ? 'Active · whole week' : item.status}</Text></View><ChevronRight color="#7A8D85" size={18} /></TouchableOpacity>} onEndReached={() => { if (query.hasNextPage && !query.isFetchingNextPage) void query.fetchNextPage(); }} onEndReachedThreshold={0.4} ListFooterComponent={<PaginatedListFooter loading={query.isFetchingNextPage} error={query.isFetchNextPageError} onRetry={() => void query.fetchNextPage()} />} ListEmptyComponent={<View className="items-center justify-center py-20"><CalendarDays color="#D1D5DB" size={40} /><Text className="mt-4 text-sm text-[#7A8D85]">No menu cycles yet.</Text></View>} />;
}

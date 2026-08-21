import DateTimePicker, { DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router, type Href } from 'expo-router';
import { AlertCircle, CalendarDays, ChefHat, ChevronRight, RotateCcw, Save, Users } from 'lucide-react-native';
import { useMemo, useState } from 'react';
import { ActivityIndicator, Platform, RefreshControl, ScrollView, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { dateFromKey, localDateKey, readableLocalDate } from '../../lib/localDate';
import { getMenuCycle, listMealPrep, listMenuCycles, MEAL_LABELS, setServedPopulation } from '../../lib/foodService';

function addDays(value: string, days: number): string {
  const date = dateFromKey(value);
  date.setDate(date.getDate() + days);
  return localDateKey(date);
}

export default function MealPrepScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const today = localDateKey();
  const [selectedDate, setSelectedDate] = useState(today);
  const [showPicker, setShowPicker] = useState(false);
  const [served, setServed] = useState('');
  const [message, setMessage] = useState<{ error: boolean; text: string } | null>(null);

  const activeQuery = useQuery({
    queryKey: ['fss-active-cycle'],
    queryFn: async () => (await listMenuCycles(1, true)).data.find((cycle) => cycle.is_active) ?? null,
    staleTime: 120_000,
  });
  const cycleQuery = useQuery({
    queryKey: ['fss-meal-prep-cycle', activeQuery.data?.id],
    queryFn: () => getMenuCycle(activeQuery.data!.id),
    enabled: Boolean(activeQuery.data?.id),
  });
  const logsQuery = useQuery({
    queryKey: ['fs-mealprep', activeQuery.data?.id],
    queryFn: () => listMealPrep(activeQuery.data!.id),
    enabled: Boolean(activeQuery.data?.id),
  });

  const cycle = cycleQuery.data;
  const start = cycle?.week_start_date ?? today;
  const end = cycle?.week_start_date ? addDays(cycle.week_start_date, 6) : today;
  const maxDate = end < today ? end : today;
  const dateInCycle = selectedDate >= start && selectedDate <= end;
  const weekday = dateFromKey(selectedDate).toLocaleDateString('en-US', { weekday: 'long' });
  const meals = useMemo(() => dateInCycle ? (cycle?.days ?? []).filter((row) => row.day_of_week === weekday) : [], [cycle?.days, dateInCycle, weekday]);
  const existing = (logsQuery.data ?? []).find((log) => log.service_date === selectedDate);

  const saveMutation = useMutation({
    mutationFn: () => setServedPopulation(cycle!.id, selectedDate, Number.parseInt(served, 10)),
    onSuccess: async () => {
      setMessage({ error: false, text: `Actual served population recorded for ${readableLocalDate(selectedDate)}.` });
      setServed('');
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['fs-mealprep', cycle?.id] }),
        queryClient.invalidateQueries({ queryKey: ['fss-dashboard'] }),
        queryClient.invalidateQueries({ queryKey: ['fss-purchase-orders'] }),
      ]);
    },
    onError: (error: any) => setMessage({ error: true, text: error?.response?.data?.message ?? Object.values(error?.response?.data?.errors ?? {}).flat()[0] ?? 'Could not save actual served population.' }),
  });

  const submit = () => {
    setMessage(null);
    const value = Number.parseInt(served, 10);
    if (!Number.isFinite(value) || value < 0) { setMessage({ error: true, text: 'Enter an actual served population of 0 or greater.' }); return; }
    if (!cycle || meals.length === 0) { setMessage({ error: true, text: 'This date has no planned meals in the active cycle.' }); return; }
    saveMutation.mutate();
  };

  const selectDate = (event: DateTimePickerEvent, value?: Date) => {
    if (Platform.OS === 'android') setShowPicker(false);
    if (event.type !== 'dismissed' && value) { setSelectedDate(localDateKey(value)); setServed(''); setMessage(null); }
  };
  const goToday = () => { setSelectedDate(localDateKey()); setShowPicker(false); setServed(''); setMessage(null); };
  const loading = activeQuery.isLoading || cycleQuery.isLoading;

  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 28 }} refreshControl={<RefreshControl refreshing={activeQuery.isRefetching || cycleQuery.isRefetching} onRefresh={() => { void activeQuery.refetch(); void cycleQuery.refetch(); void logsQuery.refetch(); }} tintColor="#087F5B" />}>
    <View className="rounded-[24px] bg-[#0B6B4B] p-5"><ChefHat color="#D1FAE5" size={22} /><Text className="mt-3 text-2xl font-extrabold text-white">Meal preparation</Text><Text className="mt-1 text-sm leading-5 text-emerald-100">Review the planned meals, then record the actual patient population served.</Text></View>

    <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4">
      <Text className="text-xs font-bold uppercase tracking-widest text-[#6B7F77]">Service date</Text>
      <View className="mt-2 flex-row gap-2"><TouchableOpacity onPress={() => setShowPicker(true)} disabled={!cycle} className="min-h-12 flex-1 flex-row items-center gap-3 rounded-xl border border-[#D9E3DD] bg-[#FAFCFB] px-3"><CalendarDays color="#087F5B" size={20} /><Text className="flex-1 text-sm font-bold text-[#263D35]">{readableLocalDate(selectedDate)}</Text></TouchableOpacity>{selectedDate !== today && <TouchableOpacity onPress={goToday} className="min-h-12 flex-row items-center gap-1.5 rounded-xl bg-[#E7F7F0] px-3"><RotateCcw color="#087F5B" size={17} /><Text className="text-sm font-bold text-[#087F5B]">Today</Text></TouchableOpacity>}</View>
      {showPicker && cycle && <DateTimePicker value={dateFromKey(dateInCycle ? selectedDate : maxDate)} mode="date" minimumDate={dateFromKey(start)} maximumDate={dateFromKey(maxDate)} onChange={selectDate} />}
    </View>

    {loading ? <View className="py-16"><ActivityIndicator color="#087F5B" /></View> : !cycle ? <View className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"><Text className="text-sm text-amber-800">No active menu cycle. Contact RND.</Text></View> : !dateInCycle ? <View className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-4"><Text className="text-sm text-amber-800">Today is outside the active menu cycle. Choose a planned date or contact RND.</Text></View> : <>
      <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-base font-extrabold text-[#16352B]">{weekday}'s meals</Text><Text className="mt-1 text-xs text-[#6B7F77]">{cycle.name}</Text>{meals.length === 0 ? <Text className="py-6 text-center text-sm text-[#7A8D85]">No meals planned for this date.</Text> : meals.map((meal) => <TouchableOpacity key={`${meal.day_of_week}-${meal.meal_type}`} onPress={() => router.push({ pathname: '/food-details', params: { cycleId: cycle.id, day: meal.day_of_week, meal: meal.meal_type } } as unknown as Href)} className="min-h-14 flex-row items-center border-b border-[#EDF2EF] py-2"><View className="flex-1"><Text className="text-xs font-bold uppercase tracking-wider text-[#7A8D85]">{MEAL_LABELS[meal.meal_type] ?? meal.meal_type}</Text><Text className="mt-0.5 text-sm font-bold text-[#263D35]">{meal.po_snapshot?.name ?? meal.recipe?.name ?? meal.fs_item?.name ?? 'Meal details'}</Text></View><ChevronRight color="#7A8D85" size={18} /></TouchableOpacity>)}</View>

      <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><View className="flex-row items-center gap-2"><Users color="#087F5B" size={19} /><Text className="text-base font-extrabold text-[#16352B]">Actual population served</Text></View>{existing?.served_population != null && <Text className="mt-2 text-xs text-[#087F5B]">Currently recorded: {existing.served_population}</Text>}<TextInput value={served} onChangeText={(value) => { setServed(value.replace(/[^0-9]/g, '')); setMessage(null); }} placeholder={existing?.served_population != null ? String(existing.served_population) : 'Enter actual headcount'} placeholderTextColor="#9AA9A2" keyboardType="number-pad" className="mt-3 min-h-12 rounded-xl border border-[#D9E3DD] bg-[#FAFCFB] px-3.5 text-[#16352B]" /><TouchableOpacity onPress={submit} disabled={saveMutation.isPending || meals.length === 0} className={`mt-3 min-h-12 flex-row items-center justify-center gap-2 rounded-xl ${saveMutation.isPending || meals.length === 0 ? 'bg-[#8FC8B5]' : 'bg-[#087F5B]'}`}>{saveMutation.isPending ? <ActivityIndicator color="#fff" /> : <Save color="#fff" size={18} />}<Text className="font-extrabold text-white">{existing ? 'Update actual served' : 'Record actual served'}</Text></TouchableOpacity></View>
    </>}

    {message && <View className={`mt-3 flex-row gap-2 rounded-xl border p-3 ${message.error ? 'border-red-200 bg-red-50' : 'border-emerald-200 bg-emerald-50'}`}><AlertCircle color={message.error ? '#DC2626' : '#087F5B'} size={18} /><Text className={`flex-1 text-sm ${message.error ? 'text-red-700' : 'text-emerald-800'}`}>{message.text}</Text></View>}
  </ScrollView>;
}

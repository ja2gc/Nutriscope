import DateTimePicker, { DateTimePickerEvent } from '@react-native-community/datetimepicker';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarDays, Check, CheckCircle2, ClipboardCheck, FileText, RotateCcw, Users } from 'lucide-react-native';
import { useCallback, useMemo, useState } from 'react';
import { ActivityIndicator, Platform, Pressable, RefreshControl, ScrollView, Switch, Text, TextInput, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';
import { dateFromKey, localDateKey, readableLocalDate } from '../../lib/localDate';
import ReportsScreen from '../reports';

const TASKS = [
  ['helped_food_prep', 'Helped prepare food'],
  ['stored_supplies', 'Stored food supplies properly'],
  ['collected_diet_list', 'Collected ward diet lists'],
  ['apportioned_food', 'Apportioned and distributed meals'],
  ['cleaned_utensils', 'Cleaned and returned utensils'],
  ['assistant_cook', 'Worked as assistant cook'],
  ['maintained_cleanliness', 'Checked kitchen and cold-storage cleanliness'],
] as const;
type TaskKey = (typeof TASKS)[number][0];
type TaskFlags = Record<TaskKey, boolean>;
type DailyRow = { id: string; ward: string; population: number; off_duty: boolean };

const emptyTasks = (): TaskFlags => ({
  helped_food_prep: false, stored_supplies: false, collected_diet_list: false,
  apportioned_food: false, cleaned_utensils: false, assistant_cook: false, maintained_cleanliness: false,
});

async function getDailyRows(date: string): Promise<DailyRow[]> {
  const res = await api.get<{ data: DailyRow[] }>('/api/fss/diet-list-counts', { params: { from: date, to: date } });
  return res.data.data;
}

export default function AccomplishmentsScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const today = localDateKey();
  const [section, setSection] = useState<'log' | 'reports'>('log');
  const [selectedDate, setSelectedDate] = useState(today);
  const [showPicker, setShowPicker] = useState(false);
  const [ward, setWard] = useState('');
  const [meals, setMeals] = useState('');
  const [offDuty, setOffDuty] = useState(false);
  const [tasks, setTasks] = useState<TaskFlags>(emptyTasks);
  const [message, setMessage] = useState<{ kind: 'error' | 'success'; text: string } | null>(null);
  const [refreshing, setRefreshing] = useState(false);

  const rowsQuery = useQuery({ queryKey: ['fss-diet-list-day', selectedDate], queryFn: () => getDailyRows(selectedDate), staleTime: 30_000 });
  const total = useMemo(() => (rowsQuery.data ?? []).reduce((sum, row) => sum + Number(row.population), 0), [rowsQuery.data]);

  const resetForm = () => { setWard(''); setMeals(''); setOffDuty(false); setTasks(emptyTasks()); };
  const saveMutation = useMutation({
    mutationFn: async () => {
      const population = offDuty ? 0 : Number.parseInt(meals, 10);
      return api.post('/api/fss/diet-list-counts', {
        service_date: selectedDate,
        ward: offDuty ? 'Off duty' : ward.trim(),
        population,
        off_duty: offDuty,
        ...tasks,
        apportioned_food: offDuty ? false : tasks.apportioned_food || population > 0,
      });
    },
    onSuccess: async () => {
      resetForm();
      setMessage({ kind: 'success', text: `Saved for ${readableLocalDate(selectedDate)}.` });
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['fss-diet-list-day', selectedDate] }),
        queryClient.invalidateQueries({ queryKey: ['fss-dashboard'] }),
        queryClient.invalidateQueries({ queryKey: ['fss-reports'] }),
      ]);
    },
    onError: (error: any) => {
      const errors = error?.response?.data?.errors;
      const first = errors ? Object.values(errors).flat()[0] : null;
      setMessage({ kind: 'error', text: String(first ?? error?.response?.data?.message ?? 'Could not save the entry.') });
    },
  });

  const submit = () => {
    setMessage(null);
    if (!offDuty && !ward.trim()) { setMessage({ kind: 'error', text: 'Enter the ward before saving.' }); return; }
    const population = Number.parseInt(meals, 10);
    if (!offDuty && (!Number.isFinite(population) || population < 0)) { setMessage({ kind: 'error', text: 'Distributed meals must be 0 or greater.' }); return; }
    saveMutation.mutate();
  };

  const selectDate = (event: DateTimePickerEvent, date?: Date) => {
    if (Platform.OS === 'android') setShowPicker(false);
    if (event.type !== 'dismissed' && date) { setSelectedDate(localDateKey(date)); setMessage(null); }
  };
  const goToday = () => { setSelectedDate(localDateKey()); setShowPicker(false); setMessage(null); };
  const refresh = useCallback(async () => {
    setRefreshing(true); await rowsQuery.refetch(); setRefreshing(false);
  }, [rowsQuery]);

  return <View className="flex-1 bg-[#F4F7F5]">
    <View className="mx-4 mt-3 flex-row rounded-2xl bg-[#E5ECE8] p-1" accessibilityRole="tablist">
      {([['log', 'Daily Log', ClipboardCheck], ['reports', 'My Reports', FileText]] as const).map(([key, label, Icon]) =>
        <TouchableOpacity key={key} onPress={() => setSection(key)} className={`min-h-11 flex-1 flex-row items-center justify-center gap-2 rounded-xl ${section === key ? 'bg-white' : ''}`} accessibilityRole="tab" accessibilityState={{ selected: section === key }}>
          <Icon color={section === key ? '#087F5B' : '#6B7F77'} size={17} /><Text className={`text-sm font-bold ${section === key ? 'text-[#087F5B]' : 'text-[#6B7F77]'}`}>{label}</Text>
        </TouchableOpacity>)}
    </View>

    {section === 'reports' ? <ReportsScreen embedded /> : <ScrollView className="flex-1" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 28 }} keyboardShouldPersistTaps="handled" refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor="#087F5B" />}>
      <View className="rounded-[22px] bg-white border border-[#E2EAE5] p-4">
        <Text className="text-xs font-bold uppercase tracking-widest text-[#6B7F77]">Log date</Text>
        <View className="mt-2 flex-row gap-2">
          <TouchableOpacity onPress={() => setShowPicker(true)} className="min-h-12 flex-1 flex-row items-center gap-3 rounded-xl border border-[#D9E3DD] bg-[#FAFCFB] px-3" accessibilityLabel={`Select log date, ${readableLocalDate(selectedDate)}`}>
            <CalendarDays color="#087F5B" size={20} /><Text className="flex-1 text-sm font-bold text-[#263D35]">{readableLocalDate(selectedDate)}</Text>
          </TouchableOpacity>
          {selectedDate !== today && <TouchableOpacity onPress={goToday} className="min-h-12 flex-row items-center gap-1.5 rounded-xl bg-[#E7F7F0] px-3" accessibilityLabel="Return to today's daily log"><RotateCcw color="#087F5B" size={17} /><Text className="text-sm font-bold text-[#087F5B]">Today</Text></TouchableOpacity>}
        </View>
        {selectedDate !== today && <Text className="mt-2 text-xs text-amber-700">Backfilling a missed daily log.</Text>}
        {showPicker && <DateTimePicker value={dateFromKey(selectedDate)} mode="date" maximumDate={dateFromKey(today)} onChange={selectDate} />}
      </View>

      <View className="mt-3 rounded-[22px] bg-[#0B6B4B] p-4">
        <View className="flex-row items-center justify-between"><View><Text className="text-emerald-100 text-xs font-bold uppercase tracking-widest">Meals logged</Text><Text className="mt-1 text-3xl font-extrabold text-white tabular-nums">{total}</Text></View><Users color="#D1FAE5" size={28} /></View>
        {(rowsQuery.data?.length ?? 0) > 0 && <Text className="mt-2 text-xs text-emerald-100">{rowsQuery.data!.map((row) => row.off_duty ? 'Off duty' : `${row.ward}: ${row.population}`).join(' · ')}</Text>}
      </View>

      <View className="rounded-[22px] bg-white border border-[#E2EAE5] p-4 mt-3">
        <Text className="text-base font-extrabold text-[#16352B]">Service details</Text>
        <TextInput value={ward} onChangeText={(value) => { setWard(value); setMessage(null); }} editable={!offDuty} placeholder="Ward, e.g. Ward A" placeholderTextColor="#9AA9A2" className="mt-3 min-h-12 border border-[#D9E3DD] bg-[#FAFCFB] rounded-xl px-3.5 text-[#16352B]" />
        <TextInput value={meals} onChangeText={(value) => { setMeals(value.replace(/[^0-9]/g, '')); setMessage(null); }} editable={!offDuty} placeholder="Meals distributed" placeholderTextColor="#9AA9A2" keyboardType="number-pad" className="mt-3 min-h-12 border border-[#D9E3DD] bg-[#FAFCFB] rounded-xl px-3.5 text-[#16352B] tabular-nums" />
        <View className="flex-row items-center justify-between mt-4 pt-4 border-t border-[#EDF2EF]"><View className="flex-1 pr-4"><Text className="text-sm font-bold text-[#263D35]">Off duty / absent</Text><Text className="text-xs text-[#7A8D85] mt-0.5">Records X for this date.</Text></View><Switch value={offDuty} onValueChange={(value) => { setOffDuty(value); if (value) { setWard(''); setMeals(''); setTasks(emptyTasks()); } }} trackColor={{ false: '#D8E0DC', true: '#A7E8CE' }} thumbColor={offDuty ? '#087F5B' : '#74857D'} /></View>
      </View>

      {!offDuty && <View className="rounded-[22px] bg-white border border-[#E2EAE5] p-4 mt-3"><Text className="text-base font-extrabold text-[#16352B]">Tasks completed</Text>{TASKS.map(([key, label]) => <Pressable key={key} onPress={() => setTasks((current) => ({ ...current, [key]: !current[key] }))} className="min-h-12 flex-row items-center gap-3 border-b border-[#F0F3F1]" accessibilityRole="checkbox" accessibilityState={{ checked: tasks[key] }}><View className={`h-6 w-6 rounded-lg items-center justify-center ${tasks[key] ? 'bg-[#087F5B]' : 'bg-[#F4F7F5] border border-[#CEDAD3]'}`}>{tasks[key] && <Check color="#FFFFFF" size={15} strokeWidth={3} />}</View><Text className="flex-1 text-sm text-[#30483F] leading-5">{label}</Text></Pressable>)}</View>}

      {message && <View className={`mt-3 flex-row gap-2 rounded-xl border p-3 ${message.kind === 'success' ? 'border-emerald-200 bg-emerald-50' : 'border-red-200 bg-red-50'}`}><CheckCircle2 color={message.kind === 'success' ? '#087F5B' : '#DC2626'} size={18} /><Text className={`flex-1 text-sm ${message.kind === 'success' ? 'text-emerald-800' : 'text-red-700'}`}>{message.text}</Text></View>}
      <TouchableOpacity onPress={submit} disabled={saveMutation.isPending} className={`mt-4 min-h-12 rounded-2xl flex-row items-center justify-center gap-2 ${saveMutation.isPending ? 'bg-[#69B99D]' : 'bg-[#087F5B]'}`} accessibilityRole="button">{saveMutation.isPending ? <ActivityIndicator color="#FFFFFF" /> : <ClipboardCheck color="#FFFFFF" size={19} />}<Text className="text-white font-extrabold">{saveMutation.isPending ? 'Saving…' : `Save ${selectedDate === today ? "today's" : 'past'} log`}</Text></TouchableOpacity>
    </ScrollView>}
  </View>;
}

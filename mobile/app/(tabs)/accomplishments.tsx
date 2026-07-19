import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { AlertCircle, Check, CheckCircle2, ChevronRight, ClipboardCheck, FileText, Users } from 'lucide-react-native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  RefreshControl,
  ScrollView,
  Switch,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';

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

const emptyTasks = (): TaskFlags => ({
  helped_food_prep: false,
  stored_supplies: false,
  collected_diet_list: false,
  apportioned_food: false,
  cleaned_utensils: false,
  assistant_cook: false,
  maintained_cleanliness: false,
});

const today = () => new Date().toISOString().slice(0, 10);

async function getActiveCycleId(): Promise<number | undefined> {
  const res = await api.get<{ data: { id: number; is_active: boolean }[] }>('/api/fss/menu-cycles', {
    params: { active: 1, per_page: 1 },
  });
  return res.data.data.find((cycle) => cycle.is_active)?.id;
}

async function getTodayTotal(): Promise<number> {
  const res = await api.get<{ data: { population: number }[] }>('/api/fss/diet-list-counts', {
    params: { from: today(), to: today() },
  });
  return res.data.data.reduce((sum, row) => sum + Number(row.population), 0);
}

export default function AccomplishmentsScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const [ward, setWard] = useState('');
  const [meals, setMeals] = useState('');
  const [offDuty, setOffDuty] = useState(false);
  const [tasks, setTasks] = useState<TaskFlags>(emptyTasks);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);
  const [refreshing, setRefreshing] = useState(false);

  const cycleQuery = useQuery({ queryKey: ['fss-active-cycle-id'], queryFn: getActiveCycleId, staleTime: 120_000 });
  const totalQuery = useQuery({ queryKey: ['fss-diet-list-today-total'], queryFn: getTodayTotal, staleTime: 30_000 });

  const saveMutation = useMutation({
    mutationFn: async () => {
      const population = offDuty ? 0 : Number.parseInt(meals, 10);
      return api.post('/api/fss/diet-list-counts', {
        service_date: today(),
        ward: offDuty ? ward.trim() || 'Absent' : ward.trim(),
        population,
        off_duty: offDuty,
        ...tasks,
        apportioned_food: offDuty ? false : tasks.apportioned_food || population > 0,
        ...(cycleQuery.data ? { menu_cycle_id: cycleQuery.data } : {}),
      });
    },
    onSuccess: async () => {
      setWard('');
      setMeals('');
      setOffDuty(false);
      setTasks(emptyTasks());
      setError(null);
      setSaved(true);
      await Promise.all([
        queryClient.invalidateQueries({ queryKey: ['fss-diet-list-today-total'] }),
        queryClient.invalidateQueries({ queryKey: ['fss-dashboard'] }),
        queryClient.invalidateQueries({ queryKey: ['reports'] }),
      ]);
      setTimeout(() => setSaved(false), 3500);
    },
  });

  const submit = () => {
    setError(null);
    setSaved(false);
    if (!offDuty && !ward.trim()) {
      setError('Enter the ward before saving.');
      return;
    }
    const population = Number.parseInt(meals, 10);
    if (!offDuty && (!Number.isFinite(population) || population < 0)) {
      setError('Distributed meals must be 0 or greater.');
      return;
    }
    saveMutation.mutate();
  };

  const refresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([cycleQuery.refetch(), totalQuery.refetch()]);
    setRefreshing(false);
  }, [cycleQuery, totalQuery]);

  return (
    <ScrollView
      className="flex-1 bg-[#F4F7F5]"
      contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 28 }}
      keyboardShouldPersistTaps="handled"
      refreshControl={<RefreshControl refreshing={refreshing} onRefresh={refresh} tintColor="#087F5B" />}
    >
      <View className="rounded-[24px] bg-[#0B6B4B] p-5 overflow-hidden">
        <View className="h-28 w-28 rounded-full bg-white/10 absolute -right-8 -top-8" />
        <View className="flex-row items-center gap-2">
          <ClipboardCheck color="#D1FAE5" size={20} />
          <Text className="text-emerald-100 text-xs font-bold uppercase tracking-widest">Daily log</Text>
        </View>
        <Text className="text-white text-2xl font-extrabold mt-3">Record today’s work</Text>
        <Text className="text-emerald-100 text-sm mt-1 leading-5">One focused entry for duties and meals distributed.</Text>
      </View>

      <View className="flex-row gap-3 mt-4">
        <View className="flex-1 rounded-2xl bg-white border border-[#E2EAE5] p-4">
          <Users color="#087F5B" size={19} />
          <Text className="text-3xl font-extrabold text-[#16352B] mt-2 tabular-nums">{totalQuery.data ?? 0}</Text>
          <Text className="text-xs text-[#6B7F77] mt-1">Meals logged today</Text>
        </View>
        <TouchableOpacity
          onPress={() => router.push('/reports')}
          className="flex-1 rounded-2xl bg-[#FFF8ED] border border-[#F4DEC0] p-4"
          accessibilityRole="button"
          accessibilityLabel="Open my accomplishment reports"
        >
          <View className="flex-row items-center justify-between">
            <FileText color="#B66318" size={19} />
            <ChevronRight color="#B66318" size={18} />
          </View>
          <Text className="text-sm font-bold text-[#6F3A10] mt-3">My reports</Text>
          <Text className="text-xs text-[#97643A] mt-1">View only your generated files</Text>
        </TouchableOpacity>
      </View>

      <View className="rounded-[22px] bg-white border border-[#E2EAE5] p-4 mt-4">
        <Text className="text-base font-extrabold text-[#16352B]">Service details</Text>
        <Text className="text-xs text-[#6B7F77] mt-1 mb-4">Required unless you were off duty.</Text>

        <Text className="text-xs font-bold text-[#53675F] mb-1.5">Ward</Text>
        <TextInput
          value={ward}
          onChangeText={(value) => { setWard(value); setError(null); }}
          placeholder="Example: Ward A"
          placeholderTextColor="#9AA9A2"
          className="border border-[#D9E3DD] bg-[#FAFCFB] rounded-xl px-3.5 py-3 text-[#16352B]"
        />

        <Text className="text-xs font-bold text-[#53675F] mt-4 mb-1.5">Meals distributed</Text>
        <TextInput
          value={meals}
          onChangeText={(value) => { setMeals(value.replace(/[^0-9]/g, '')); setError(null); }}
          placeholder="0"
          placeholderTextColor="#9AA9A2"
          keyboardType="number-pad"
          className="border border-[#D9E3DD] bg-[#FAFCFB] rounded-xl px-3.5 py-3 text-[#16352B] tabular-nums"
        />

        <View className="flex-row items-center justify-between mt-4 pt-4 border-t border-[#EDF2EF]">
          <View className="flex-1 pr-4">
            <Text className="text-sm font-bold text-[#263D35]">Off duty / absent</Text>
            <Text className="text-xs text-[#7A8D85] mt-0.5">Records an X for today.</Text>
          </View>
          <Switch value={offDuty} onValueChange={setOffDuty} trackColor={{ false: '#D8E0DC', true: '#A7E8CE' }} thumbColor={offDuty ? '#087F5B' : '#74857D'} />
        </View>
      </View>

      <View className="rounded-[22px] bg-white border border-[#E2EAE5] p-4 mt-4">
        <Text className="text-base font-extrabold text-[#16352B]">Tasks completed</Text>
        <Text className="text-xs text-[#6B7F77] mt-1 mb-3">Tap every duty you completed today.</Text>
        {TASKS.map(([key, label]) => (
          <Pressable
            key={key}
            onPress={() => setTasks((current) => ({ ...current, [key]: !current[key] }))}
            className="flex-row items-center gap-3 py-3 border-b border-[#F0F3F1]"
            accessibilityRole="checkbox"
            accessibilityState={{ checked: tasks[key] }}
          >
            <View className={`h-6 w-6 rounded-lg items-center justify-center ${tasks[key] ? 'bg-[#087F5B]' : 'bg-[#F4F7F5] border border-[#CEDAD3]'}`}>
              {tasks[key] && <Check color="#FFFFFF" size={15} strokeWidth={3} />}
            </View>
            <Text className="flex-1 text-sm text-[#30483F] leading-5">{label}</Text>
          </Pressable>
        ))}
      </View>

      {(error || saveMutation.isError) && (
        <View className="flex-row gap-2 mt-4 rounded-xl border border-red-200 bg-red-50 p-3">
          <AlertCircle color="#DC2626" size={18} />
          <Text className="flex-1 text-sm text-red-700">{error ?? 'Could not save the entry. Please retry.'}</Text>
        </View>
      )}
      {saved && (
        <View className="flex-row gap-2 mt-4 rounded-xl border border-emerald-200 bg-emerald-50 p-3">
          <CheckCircle2 color="#087F5B" size={18} />
          <Text className="flex-1 text-sm font-semibold text-emerald-800">Accomplishment saved.</Text>
        </View>
      )}

      <TouchableOpacity
        onPress={submit}
        disabled={saveMutation.isPending}
        className={`mt-4 min-h-12 rounded-2xl flex-row items-center justify-center gap-2 ${saveMutation.isPending ? 'bg-[#69B99D]' : 'bg-[#087F5B]'}`}
        accessibilityRole="button"
      >
        {saveMutation.isPending ? <ActivityIndicator color="#FFFFFF" /> : <ClipboardCheck color="#FFFFFF" size={19} />}
        <Text className="text-white font-extrabold">{saveMutation.isPending ? 'Saving…' : 'Save accomplishment'}</Text>
      </TouchableOpacity>
    </ScrollView>
  );
}

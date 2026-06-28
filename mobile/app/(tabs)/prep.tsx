import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AlertCircle,
  CalendarDays,
  CheckCircle2,
  ChefHat,
  ClipboardCheck,
  FileText,
  TriangleAlert,
} from 'lucide-react-native';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Modal,
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

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface MenuCycle {
  id: number;
  name: string;
  is_active: boolean;
  status: string;
}

interface ServiceRow {
  meal_type: string;
  name: string;
  prepped: boolean;
  has_shortfall: boolean;
}

interface DashboardData {
  today_service: ServiceRow[];
}

interface DietListCount {
  id: number;
  ward: string;
  population: number;
  service_date: string;
}

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------

const today = () => new Date().toISOString().slice(0, 10);

async function fetchActiveCycle(): Promise<MenuCycle | null> {
  const res = await api.get<{ data: MenuCycle[] }>('/api/fss/menu-cycles');
  const cycles: MenuCycle[] = res.data.data;
  return cycles.find((c) => c.is_active) ?? null;
}

async function fetchTodayService(): Promise<ServiceRow[]> {
  const res = await api.get<{ data: DashboardData }>('/api/fss/dashboard/summary');
  return res.data.data.today_service ?? [];
}

async function fetchTodayDietCounts(): Promise<DietListCount[]> {
  const date = today();
  const res = await api.get<{ data: DietListCount[] }>('/api/fss/diet-list-counts', {
    params: { from: date, to: date },
  });
  return res.data.data;
}

// ---------------------------------------------------------------------------
// Section A — Meal Prep
// ---------------------------------------------------------------------------

function MealPrepSection() {
  const qc = useQueryClient();
  const [showShortfallModal, setShowShortfallModal] = useState(false);
  const [shortfallMessage, setShortfallMessage] = useState('');
  const [pendingCycleId, setPendingCycleId] = useState<number | null>(null);

  const { data: activeCycle, isLoading: cycleLoading } = useQuery({
    queryKey: ['fss-active-cycle'],
    queryFn: fetchActiveCycle,
    staleTime: 120_000,
  });

  const {
    data: service,
    isLoading: serviceLoading,
    isError: serviceError,
    refetch: refetchService,
    isFetching: serviceFetching,
  } = useQuery({
    queryKey: ['fss-dashboard'],
    queryFn: fetchTodayService,
    staleTime: 60_000,
  });

  const isLoading = cycleLoading || serviceLoading;

  const markServedMutation = useMutation({
    mutationFn: async ({ cycleId, allowShortfall }: { cycleId: number; allowShortfall: boolean }) => {
      const res = await api.post(`/api/fss/menu-cycles/${cycleId}/complete-day`, {
        service_date: today(),
        allow_shortfall: allowShortfall,
      });
      return res.data;
    },
    onSuccess: () => {
      setShowShortfallModal(false);
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      qc.invalidateQueries({ queryKey: ['fss-active-cycle'] });
    },
    onError: (err: unknown) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const axiosErr = err as any;
      const status = axiosErr?.response?.status;
      const message: string = axiosErr?.response?.data?.message ?? 'Unknown error';

      if (status === 422 && message.toLowerCase().includes('insufficient stock')) {
        setShortfallMessage(message);
        setPendingCycleId(activeCycle?.id ?? null);
        setShowShortfallModal(true);
      }
    },
  });

  const handleMarkServed = () => {
    if (!activeCycle) return;
    markServedMutation.mutate({ cycleId: activeCycle.id, allowShortfall: false });
  };

  const handleProceedWithShortfall = () => {
    if (!pendingCycleId) return;
    markServedMutation.mutate({ cycleId: pendingCycleId, allowShortfall: true });
  };

  return (
    <View className="px-4 mt-4">
      <View className="flex-row items-center gap-2 mb-3">
        <ChefHat color="#059669" size={20} />
        <Text className="text-base font-semibold text-gray-800">Meal Prep — Today's Service</Text>
      </View>

      {isLoading ? (
        <View className="bg-white rounded-xl border border-gray-100 py-8 items-center">
          <ActivityIndicator color="#059669" />
        </View>
      ) : serviceError ? (
        <View className="bg-white rounded-xl border border-red-100 px-4 py-5 items-center">
          <AlertCircle color="#ef4444" size={24} />
          <Text className="mt-2 text-sm text-gray-600 text-center">Could not load today's service.</Text>
          <TouchableOpacity
            className="mt-3 px-4 py-2 bg-emerald-600 rounded-lg"
            onPress={() => refetchService()}
          >
            <Text className="text-white text-sm font-semibold">Retry</Text>
          </TouchableOpacity>
        </View>
      ) : (
        <>
          {service && service.length > 0 ? (
            <View className="bg-white rounded-xl border border-gray-100 overflow-hidden mb-3">
              {service.map((row, idx) => (
                <View
                  key={`${row.meal_type}-${idx}`}
                  className={`flex-row items-center px-4 py-3 ${idx < service.length - 1 ? 'border-b border-gray-100' : ''}`}
                >
                  <View className="flex-1">
                    <Text className="text-xs text-gray-400 uppercase tracking-wide">{row.meal_type}</Text>
                    <Text className="text-sm font-medium text-gray-800 mt-0.5">{row.name}</Text>
                  </View>
                  <View className="flex-row gap-2 items-center">
                    {row.prepped && (
                      <View className="bg-green-100 px-2 py-0.5 rounded-full">
                        <Text className="text-xs font-medium text-green-700">Prepped</Text>
                      </View>
                    )}
                    {row.has_shortfall && (
                      <View className="bg-red-100 px-2 py-0.5 rounded-full">
                        <Text className="text-xs font-medium text-red-700">Shortfall</Text>
                      </View>
                    )}
                  </View>
                </View>
              ))}
            </View>
          ) : (
            <View className="bg-white rounded-xl border border-gray-100 px-4 py-6 items-center mb-3">
              <Text className="text-gray-400 text-sm">No service entries for today.</Text>
            </View>
          )}

          {!activeCycle ? (
            <View className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-4">
              <Text className="text-amber-700 text-sm text-center">No active menu cycle — contact RND.</Text>
            </View>
          ) : (
            <TouchableOpacity
              className={`flex-row items-center justify-center gap-2 py-3.5 rounded-xl ${
                markServedMutation.isPending ? 'bg-emerald-400' : 'bg-emerald-600'
              }`}
              onPress={handleMarkServed}
              disabled={markServedMutation.isPending}
              accessibilityLabel="Mark today's meals as served"
              accessibilityRole="button"
            >
              {markServedMutation.isPending ? (
                <ActivityIndicator color="#fff" size="small" />
              ) : (
                <CheckCircle2 color="#fff" size={18} />
              )}
              <Text className="text-white font-semibold text-sm">
                {markServedMutation.isPending ? 'Marking served…' : 'Mark served'}
              </Text>
            </TouchableOpacity>
          )}

          {markServedMutation.isSuccess && (
            <View className="mt-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3 flex-row items-center gap-2">
              <CheckCircle2 color="#16a34a" size={18} />
              <Text className="text-green-700 text-sm font-medium">Service day marked complete.</Text>
            </View>
          )}
        </>
      )}

      {/* Shortfall modal */}
      <Modal
        visible={showShortfallModal}
        transparent
        animationType="fade"
        onRequestClose={() => setShowShortfallModal(false)}
      >
        <View className="flex-1 bg-black/50 justify-center px-6">
          <View className="bg-white rounded-2xl p-6">
            <View className="flex-row items-center gap-2 mb-3">
              <TriangleAlert color="#d97706" size={22} />
              <Text className="text-base font-semibold text-gray-900">Inventory Shortfall</Text>
            </View>
            <Text className="text-sm text-gray-600 leading-5 mb-5">{shortfallMessage}</Text>
            <View className="gap-3">
              <TouchableOpacity
                className="bg-amber-500 py-3 rounded-xl items-center"
                onPress={handleProceedWithShortfall}
                disabled={markServedMutation.isPending}
                accessibilityLabel="Proceed anyway with shortfall"
                accessibilityRole="button"
              >
                {markServedMutation.isPending ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : (
                  <Text className="text-white font-semibold">Proceed anyway</Text>
                )}
              </TouchableOpacity>
              <TouchableOpacity
                className="border border-gray-200 py-3 rounded-xl items-center"
                onPress={() => setShowShortfallModal(false)}
                disabled={markServedMutation.isPending}
                accessibilityLabel="Cancel mark served"
                accessibilityRole="button"
              >
                <Text className="text-gray-600 font-medium">Cancel</Text>
              </TouchableOpacity>
            </View>
          </View>
        </View>
      </Modal>
    </View>
  );
}

// ---------------------------------------------------------------------------
// Section B — Diet-list accomplishment
// ---------------------------------------------------------------------------

const TASK_FLAGS = [
  { key: 'helped_food_prep', label: 'Helped food prep' },
  { key: 'stored_supplies', label: 'Stored supplies' },
  { key: 'collected_diet_list', label: 'Collected diet list' },
  { key: 'apportioned_food', label: 'Apportioned food' },
  { key: 'cleaned_utensils', label: 'Cleaned utensils' },
  { key: 'assistant_cook', label: 'Assistant cook' },
  { key: 'maintained_cleanliness', label: 'Maintained cleanliness' },
] as const;

type TaskKey = (typeof TASK_FLAGS)[number]['key'];

type TaskFlags = Record<TaskKey, boolean>;

function AccomplishmentSection({ activeCycleId }: { activeCycleId?: number }) {
  const qc = useQueryClient();

  const [ward, setWard] = useState('');
  const [population, setPopulation] = useState('');
  const [offDuty, setOffDuty] = useState(false);
  const [tasks, setTasks] = useState<TaskFlags>({
    helped_food_prep: false,
    stored_supplies: false,
    collected_diet_list: false,
    apportioned_food: false,
    cleaned_utensils: false,
    assistant_cook: false,
    maintained_cleanliness: false,
  });
  const [validationError, setValidationError] = useState<string | null>(null);
  const [submitSuccess, setSubmitSuccess] = useState(false);

  const {
    data: todayCounts,
    refetch: refetchCounts,
  } = useQuery({
    queryKey: ['fss-diet-list-today'],
    queryFn: fetchTodayDietCounts,
    staleTime: 30_000,
  });

  const totalPopulation = todayCounts?.reduce((acc, c) => acc + c.population, 0) ?? 0;

  const submitMutation = useMutation({
    mutationFn: async () => {
      const pop = offDuty ? 0 : parseInt(population, 10);
      const body: Record<string, unknown> = {
        service_date: today(),
        ward: offDuty ? (ward.trim() || 'Absent') : ward.trim(),
        population: pop,
        off_duty: offDuty,
        ...tasks,
      };
      if (activeCycleId != null) {
        body.menu_cycle_id = activeCycleId;
      }
      const res = await api.post('/api/fss/diet-list-counts', body);
      return res.data;
    },
    onSuccess: () => {
      setSubmitSuccess(true);
      setWard('');
      setPopulation('');
      setOffDuty(false);
      setTasks({
        helped_food_prep: false,
        stored_supplies: false,
        collected_diet_list: false,
        apportioned_food: false,
        cleaned_utensils: false,
        assistant_cook: false,
        maintained_cleanliness: false,
      });
      setValidationError(null);
      refetchCounts();
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      setTimeout(() => setSubmitSuccess(false), 4000);
    },
  });

  const handleToggleTask = useCallback((key: TaskKey) => {
    setTasks((prev) => ({ ...prev, [key]: !prev[key] }));
  }, []);

  const handleSubmit = () => {
    setValidationError(null);
    setSubmitSuccess(false);

    if (!offDuty && !ward.trim()) {
      setValidationError('Ward is required.');
      return;
    }
    const pop = offDuty ? 0 : parseInt(population, 10);
    if (!offDuty && (isNaN(pop) || pop < 0)) {
      setValidationError('Population must be 0 or greater.');
      return;
    }
    submitMutation.mutate();
  };

  return (
    <View className="px-4 mt-6 mb-4">
      <View className="flex-row items-center gap-2 mb-3">
        <ClipboardCheck color="#7c3aed" size={20} />
        <Text className="text-base font-semibold text-gray-800">Accomplishment / Diet List</Text>
      </View>

      {/* Running total */}
      <View className="bg-emerald-50 border border-emerald-100 rounded-xl px-4 py-3 flex-row items-center justify-between mb-4">
        <Text className="text-sm text-emerald-700">Today's total headcount</Text>
        <Text className="text-2xl font-bold text-emerald-700 tabular-nums">{totalPopulation}</Text>
      </View>

      {/* Form */}
      <View className="bg-white rounded-xl border border-gray-100 px-4 py-4 gap-4">
        {/* Ward */}
        <View>
          <Text className="text-xs font-medium text-gray-500 mb-1.5">Ward *</Text>
          <TextInput
            value={ward}
            onChangeText={(t) => {
              setWard(t);
              setValidationError(null);
            }}
            placeholder="e.g. Ward A"
            placeholderTextColor="#9ca3af"
            className="border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-900"
            autoCapitalize="words"
            returnKeyType="next"
            accessibilityLabel="Ward name"
          />
        </View>

        {/* Population */}
        <View>
          <Text className="text-xs font-medium text-gray-500 mb-1.5">Population (headcount) *</Text>
          <TextInput
            value={population}
            onChangeText={(t) => {
              setPopulation(t.replace(/[^0-9]/g, ''));
              setValidationError(null);
            }}
            placeholder="0"
            placeholderTextColor="#9ca3af"
            className="border border-gray-200 rounded-lg px-3 py-2.5 text-sm text-gray-900 tabular-nums"
            keyboardType="numeric"
            returnKeyType="done"
            accessibilityLabel="Population headcount"
          />
        </View>

        {/* Task flags */}
        <View>
          <Text className="text-xs font-medium text-gray-500 mb-2">Tasks completed</Text>
          <View className="gap-1">
            {TASK_FLAGS.map(({ key, label }) => (
              <Pressable
                key={key}
                onPress={() => handleToggleTask(key)}
                className="flex-row items-center py-2 gap-3"
                accessibilityRole="checkbox"
                accessibilityState={{ checked: tasks[key] }}
                accessibilityLabel={label}
              >
                <View
                  className={`w-5 h-5 rounded border-2 items-center justify-center ${
                    tasks[key] ? 'bg-emerald-600 border-emerald-600' : 'border-gray-300 bg-white'
                  }`}
                >
                  {tasks[key] && (
                    <Text className="text-white text-xs font-bold leading-none">✓</Text>
                  )}
                </View>
                <Text className="text-sm text-gray-700 flex-1">{label}</Text>
              </Pressable>
            ))}
          </View>
        </View>

        {/* Off duty toggle */}
        <View className="flex-row items-center justify-between py-2 border-t border-gray-100">
          <View className="flex-1 pr-4">
            <Text className="text-sm text-gray-700">Absent / off duty today</Text>
            <Text className="text-xs text-gray-400 mt-0.5">
              Saves today as X in the accomplishment report.
            </Text>
          </View>
          <Switch
            value={offDuty}
            onValueChange={setOffDuty}
            trackColor={{ false: '#d1d5db', true: '#bfdbfe' }}
            thumbColor={offDuty ? '#059669' : '#6b7280'}
            accessibilityLabel="Off duty toggle"
            accessibilityRole="switch"
          />
        </View>

        {/* Validation error */}
        {validationError && (
          <View className="flex-row items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5">
            <AlertCircle color="#ef4444" size={16} />
            <Text className="text-sm text-red-700 flex-1">{validationError}</Text>
          </View>
        )}

        {/* Submit error */}
        {submitMutation.isError && !validationError && (
          <View className="flex-row items-center gap-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2.5">
            <AlertCircle color="#ef4444" size={16} />
            <Text className="text-sm text-red-700 flex-1">
              {/* eslint-disable-next-line @typescript-eslint/no-explicit-any */}
              {(submitMutation.error as any)?.response?.data?.message ?? 'Submit failed. Try again.'}
            </Text>
          </View>
        )}

        {/* Success */}
        {submitSuccess && (
          <View className="flex-row items-center gap-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2.5">
            <CheckCircle2 color="#16a34a" size={16} />
            <Text className="text-sm text-green-700 font-medium">Diet list entry saved.</Text>
          </View>
        )}

        {/* Submit button */}
        <TouchableOpacity
          className={`flex-row items-center justify-center gap-2 py-3.5 rounded-xl ${
            submitMutation.isPending ? 'bg-emerald-400' : 'bg-emerald-600'
          }`}
          onPress={handleSubmit}
          disabled={submitMutation.isPending}
          accessibilityLabel="Submit diet list entry"
          accessibilityRole="button"
        >
          {submitMutation.isPending ? (
            <ActivityIndicator color="#fff" size="small" />
          ) : (
            <ClipboardCheck color="#fff" size={18} />
          )}
          <Text className="text-white font-semibold text-sm">
            {submitMutation.isPending ? 'Saving…' : 'Submit entry'}
          </Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

// ---------------------------------------------------------------------------
// Root screen
// ---------------------------------------------------------------------------

export default function PrepScreen() {
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();
  const [refreshing, setRefreshing] = useState(false);

  const { data: activeCycle } = useQuery({
    queryKey: ['fss-active-cycle'],
    queryFn: fetchActiveCycle,
    staleTime: 120_000,
  });

  const onRefresh = useCallback(async () => {
    setRefreshing(true);
    await Promise.all([
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] }),
      qc.invalidateQueries({ queryKey: ['fss-active-cycle'] }),
      qc.invalidateQueries({ queryKey: ['fss-diet-list-today'] }),
    ]);
    setRefreshing(false);
  }, [qc]);

  return (
    <ScrollView
      className="flex-1 bg-gray-50"
      contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
      refreshControl={
        <RefreshControl refreshing={refreshing} onRefresh={onRefresh} />
      }
    >
      <View className="px-4 mt-4 gap-2.5">
        <TouchableOpacity
          className="flex-row items-center justify-between bg-white border border-gray-100 rounded-xl px-4 py-3"
          onPress={() => router.push('/(tabs)/menu')}
          accessibilityLabel="Open menu cycles and served population"
          accessibilityRole="button"
        >
          <View className="flex-row items-center gap-2">
            <CalendarDays color="#059669" size={18} />
            <Text className="text-sm font-semibold text-gray-800">Menu cycles and served population</Text>
          </View>
          <Text className="text-xs text-gray-400">Open</Text>
        </TouchableOpacity>

        <TouchableOpacity
          className="flex-row items-center justify-between bg-white border border-gray-100 rounded-xl px-4 py-3"
          onPress={() => router.push('/reports')}
          accessibilityLabel="Open archived accomplishment reports"
          accessibilityRole="button"
        >
          <View className="flex-row items-center gap-2">
            <FileText color="#7c3aed" size={18} />
            <Text className="text-sm font-semibold text-gray-800">My accomplishment reports</Text>
          </View>
          <Text className="text-xs text-gray-400">Open</Text>
        </TouchableOpacity>
      </View>
      <MealPrepSection />
      <View className="mx-4 mt-6 border-t border-gray-100" />
      <AccomplishmentSection activeCycleId={activeCycle?.id} />
    </ScrollView>
  );
}

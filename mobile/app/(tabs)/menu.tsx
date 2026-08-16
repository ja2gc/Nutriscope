import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarDays, ChevronLeft, ChevronRight, Utensils, X } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Modal,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  DAYS, MEALS, MEAL_LABELS, MenuCycle, MenuDay, RecipeProfile, FsItemProfile, MenuSnapshot,
  getFsItemProfile, getMenuCycle, getRecipeProfile, listMealPrep, listMenuCycles, setServedPopulation,
} from '../../lib/foodService';
import { PaginatedListFooter } from '../../components/PaginatedListFooter';
import { flattenUniquePages, getNextPageParam } from '../../lib/pagination';
import { DayPicker } from '../../components/menu/DayPicker';

const peso = (n: number) => `₱${n.toFixed(2)}`;

// ── Read-only recipe profile (scaled to RND's set servings / day population) ──────
type ProfileTarget =
  | { type: 'recipe'; id: number; population: number; quantity?: number }
  | { type: 'item'; id: number; population: number; quantity: number }
  | { type: 'snapshot'; data: MenuSnapshot };

type DisplayProfile = {
  name: string;
  prep_notes: string | null;
  servings: number;
  population: number;
  total_cost: number;
  cost_per_head: number;
  ingredient_usage: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
};

function snapshotToProfile(snapshot: MenuSnapshot): DisplayProfile {
  const population = Number(snapshot.population ?? 0);
  const totalCost = Number(snapshot.total_cost ?? 0);
  const ingredientUsage = snapshot.ingredient_usage ?? (snapshot.total_quantity != null ? [{
    fs_item_id: snapshot.fs_item_id ?? 0,
    name: snapshot.name,
    unit: snapshot.unit ?? '',
    quantity: Number(snapshot.total_quantity),
    cost: totalCost,
  }] : []);

  return {
    name: snapshot.name,
    prep_notes: snapshot.prep_notes ?? null,
    servings: Number(snapshot.servings ?? population),
    population,
    total_cost: totalCost,
    cost_per_head: Number(snapshot.cost_per_head ?? (population > 0 ? totalCost / population : 0)),
    ingredient_usage: ingredientUsage,
  };
}

function MenuItemModal({
  profile,
  onClose,
}: {
  profile: ProfileTarget;
  onClose: () => void;
}) {
  const { data, isLoading } = useQuery<DisplayProfile | RecipeProfile | FsItemProfile>({
    queryKey: [
      'fs-menu-item-profile',
      profile.type,
      profile.type === 'snapshot' ? profile.data.name : profile.id,
      profile.type === 'snapshot' ? profile.data.population : profile.population,
      profile.type === 'item' ? profile.quantity : 1,
    ],
    queryFn: async () => {
      if (profile.type === 'snapshot') {
        return snapshotToProfile(profile.data);
      }
      if (profile.type === 'recipe') {
        return getRecipeProfile(profile.id, Math.max(1, profile.population));
      }
      return getFsItemProfile(profile.id, Math.max(1, profile.population), profile.quantity);
    },
  });

  return (
    <Modal visible animationType="slide" transparent onRequestClose={onClose}>
      <View className="flex-1 bg-black/40 justify-end">
        <View className="bg-white rounded-t-3xl max-h-[85%]">
          <View className="flex-row items-center justify-between px-5 py-4 border-b border-gray-100">
            <Text className="text-sm font-extrabold text-gray-900 flex-1" numberOfLines={1}>
              {data?.name ?? 'Recipe'}
            </Text>
            <TouchableOpacity onPress={onClose} hitSlop={8}><X color="#374151" size={20} /></TouchableOpacity>
          </View>
          {isLoading || !data ? (
            <View className="py-16 items-center"><ActivityIndicator color="#059669" /></View>
          ) : (
            <ScrollView contentContainerStyle={{ padding: 20 }}>
              <Text className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                Scaled to {data.population} servings · baseline serves {data.servings} (view only)
              </Text>
              <View className="flex-row gap-6 mt-3">
                <View>
                  <Text className="text-[10px] font-extrabold uppercase tracking-wider text-gray-500">Total</Text>
                  <Text className="text-xl font-extrabold text-emerald-600">{peso(data.total_cost)}</Text>
                </View>
                <View>
                  <Text className="text-[10px] font-extrabold uppercase tracking-wider text-gray-500">Cost / head</Text>
                  <Text className="text-xl font-extrabold text-gray-800">{peso(data.cost_per_head)}</Text>
                </View>
              </View>

              <Text className="text-[10px] font-extrabold uppercase tracking-wider text-gray-500 mt-5 mb-1">Ingredients (scaled)</Text>
              {data.ingredient_usage.map((u) => (
                <View key={u.fs_item_id} className="flex-row items-center justify-between py-1.5 border-b border-gray-50">
                  <Text className="text-sm text-gray-700 flex-1">{u.name}</Text>
                  <Text className="text-xs text-gray-500 tabular-nums">{u.quantity.toFixed(1)} {u.unit}</Text>
                  <Text className="text-xs font-semibold text-emerald-700 tabular-nums ml-3">{peso(u.cost)}</Text>
                </View>
              ))}

              {data.prep_notes ? (
                <View className="mt-5">
                  <Text className="text-[10px] font-extrabold uppercase tracking-wider text-gray-500 mb-1">Preparation notes</Text>
                  <Text className="text-xs text-gray-700 leading-6 bg-gray-50 border border-gray-100 rounded-xl p-3">{data.prep_notes}</Text>
                </View>
              ) : null}
            </ScrollView>
          )}
        </View>
      </View>
    </Modal>
  );
}

// ── Per-day served population editor (backfill, works for ANY cycle day) ──────────
function ServedRow({
  cycleId,
  date,
  weekday,
  served,
}: {
  cycleId: number;
  date: string;
  weekday: string;
  served: number | null;
}) {
  const qc = useQueryClient();
  const [val, setVal] = useState(served != null ? String(served) : '');
  const [saved, setSaved] = useState(false);
  const mut = useMutation({
    mutationFn: () => setServedPopulation(cycleId, date, parseInt(val) || 0),
    onSuccess: () => {
      setSaved(true);
      setTimeout(() => setSaved(false), 2000);
      qc.invalidateQueries({ queryKey: ['fs-mealprep', cycleId] });
    },
  });

  return (
    <View className="flex-row items-center justify-between px-4 py-3 border-b border-gray-100">
      <View>
        <Text className="text-sm font-medium text-gray-700">{weekday}</Text>
        <Text className="text-[11px] text-gray-400">{new Date(date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}</Text>
      </View>
      <View className="flex-row items-center gap-2">
        <TextInput
          value={val}
          onChangeText={setVal}
          keyboardType="number-pad"
          placeholder="served"
          placeholderTextColor="#9ca3af"
          className="w-20 px-2 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-900 tabular-nums"
        />
        <TouchableOpacity
          onPress={() => mut.mutate()}
          disabled={mut.isPending || val.trim() === ''}
          className={`px-3 py-1.5 rounded-lg ${mut.isPending || val.trim() === '' ? 'bg-emerald-300' : saved ? 'bg-green-600' : 'bg-emerald-600'}`}
        >
          <Text className="text-white text-xs font-semibold">{mut.isPending ? '…' : saved ? 'Saved' : 'Save'}</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

// ── Cycle detail: read-only week + served entry ───────────────────────────────────
function CycleDetail({ cycleId, onBack }: { cycleId: number; onBack: () => void }) {
  const [profile, setProfile] = useState<ProfileTarget | null>(null);
  const [selectedDay, setSelectedDay] = useState('');
  const { data: cycle, isLoading } = useQuery({ queryKey: ['fs-cycle', cycleId], queryFn: () => getMenuCycle(cycleId) });
  const { data: prep } = useQuery({ queryKey: ['fs-mealprep', cycleId], queryFn: () => listMealPrep(cycleId) });

  useEffect(() => {
    if (!cycle) return;
    const available = DAYS.filter((day) => cycle.days?.some((entry) => entry.day_of_week === day));
    if (selectedDay && available.includes(selectedDay)) return;
    const todayName = new Date().toLocaleDateString('en-US', { weekday: 'long' });
    setSelectedDay(available.includes(todayName) ? todayName : available[0] ?? '');
  }, [cycle, selectedDay]);

  if (isLoading || !cycle) {
    return <View className="flex-1 items-center justify-center bg-gray-50"><ActivityIndicator color="#059669" /></View>;
  }

  const byDay: Record<string, MenuDay[]> = {};
  (cycle.days ?? []).forEach((d) => { (byDay[d.day_of_week] ??= []).push(d); });
  const availableDays = DAYS.filter((day) => byDay[day]?.length);

  return (
    <ScrollView className="flex-1 bg-gray-50" contentContainerStyle={{ paddingBottom: 32 }}>
      <View className="flex-row items-center gap-2 px-4 py-3 bg-white border-b border-gray-100">
        <TouchableOpacity onPress={onBack} hitSlop={8}><ChevronLeft color="#374151" size={22} /></TouchableOpacity>
        <Text className="text-base font-bold text-gray-900 flex-1">{cycle.name}</Text>
        {cycle.is_active && (
          <View className="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200">
            <Text className="text-[10px] font-bold text-emerald-700">Active</Text>
          </View>
        )}
      </View>

      {selectedDay && (
        <DayPicker days={availableDays} weekStartDate={cycle.week_start_date} selectedDay={selectedDay} onSelect={setSelectedDay} />
      )}

      {selectedDay && (() => {
        const planned = Number(byDay[selectedDay]?.[0]?.estimate_population ?? 0);
        const serviceDate = cycle.week_start_date ? new Date(`${cycle.week_start_date}T00:00:00`) : null;
        if (serviceDate) serviceDate.setDate(serviceDate.getDate() + DAYS.indexOf(selectedDay));
        const dateKey = serviceDate ? `${serviceDate.getFullYear()}-${String(serviceDate.getMonth() + 1).padStart(2, '0')}-${String(serviceDate.getDate()).padStart(2, '0')}` : '';
        const served = prep?.find((log) => log.service_date === dateKey)?.served_population ?? 0;
        return (
          <View className="mx-4 mt-4 flex-row gap-3">
            <View className="flex-1 rounded-2xl bg-[#EAF7F1] border border-[#CBEADB] p-4">
              <Text className="text-[10px] font-bold uppercase tracking-wider text-[#4D7464]">Planned population</Text>
              <Text className="text-2xl font-extrabold text-[#087F5B] mt-1 tabular-nums">{planned}</Text>
            </View>
            <View className="flex-1 rounded-2xl bg-[#FFF7EA] border border-[#F3DEC1] p-4">
              <Text className="text-[10px] font-bold uppercase tracking-wider text-[#8A653B]">Total served</Text>
              <Text className="text-2xl font-extrabold text-[#A65B13] mt-1 tabular-nums">{served}</Text>
            </View>
          </View>
        );
      })()}

      {/* Week — read-only foods */}
      {DAYS.filter((d) => d === selectedDay && byDay[d]?.length).map((day) => {
        const pop = byDay[day][0]?.estimate_population ?? 0;
        return (
          <View key={day} className="mt-3 mx-4 bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <View className="flex-row items-center justify-between px-4 py-2.5 bg-gray-50 border-b border-gray-100">
              <Text className="text-sm font-bold text-gray-800">{day}</Text>
              <Text className="text-[11px] text-gray-400">{pop} heads</Text>
            </View>
            {MEALS.filter((m) => byDay[day].some((d) => d.meal_type === m)).map((m) => {
              const entry = byDay[day].find((d) => d.meal_type === m)!;
              const name = entry.recipe?.name ?? entry.fs_item?.name ?? '—';
              const scaleTo = entry.servings_override ?? entry.estimate_population ?? pop;
              return (
                <TouchableOpacity
                  key={m}
                  disabled={!entry.recipe_id && !entry.fs_item_id && !entry.po_snapshot}
                  onPress={() => {
                    if (entry.po_snapshot) {
                      setProfile({ type: 'snapshot', data: entry.po_snapshot });
                      return;
                    }
                    if (entry.recipe_id) {
                      setProfile({ type: 'recipe', id: entry.recipe_id, population: scaleTo });
                      return;
                    }
                    if (entry.fs_item_id) {
                      setProfile({ type: 'item', id: entry.fs_item_id, population: scaleTo, quantity: Number(entry.quantity ?? 1) });
                    }
                  }}
                  className="flex-row items-center px-4 py-2.5 border-b border-gray-50"
                >
                  <Text className="text-[10px] font-bold uppercase tracking-wider text-gray-400 w-20">{MEAL_LABELS[m]}</Text>
                  <Text className="text-sm text-gray-700 flex-1">{name}</Text>
                  {entry.recipe_id || entry.fs_item_id ? <ChevronRight color="#9ca3af" size={16} /> : null}
                </TouchableOpacity>
              );
            })}
          </View>
        );
      })}

      {/* Served population per service day — editable for EVERY day of the cycle */}
      {cycle.week_start_date && Object.keys(byDay).length > 0 && (
        <View className="mt-5 mx-4 bg-white rounded-2xl border border-gray-100 overflow-hidden">
          <View className="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
            <Text className="text-xs font-extrabold uppercase tracking-wider text-gray-600">Actual served population per day</Text>
            <Text className="text-[10px] text-gray-400 mt-0.5">Record the real headcount for any day. Used for food purchase cost per served patient-day.</Text>
          </View>
          {(() => {
            const servedByDate: Record<string, number | null> = {};
            (prep ?? []).forEach((log) => { servedByDate[log.service_date] = log.served_population; });
            const weekStart = new Date(cycle.week_start_date + 'T00:00:00');
            return DAYS.filter((d) => d === selectedDay && byDay[d]?.length).map((day) => {
              const offset = DAYS.indexOf(day);
              const d = new Date(weekStart);
              d.setDate(d.getDate() + offset);
              const dateStr = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
              return (
                <ServedRow
                  key={day}
                  cycleId={cycleId}
                  date={dateStr}
                  weekday={day}
                  served={servedByDate[dateStr] ?? null}
                />
              );
            });
          })()}
        </View>
      )}

      {profile && (
        <MenuItemModal profile={profile} onClose={() => setProfile(null)} />
      )}
    </ScrollView>
  );
}

// ── Cycle list (active first) ─────────────────────────────────────────────────────
export default function MenuScreen() {
  const insets = useSafeAreaInsets();
  const [openId, setOpenId] = useState<number | null>(null);
  const [autoOpened, setAutoOpened] = useState(false);
  const { data, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['fs-cycles'],
    queryFn: ({ pageParam }) => listMenuCycles(pageParam),
    initialPageParam: 1,
    getNextPageParam,
  });
  const cycles = flattenUniquePages(data?.pages).sort((a: MenuCycle, b: MenuCycle) => Number(b.is_active) - Number(a.is_active));

  useEffect(() => {
    if (autoOpened || openId || cycles.length === 0) return;
    const active = cycles.find((cycle) => cycle.is_active);
    if (active) {
      setAutoOpened(true);
      setOpenId(active.id);
    }
  }, [autoOpened, cycles, openId]);

  if (openId) return <CycleDetail cycleId={openId} onBack={() => setOpenId(null)} />;

  if (isLoading) {
    return <View className="flex-1 items-center justify-center bg-gray-50"><ActivityIndicator size="large" color="#059669" /></View>;
  }
  if (isError) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50 px-6">
        <Utensils color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">Could not load menu cycles</Text>
        <TouchableOpacity className="mt-5 bg-emerald-600 px-6 py-3 rounded-lg" onPress={() => refetch()}>
          <Text className="text-white font-semibold">Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <FlatList
      className="bg-gray-50"
      data={cycles}
      keyExtractor={(c) => String(c.id)}
      contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16, flexGrow: 1 }}
      renderItem={({ item }) => (
        <TouchableOpacity
          onPress={() => setOpenId(item.id)}
          className="bg-white rounded-2xl border border-gray-200 p-4 mb-3 flex-row items-center"
        >
          <View className="h-10 w-10 rounded-xl bg-emerald-50 items-center justify-center mr-3">
            <CalendarDays color="#059669" size={20} />
          </View>
          <View className="flex-1">
            <Text className="text-sm font-bold text-gray-900">{item.name}</Text>
            <Text className="text-[11px] text-gray-400 capitalize">{item.is_active ? 'Active · whole week' : item.status}</Text>
          </View>
          <ChevronRight color="#9ca3af" size={18} />
        </TouchableOpacity>
      )}
      onEndReached={() => { if (hasNextPage && !isFetchingNextPage) void fetchNextPage(); }}
      onEndReachedThreshold={0.4}
      ListFooterComponent={<PaginatedListFooter loading={isFetchingNextPage} error={isFetchNextPageError} onRetry={() => void fetchNextPage()} />}
      ListEmptyComponent={
        <View className="items-center justify-center py-20">
          <CalendarDays color="#d1d5db" size={40} />
          <Text className="mt-4 text-gray-400 text-sm">No menu cycles yet.</Text>
        </View>
      }
    />
  );
}

import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { CalendarDays, ChevronLeft, ChevronRight, Utensils, X } from 'lucide-react-native';
import { useState } from 'react';
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
  DAYS, MEALS, MEAL_LABELS, MenuCycle, MenuDay,
  getMenuCycle, getRecipeProfile, listMealPrep, listMenuCycles, setServedPopulation,
} from '../../lib/foodService';

const peso = (n: number) => `₱${n.toFixed(2)}`;

// ── Read-only recipe profile (scaled to RND's set servings / day population) ──────
function RecipeModal({ recipeId, population, onClose }: { recipeId: number; population: number; onClose: () => void }) {
  const { data, isLoading } = useQuery({
    queryKey: ['fs-recipe-profile', recipeId, population],
    queryFn: () => getRecipeProfile(recipeId, Math.max(1, population)),
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
                Scaled to {population} servings · baseline serves {data.servings} (view only)
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

// ── Per-day served population editor (backfill) ───────────────────────────────────
function ServedRow({ cycleId, date, served }: { cycleId: number; date: string; served: number | null }) {
  const qc = useQueryClient();
  const [val, setVal] = useState(served != null ? String(served) : '');
  const mut = useMutation({
    mutationFn: () => setServedPopulation(cycleId, date, parseInt(val) || 0),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['fs-mealprep', cycleId] }),
  });

  return (
    <View className="flex-row items-center justify-between px-4 py-3 border-b border-gray-100">
      <Text className="text-sm text-gray-700">{new Date(date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' })}</Text>
      <View className="flex-row items-center gap-2">
        <TextInput
          value={val}
          onChangeText={setVal}
          keyboardType="number-pad"
          placeholder="served"
          className="w-20 px-2 py-1.5 text-sm border border-gray-200 rounded-lg text-gray-900"
        />
        <TouchableOpacity
          onPress={() => mut.mutate()}
          disabled={mut.isPending}
          className={`px-3 py-1.5 rounded-lg ${mut.isPending ? 'bg-emerald-400' : 'bg-emerald-600'}`}
        >
          <Text className="text-white text-xs font-semibold">{mut.isPending ? '…' : 'Save'}</Text>
        </TouchableOpacity>
      </View>
    </View>
  );
}

// ── Cycle detail: read-only week + served entry ───────────────────────────────────
function CycleDetail({ cycleId, onBack }: { cycleId: number; onBack: () => void }) {
  const [profile, setProfile] = useState<{ recipeId: number; population: number } | null>(null);
  const { data: cycle, isLoading } = useQuery({ queryKey: ['fs-cycle', cycleId], queryFn: () => getMenuCycle(cycleId) });
  const { data: prep } = useQuery({ queryKey: ['fs-mealprep', cycleId], queryFn: () => listMealPrep(cycleId) });

  if (isLoading || !cycle) {
    return <View className="flex-1 items-center justify-center bg-gray-50"><ActivityIndicator color="#059669" /></View>;
  }

  const byDay: Record<string, MenuDay[]> = {};
  (cycle.days ?? []).forEach((d) => { (byDay[d.day_of_week] ??= []).push(d); });

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

      {/* Week — read-only foods */}
      {DAYS.filter((d) => byDay[d]?.length).map((day) => {
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
                  disabled={!entry.recipe_id}
                  onPress={() => entry.recipe_id && setProfile({ recipeId: entry.recipe_id, population: scaleTo })}
                  className="flex-row items-center px-4 py-2.5 border-b border-gray-50"
                >
                  <Text className="text-[10px] font-bold uppercase tracking-wider text-gray-400 w-20">{MEAL_LABELS[m]}</Text>
                  <Text className="text-sm text-gray-700 flex-1">{name}</Text>
                  {entry.recipe_id ? <ChevronRight color="#9ca3af" size={16} /> : null}
                </TouchableOpacity>
              );
            })}
          </View>
        );
      })}

      {/* Served population per service day (backfill) */}
      {prep && prep.length > 0 && (
        <View className="mt-5 mx-4 bg-white rounded-2xl border border-gray-100 overflow-hidden">
          <View className="px-4 py-2.5 bg-gray-50 border-b border-gray-100">
            <Text className="text-xs font-extrabold uppercase tracking-wider text-gray-600">Served population per day</Text>
            <Text className="text-[10px] text-gray-400 mt-0.5">Backfill any day you missed. Drives the actual budget per head.</Text>
          </View>
          {prep
            .slice()
            .sort((a, b) => a.service_date.localeCompare(b.service_date))
            .map((log) => (
              <ServedRow key={log.id} cycleId={cycleId} date={log.service_date} served={log.served_population} />
            ))}
        </View>
      )}

      {profile && (
        <RecipeModal recipeId={profile.recipeId} population={profile.population} onClose={() => setProfile(null)} />
      )}
    </ScrollView>
  );
}

// ── Cycle list (active first) ─────────────────────────────────────────────────────
export default function MenuScreen() {
  const insets = useSafeAreaInsets();
  const [openId, setOpenId] = useState<number | null>(null);
  const { data, isLoading, isError, refetch } = useQuery({ queryKey: ['fs-cycles'], queryFn: listMenuCycles });

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

  // Active cycle first.
  const cycles = (data ?? []).slice().sort((a: MenuCycle, b: MenuCycle) => Number(b.is_active) - Number(a.is_active));

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
      ListEmptyComponent={
        <View className="items-center justify-center py-20">
          <CalendarDays color="#d1d5db" size={40} />
          <Text className="mt-4 text-gray-400 text-sm">No menu cycles yet.</Text>
        </View>
      }
    />
  );
}

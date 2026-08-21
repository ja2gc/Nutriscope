import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { ChefHat, Coins, Users } from 'lucide-react-native';
import { ActivityIndicator, ScrollView, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { FsItemProfile, getFsItemProfile, getMenuCycle, getRecipeProfile, MenuSnapshot, RecipeProfile } from '../lib/foodService';

type DisplayProfile = {
  name: string; prep_notes: string | null; servings: number; population: number;
  total_cost: number; cost_per_head: number;
  ingredient_usage: { fs_item_id: number; name: string; unit: string; quantity: number; cost: number }[];
};

function fromSnapshot(snapshot: MenuSnapshot): DisplayProfile {
  const population = Number(snapshot.population ?? 0);
  const totalCost = Number(snapshot.total_cost ?? 0);
  return {
    name: snapshot.name,
    prep_notes: snapshot.prep_notes ?? null,
    servings: Number(snapshot.servings ?? population),
    population,
    total_cost: totalCost,
    cost_per_head: Number(snapshot.cost_per_head ?? (population > 0 ? totalCost / population : 0)),
    ingredient_usage: snapshot.ingredient_usage ?? (snapshot.total_quantity == null ? [] : [{
      fs_item_id: snapshot.fs_item_id ?? 0, name: snapshot.name, unit: snapshot.unit ?? '',
      quantity: Number(snapshot.total_quantity), cost: totalCost,
    }]),
  };
}

export default function FoodDetailsScreen() {
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ cycleId: string; day: string; meal: string }>();
  const query = useQuery<DisplayProfile | RecipeProfile | FsItemProfile>({
    queryKey: ['fss-food-details', params.cycleId, params.day, params.meal],
    enabled: Boolean(params.cycleId && params.day && params.meal),
    queryFn: async () => {
      const cycle = await getMenuCycle(params.cycleId);
      const entry = cycle.days?.find((row) => row.day_of_week === params.day && row.meal_type === params.meal);
      if (!entry) throw new Error('Menu slot not found.');
      if (entry.po_snapshot) return fromSnapshot(entry.po_snapshot);
      const population = Math.max(1, Number(entry.servings_override ?? entry.estimate_population ?? 1));
      if (entry.recipe_id) return getRecipeProfile(entry.recipe_id, population);
      if (entry.fs_item_id) return getFsItemProfile(entry.fs_item_id, population, Number(entry.quantity ?? 1));
      throw new Error('Food details are unavailable.');
    },
  });

  if (query.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator color="#087F5B" /></View>;
  if (query.isError || !query.data) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Text className="text-center text-sm text-red-700">Could not load this food profile.</Text></View>;
  const data = query.data;
  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 28 }}>
    <View className="rounded-[24px] bg-[#0B6B4B] p-5"><ChefHat color="#D1FAE5" size={22} /><Text className="mt-3 text-2xl font-extrabold text-white">{data.name}</Text><Text className="mt-1 text-sm text-emerald-100">Read-only menu-slot quantities scaled to {data.population} servings.</Text></View>
    <View className="mt-3 flex-row gap-3"><View className="flex-1 rounded-2xl border border-[#CBEADB] bg-[#EAF7F1] p-4"><Users color="#087F5B" size={19} /><Text className="mt-2 text-2xl font-extrabold text-[#087F5B]">{data.population}</Text><Text className="text-xs text-[#4D7464]">Scaled servings</Text></View><View className="flex-1 rounded-2xl border border-[#F3DEC1] bg-[#FFF7EA] p-4"><Coins color="#A65B13" size={19} /><Text className="mt-2 text-xl font-extrabold text-[#A65B13]">PHP {data.cost_per_head.toFixed(2)}</Text><Text className="text-xs text-[#8A653B]">Cost per head</Text></View></View>
    <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-base font-extrabold text-[#16352B]">Scaled ingredients</Text>{data.ingredient_usage.length === 0 ? <Text className="mt-3 text-sm text-[#7A8D85]">No ingredient breakdown is available.</Text> : data.ingredient_usage.map((item) => <View key={`${item.fs_item_id}-${item.name}`} className="min-h-12 flex-row items-center border-b border-[#EDF2EF] py-2"><Text className="flex-1 text-sm text-[#30483F]">{item.name}</Text><Text className="text-sm font-bold text-[#16352B] tabular-nums">{item.quantity.toFixed(1)} {item.unit}</Text></View>)}</View>
    {data.prep_notes ? <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-base font-extrabold text-[#16352B]">Preparation notes</Text><Text className="mt-2 text-sm leading-6 text-[#30483F]">{data.prep_notes}</Text></View> : null}
  </ScrollView>;
}

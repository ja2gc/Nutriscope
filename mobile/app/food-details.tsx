import { useQuery } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { ChefHat, Users } from 'lucide-react-native';
import { ActivityIndicator, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { getMenuSlotProfile, MenuSlotProfile } from '../lib/foodService';

export default function FoodDetailsScreen() {
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ cycleId: string; day: string; meal: string }>();
  const query = useQuery<MenuSlotProfile>({
    queryKey: ['fss-food-details', params.cycleId, params.day, params.meal],
    enabled: Boolean(params.cycleId && params.day && params.meal),
    queryFn: () => getMenuSlotProfile(params.cycleId, params.day, params.meal),
  });

  if (query.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator color="#087F5B" /></View>;
  if (query.isError || !query.data) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Text className="text-center text-sm text-red-700">Could not load this food profile.</Text><TouchableOpacity onPress={() => query.refetch()} className="mt-4 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6"><Text className="font-bold text-white">Retry</Text></TouchableOpacity></View>;
  const data = query.data;
  const servings = data.planned_servings ?? data.reference_servings;
  const sourceLabel = data.source === 'custom' ? 'Menu-slot version' : data.source === 'locked' ? 'Purchase-order snapshot' : 'Master recipe';
  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 28 }}>
    <View className="rounded-[24px] bg-[#0B6B4B] p-5"><ChefHat color="#D1FAE5" size={22} /><Text className="mt-3 text-2xl font-extrabold text-white">{data.name}</Text><Text className="mt-1 text-sm text-emerald-100">{sourceLabel}</Text></View>
    <View className="mt-3 rounded-2xl border border-[#CBEADB] bg-[#EAF7F1] p-4"><Users color="#087F5B" size={19} /><Text className="mt-2 text-2xl font-extrabold text-[#087F5B]">{servings}</Text><Text className="text-xs text-[#4D7464]">{data.planned_servings == null ? 'Baseline servings' : 'Planned servings'}</Text></View>
    {data.planned_servings == null ? <View className="mt-3 rounded-2xl border border-amber-200 bg-amber-50 p-3"><Text className="text-sm text-amber-800">No planned population is set for this slot. Baseline recipe quantities are shown.</Text></View> : null}
    <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-base font-extrabold text-[#16352B]">{data.planned_servings == null ? 'Baseline ingredients' : 'Scaled ingredients'}</Text>{data.ingredients.length === 0 ? <Text className="mt-3 text-sm text-[#7A8D85]">No ingredient breakdown is available.</Text> : data.ingredients.map((item) => <View key={`${item.fs_item_id}-${item.name}`} className="min-h-12 flex-row items-center border-b border-[#EDF2EF] py-2"><Text className="flex-1 pr-3 text-sm text-[#30483F]">{item.name}</Text><Text className="text-sm font-bold text-[#16352B] tabular-nums">{Number(item.scaled_quantity ?? item.quantity).toFixed(1)} {item.unit}</Text></View>)}</View>
    {data.prep_notes ? <View className="mt-3 rounded-[22px] border border-[#E2EAE5] bg-white p-4"><Text className="text-base font-extrabold text-[#16352B]">Preparation notes</Text><Text className="mt-2 text-sm leading-6 text-[#30483F]">{data.prep_notes}</Text></View> : null}
  </ScrollView>;
}

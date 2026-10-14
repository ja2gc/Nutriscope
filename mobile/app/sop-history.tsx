import { useInfiniteQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ChevronLeft, ChevronRight } from 'lucide-react-native';
import { useState } from 'react';
import { ActivityIndicator, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { MobilePagination } from '../components/MobilePagination';
import { getNextPageParam } from '../lib/pagination';
import { fetchSopHistory, type Sop } from '../lib/sop';
import { useMobileServerPage } from '../lib/useMobileServerPage';

function dateLabel(value: string) {
  return new Date(value).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
}

export default function SopHistoryScreen() {
  const insets = useSafeAreaInsets();
  const [selectedHistory, setSelectedHistory] = useState<Sop | null>(null);
  const query = useInfiniteQuery({ queryKey: ['sop-history'], queryFn: ({ pageParam }) => fetchSopHistory(pageParam), initialPageParam: 1, getNextPageParam });
  const pager = useMobileServerPage(query.data, query.fetchNextPage);

  return <View className="flex-1 bg-[#F4F7F5]" style={{ paddingTop: insets.top }}>
    <View className="min-h-14 flex-row items-center gap-3 border-b border-[#E2EAE5] bg-white px-4">
      <TouchableOpacity onPress={() => selectedHistory ? setSelectedHistory(null) : router.back()} className="min-h-12 flex-row items-center gap-1 pr-3" accessibilityRole="button" accessibilityLabel={selectedHistory ? 'Back to SOP history' : 'Back to SOP'}><ChevronLeft color="#087F5B" size={22} /><Text className="font-bold text-[#087F5B]">Back</Text></TouchableOpacity>
      <Text className="text-base font-extrabold text-[#16352B]">{selectedHistory ? 'SOP version' : 'SOP history'}</Text>
    </View>
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 24 }}>
      {selectedHistory ? <View className="rounded-2xl border border-[#E2EAE5] bg-white p-4"><Text className="text-lg font-bold text-[#16352B]">{selectedHistory.title}</Text><Text className="mt-2 text-xs text-[#6B7F77]">{selectedHistory.author?.name ?? 'Unknown'} · {selectedHistory.author?.role ?? 'Staff'} · {dateLabel(selectedHistory.created_at)}</Text><Text className="mt-4 text-sm leading-6 text-[#30483F]">{selectedHistory.body}</Text></View> : <>
        {query.isLoading ? <ActivityIndicator color="#087F5B" /> : query.isError ? <View className="rounded-xl bg-white p-4"><Text className="text-sm text-red-700">Could not load SOP history.</Text><TouchableOpacity onPress={() => query.refetch()} className="mt-2 min-h-12 justify-center"><Text className="font-bold text-[#087F5B]">Retry</Text></TouchableOpacity></View> : pager.total === 0 ? <Text className="rounded-xl bg-white p-4 text-sm text-[#6B7F77]">No past versions.</Text> : pager.items.map((version, index) => <TouchableOpacity key={version.id} onPress={() => setSelectedHistory(version)} className="mb-3 min-h-16 flex-row items-center gap-3 rounded-2xl border border-[#E2EAE5] bg-white p-4" accessibilityRole="button" accessibilityLabel={`Open SOP version ${version.title}`}><View className="flex-1"><Text className="text-sm font-bold text-[#16352B]" numberOfLines={1}>{version.title}</Text><Text className="mt-1 text-xs text-[#6B7F77]">{dateLabel(version.created_at)}</Text></View>{pager.page === 1 && index === 0 && <Text className="text-xs font-semibold text-[#087F5B]">Current</Text>}<ChevronRight color="#6B7F77" size={18} /></TouchableOpacity>)}
        <MobilePagination page={pager.page} pages={pager.pages} total={pager.total} onPrevious={pager.previous} onNext={() => void pager.next()} loading={query.isFetchingNextPage} error={query.isFetchNextPageError} />
      </>}
    </ScrollView>
  </View>;
}

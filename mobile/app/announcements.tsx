import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { useLocalSearchParams } from 'expo-router';
import { ClipboardList, History, Megaphone, X } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Modal,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../lib/api';
import { PaginatedListFooter } from '../components/PaginatedListFooter';
import { MOBILE_PAGE_SIZE, PaginatedResponse, flattenUniquePages, getNextPageParam } from '../lib/pagination';

interface Sop {
  id: number;
  title: string;
  body: string;
  author?: { id: number; name: string; role: string } | null;
  created_at: string;
}

interface Announcement {
  id: number;
  title: string;
  body: string;
  category: string;
  visibility: string;
  pinned: boolean;
  author?: { name: string; role: string } | null;
  created_at: string;
}

// Category pill colors — mirror the website's categoryStyles.
const CATEGORY: Record<string, { bg: string; text: string }> = {
  General: { bg: 'bg-zinc-100', text: 'text-zinc-700' },
  Event: { bg: 'bg-orange-50', text: 'text-orange-700' },
  Operational: { bg: 'bg-emerald-50', text: 'text-emerald-700' },
  Urgent: { bg: 'bg-red-50', text: 'text-red-700' },
  Memo: { bg: 'bg-sky-50', text: 'text-sky-700' },
};

function fmt(date: string): string {
  return new Date(date).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

async function fetchSop(): Promise<Sop | null> {
  const res = await api.get<{ data: Sop | null }>('/api/sop');
  return res.data.data;
}

async function fetchSopHistory(page: number): Promise<PaginatedResponse<Sop>> {
  const res = await api.get<PaginatedResponse<Sop>>('/api/sop/history', { params: { page, per_page: MOBILE_PAGE_SIZE } });
  return res.data;
}

async function fetchAnnouncements(page: number): Promise<PaginatedResponse<Announcement>> {
  const res = await api.get<PaginatedResponse<Announcement>>('/api/fss/announcements', {
    params: { page, per_page: MOBILE_PAGE_SIZE },
  });
  return res.data;
}

function SopBanner() {
  const [historyOpen, setHistoryOpen] = useState(false);
  const { data: sop, isLoading } = useQuery({ queryKey: ['sop'], queryFn: fetchSop });
  const { data: historyPages, isLoading: histLoading, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['sop-history'],
    queryFn: ({ pageParam }) => fetchSopHistory(pageParam),
    initialPageParam: 1,
    getNextPageParam,
    enabled: historyOpen,
  });
  const history = flattenUniquePages(historyPages?.pages);

  if (isLoading) {
    return <View className="h-24 rounded-2xl bg-gray-100 mb-4" />;
  }
  if (!sop) return null;

  return (
    <View className="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 mb-4">
      <View className="flex-row items-start justify-between gap-2">
        <View className="flex-row items-center gap-2.5 flex-1">
          <View className="h-9 w-9 items-center justify-center rounded-xl bg-emerald-600">
            <ClipboardList color="#ffffff" size={18} />
          </View>
          <View className="flex-1">
            <Text className="text-[10px] font-extrabold uppercase tracking-widest text-emerald-700">
              Standard Operating Procedure
            </Text>
            {sop.author && (
              <Text className="text-[10px] font-semibold text-emerald-600/80">
                {sop.author.name} · {sop.author.role} · {fmt(sop.created_at)}
              </Text>
            )}
          </View>
        </View>
        <TouchableOpacity
          onPress={() => setHistoryOpen(true)}
          className="flex-row items-center gap-1 px-2.5 py-1.5 rounded-lg border border-emerald-200"
        >
          <History color="#047857" size={14} />
          <Text className="text-[10px] font-bold uppercase tracking-wider text-emerald-700">History</Text>
        </TouchableOpacity>
      </View>

      <Text className="text-sm font-extrabold text-gray-900 mt-3">{sop.title}</Text>
      <Text className="text-xs text-gray-700 leading-6 mt-1">{sop.body}</Text>

      <Modal visible={historyOpen} animationType="slide" transparent onRequestClose={() => setHistoryOpen(false)}>
        <View className="flex-1 bg-black/40 justify-end">
          <View className="bg-white rounded-t-3xl max-h-[80%]">
            <View className="flex-row items-center justify-between px-5 py-4 border-b border-gray-100">
              <Text className="text-xs font-bold uppercase tracking-widest text-gray-900">SOP History</Text>
              <TouchableOpacity onPress={() => setHistoryOpen(false)} hitSlop={8}>
                <X color="#374151" size={20} />
              </TouchableOpacity>
            </View>
            <ScrollView contentContainerStyle={{ padding: 16 }}>
              {histLoading ? (
                <ActivityIndicator color="#059669" />
              ) : (
                history.map((v, i) => (
                  <View key={v.id} className="rounded-2xl border border-gray-200 p-4 mb-3">
                    <View className="flex-row items-center justify-between">
                      <Text className="text-sm font-bold text-gray-900">{v.title}</Text>
                      {i === 0 && (
                        <View className="px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200">
                          <Text className="text-[9px] font-extrabold uppercase tracking-wider text-emerald-700">Current</Text>
                        </View>
                      )}
                    </View>
                    <Text className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 mt-0.5">
                      {v.author?.name} · {v.author?.role} · {fmt(v.created_at)}
                    </Text>
                    <Text className="text-xs text-gray-600 leading-6 mt-2">{v.body}</Text>
                  </View>
                ))
              )}
              {hasNextPage && (
                <TouchableOpacity className="min-h-12 items-center justify-center" onPress={() => void fetchNextPage()}>
                  <PaginatedListFooter loading={isFetchingNextPage} error={isFetchNextPageError} onRetry={() => void fetchNextPage()} />
                  {!isFetchingNextPage && !isFetchNextPageError && <Text className="text-sm font-semibold text-emerald-700">Load more</Text>}
                </TouchableOpacity>
              )}
            </ScrollView>
          </View>
        </View>
      </Modal>
    </View>
  );
}

export default function AnnouncementsScreen() {
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ announcementId?: string }>();
  // Announcement ids are public uuids (strings) — match as strings, never Number().
  const targetAnnouncementId = params.announcementId || null;
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const { data: pages, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['announcements-feed'],
    queryFn: ({ pageParam }) => fetchAnnouncements(pageParam),
    initialPageParam: 1,
    getNextPageParam,
  });
  const data = flattenUniquePages(pages?.pages);
  const selected = data.find((item) => String(item.id) === selectedId) ?? null;

  useEffect(() => {
    if (!targetAnnouncementId || selectedId === targetAnnouncementId) return;
    if (data.some((item) => String(item.id) === targetAnnouncementId)) {
      setSelectedId(targetAnnouncementId);
    } else if (hasNextPage && !isFetchingNextPage) {
      void fetchNextPage();
    }
  }, [data, fetchNextPage, hasNextPage, isFetchingNextPage, selectedId, targetAnnouncementId]);

  if (isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#059669" />
        <Text className="mt-3 text-gray-500 text-sm">Loading…</Text>
      </View>
    );
  }

  if (isError) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50 px-6">
        <Megaphone color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">Could not load announcements</Text>
        <TouchableOpacity className="mt-5 bg-emerald-600 px-6 py-3 rounded-lg" onPress={() => refetch()}>
          <Text className="text-white font-semibold">Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View className="flex-1 bg-gray-50">
      <FlatList
        data={data}
        keyExtractor={(item) => String(item.id)}
        ListHeaderComponent={<SopBanner />}
        contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16, flexGrow: 1 }}
        renderItem={({ item }) => {
          const cat = CATEGORY[item.category] ?? CATEGORY.General;
          return (
            <TouchableOpacity
              className="rounded-2xl border border-gray-200 bg-white p-4 mb-3"
              activeOpacity={0.8}
              onPress={() => setSelectedId(String(item.id))}
            >
              <View className="flex-row items-center justify-between gap-2">
                <Text className="text-[10px] font-semibold uppercase tracking-wider text-gray-400 flex-1">
                  {item.author?.name ?? 'Staff'} · {fmt(item.created_at)}
                </Text>
                <View className="flex-row items-center gap-1.5">
                  {item.pinned && (
                    <View className="px-2 py-0.5 rounded-full bg-orange-50 border border-orange-200">
                      <Text className="text-[9px] font-extrabold uppercase tracking-wider text-orange-700">Pinned</Text>
                    </View>
                  )}
                  <View className={`px-2 py-0.5 rounded-full ${cat.bg}`}>
                    <Text className={`text-[9px] font-extrabold uppercase tracking-wider ${cat.text}`}>{item.category}</Text>
                  </View>
                </View>
              </View>
              <Text className="text-sm font-bold text-gray-900 mt-2">{item.title}</Text>
              <Text className="text-xs text-gray-600 leading-6 mt-1">{item.body}</Text>
            </TouchableOpacity>
          );
        }}
        onEndReached={() => { if (hasNextPage && !isFetchingNextPage) void fetchNextPage(); }}
        onEndReachedThreshold={0.4}
        ListFooterComponent={<PaginatedListFooter loading={isFetchingNextPage} error={isFetchNextPageError} onRetry={() => void fetchNextPage()} />}
        ListEmptyComponent={
          <View className="items-center justify-center py-20">
            <Megaphone color="#d1d5db" size={40} />
            <Text className="mt-4 text-gray-400 text-sm">No announcements</Text>
          </View>
        }
      />
      <Modal visible={selected !== null} animationType="slide" transparent onRequestClose={() => setSelectedId(null)}>
        <View className="flex-1 bg-black/40 justify-end">
          <View className="bg-white rounded-t-3xl max-h-[82%]">
            <View className="flex-row items-center justify-between px-5 py-4 border-b border-gray-100">
              <Text className="text-xs font-extrabold uppercase tracking-widest text-gray-900">Announcement</Text>
              <TouchableOpacity onPress={() => setSelectedId(null)} hitSlop={8}>
                <X color="#374151" size={20} />
              </TouchableOpacity>
            </View>
            {selected && (
              <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: insets.bottom + 24 }}>
                <Text className="text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                  {selected.author?.name ?? 'Staff'} - {fmt(selected.created_at)}
                </Text>
                <View className="flex-row flex-wrap gap-2 mt-3">
                  {selected.pinned && (
                    <View className="px-2 py-0.5 rounded-full bg-orange-50 border border-orange-200">
                      <Text className="text-[9px] font-extrabold uppercase tracking-wider text-orange-700">Pinned</Text>
                    </View>
                  )}
                  <View className={`px-2 py-0.5 rounded-full ${(CATEGORY[selected.category] ?? CATEGORY.General).bg}`}>
                    <Text className={`text-[9px] font-extrabold uppercase tracking-wider ${(CATEGORY[selected.category] ?? CATEGORY.General).text}`}>
                      {selected.category}
                    </Text>
                  </View>
                </View>
                <Text className="text-lg font-extrabold text-gray-900 mt-4">{selected.title}</Text>
                <Text className="text-sm text-gray-700 leading-7 mt-3">{selected.body}</Text>
              </ScrollView>
            )}
          </View>
        </View>
      </Modal>
    </View>
  );
}

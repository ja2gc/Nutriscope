import { useInfiniteQuery, useQuery } from '@tanstack/react-query';
import { router, type Href, useLocalSearchParams } from 'expo-router';
import { ChevronLeft, ClipboardList, History, Newspaper } from 'lucide-react-native';
import { useEffect, useRef, useState } from 'react';
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
import { MobilePagination } from './MobilePagination';
import { PaginatedResponse, flattenUniquePages, getNextPageParam } from '../lib/pagination';
import { useMobileServerPage } from '../lib/useMobileServerPage';
import { fetchSop } from '../lib/sop';
import { AnnouncementAuthorAvatar, AnnouncementMedia } from './AnnouncementMedia';
import { AnnouncementCard, CATEGORY, fmt, type Announcement } from './AnnouncementCard';

async function fetchAnnouncements(page: number): Promise<PaginatedResponse<Announcement>> {
  const res = await api.get<PaginatedResponse<Announcement>>('/api/fss/announcements', {
    params: { page, per_page: 10 },
  });
  return res.data;
}

function SopBanner() {
  const { data: sop, isLoading, isError, refetch } = useQuery({ queryKey: ['sop'], queryFn: fetchSop });

  if (isLoading) {
    return <View className="h-24 rounded-2xl bg-gray-100 mb-4" />;
  }
  if (isError) {
    return <View className="rounded-2xl border border-red-200 bg-red-50 p-4"><Text className="text-sm text-red-700">Could not load the current SOP.</Text><TouchableOpacity className="mt-2 min-h-11 justify-center" onPress={() => refetch()}><Text className="font-bold text-emerald-700">Retry</Text></TouchableOpacity></View>;
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
          onPress={() => router.push('/sop-history' as Href)}
          className="min-h-12 flex-row items-center gap-1 px-2.5 rounded-lg border border-emerald-200"
        >
          <History color="#047857" size={14} />
          <Text className="text-[10px] font-bold uppercase tracking-wider text-emerald-700">History</Text>
        </TouchableOpacity>
      </View>

      <Text className="text-sm font-extrabold text-gray-900 mt-3">{sop.title}</Text>
      <Text className="text-xs text-gray-700 leading-6 mt-1">{sop.body}</Text>

    </View>
  );
}

export default function AnnouncementsScreen() {
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ announcementId?: string }>();
  // Announcement ids are public uuids (strings) — match as strings, never Number().
  const targetAnnouncementId = params.announcementId || null;
  const consumedTargetAnnouncement = useRef<string | null>(null);
  const [section, setSection] = useState<'announcements' | 'sop'>('announcements');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const { data: pages, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['announcements-feed'],
    queryFn: ({ pageParam }) => fetchAnnouncements(pageParam),
    initialPageParam: 1,
    getNextPageParam,
  });
  const data = flattenUniquePages(pages?.pages);
  const feedPager = useMobileServerPage(pages, fetchNextPage);
  const selected = data.find((item) => String(item.id) === selectedId) ?? null;

  useEffect(() => {
    if (!targetAnnouncementId || consumedTargetAnnouncement.current === targetAnnouncementId) return;
    if (data.some((item) => String(item.id) === targetAnnouncementId)) {
      consumedTargetAnnouncement.current = targetAnnouncementId;
      const foundPage = pages?.pages.find((page) => page.data.some((item) => String(item.id) === targetAnnouncementId));
      if (foundPage) feedPager.setPage(foundPage.meta.current_page);
      setSelectedId(targetAnnouncementId);
    } else if (hasNextPage && !isFetchingNextPage) {
      void fetchNextPage();
    }
  }, [data, fetchNextPage, hasNextPage, isFetchingNextPage, selectedId, targetAnnouncementId, pages, feedPager.setPage]);

  if (section === 'announcements' && isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#059669" />
        <Text className="mt-3 text-gray-500 text-sm">Loading…</Text>
      </View>
    );
  }

  if (section === 'announcements' && isError) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50 px-6">
        <Newspaper color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">Could not load announcements</Text>
        <TouchableOpacity className="mt-5 bg-emerald-600 px-6 py-3 rounded-lg" onPress={() => refetch()}>
          <Text className="text-white font-semibold">Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View className="flex-1 bg-gray-50">
      <View className="mx-4 mt-4 flex-row rounded-xl bg-gray-200 p-1" accessibilityRole="tablist">
        <TouchableOpacity
          className={`min-h-11 flex-1 items-center justify-center rounded-lg ${section === 'announcements' ? 'bg-white' : ''}`}
          accessibilityRole="tab"
          accessibilityState={{ selected: section === 'announcements' }}
          onPress={() => setSection('announcements')}
        >
          <Text className={`text-sm font-bold ${section === 'announcements' ? 'text-emerald-700' : 'text-gray-600'}`}>
            Announcements
          </Text>
        </TouchableOpacity>
        <TouchableOpacity
          className={`min-h-11 flex-1 items-center justify-center rounded-lg ${section === 'sop' ? 'bg-white' : ''}`}
          accessibilityRole="tab"
          accessibilityState={{ selected: section === 'sop' }}
          onPress={() => setSection('sop')}
        >
          <Text className={`text-sm font-bold ${section === 'sop' ? 'text-emerald-700' : 'text-gray-600'}`}>SOP</Text>
        </TouchableOpacity>
      </View>

      {section === 'sop' ? (
        <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16 }}>
          <SopBanner />
        </ScrollView>
      ) : (
        <FlatList
          data={feedPager.items}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 16, flexGrow: 1 }}
        renderItem={({ item }) => <AnnouncementCard item={item} onPress={() => setSelectedId(String(item.id))} />}
        ListFooterComponent={<MobilePagination page={feedPager.page} pages={feedPager.pages} total={feedPager.total} onPrevious={feedPager.previous} onNext={() => void feedPager.next()} loading={isFetchingNextPage} error={isFetchNextPageError} />}
        ListEmptyComponent={
          <View className="items-center justify-center py-20">
            <Newspaper color="#d1d5db" size={40} />
            <Text className="mt-4 text-gray-400 text-sm">No announcements</Text>
          </View>
        }
        />
      )}
      <Modal visible={selected !== null} animationType="slide" presentationStyle="fullScreen" onRequestClose={() => setSelectedId(null)}>
        <View className="flex-1 bg-white" style={{ paddingTop: insets.top }}>
            <View className="min-h-14 flex-row items-center gap-3 px-4 border-b border-gray-100">
              <TouchableOpacity onPress={() => setSelectedId(null)} className="min-h-12 flex-row items-center gap-1 pr-3" accessibilityRole="button" accessibilityLabel="Back to announcements">
                <ChevronLeft color="#087F5B" size={22} />
                <Text className="text-sm font-bold text-emerald-700">Back</Text>
              </TouchableOpacity>
              <Text className="text-sm font-extrabold text-gray-900">Announcement</Text>
            </View>
            {selected && (
              <ScrollView contentContainerStyle={{ padding: 20, paddingBottom: insets.bottom + 24 }}>
                <View className="flex-row items-center gap-3">
                  <AnnouncementAuthorAvatar author={selected.author} />
                  <Text className="flex-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
                    {selected.author?.name ?? 'Staff'} - {fmt(selected.created_at)}
                  </Text>
                </View>
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
                <AnnouncementMedia
                  attachments={selected.attachments?.length ? selected.attachments : (selected.attachment ? [selected.attachment] : [])}
                  title={selected.title}
                />
              </ScrollView>
            )}
        </View>
      </Modal>
    </View>
  );
}

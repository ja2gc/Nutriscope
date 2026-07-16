import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AlertCircle,
  Bell,
  CheckCircle,
  Info,
  ShoppingCart,
  TriangleAlert,
} from 'lucide-react-native';
import { useCallback } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../lib/api';
import { PaginatedListFooter } from '../components/PaginatedListFooter';
import { MOBILE_PAGE_SIZE, PaginatedResponse, flattenUniquePages, getNextPageParam, mapPageItems } from '../lib/pagination';

interface Notification {
  id: number;
  title: string;
  message: string;
  type: string;
  source_module: string | null;
  source_id: number | null;
  // Public uuid of the source record — deep-links address the target by uuid.
  source_uuid: string | null;
  read: boolean;
  created_at: string;
}

async function fetchNotifications(page: number): Promise<PaginatedResponse<Notification>> {
  const res = await api.get<PaginatedResponse<Notification>>('/api/notifications', {
    params: { page, per_page: MOBILE_PAGE_SIZE },
  });
  return res.data;
}

async function markRead(id: number): Promise<void> {
  await api.patch(`/api/notifications/${id}/read`);
}

async function markAllRead(): Promise<void> {
  await api.patch('/api/notifications/read-all');
}

async function fetchUnreadCount(): Promise<number> {
  const res = await api.get<{ count: number }>('/api/notifications/unread-count');
  return res.data.count ?? 0;
}

function relativeTime(dateStr: string): string {
  const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
  if (diff < 60) return `${diff}s ago`;
  if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
  if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
  return `${Math.floor(diff / 86400)}d ago`;
}

function NotifIcon({ type }: { type: string }) {
  const lower = type.toLowerCase();
  const size = 18;
  if (lower.includes('warning'))
    return <TriangleAlert color="#d97706" size={size} />;
  if (lower.includes('error') || lower.includes('critical'))
    return <AlertCircle color="#dc2626" size={size} />;
  if (lower.includes('success') || lower.includes('complete'))
    return <CheckCircle color="#16a34a" size={size} />;
  if (lower.includes('order') || lower.includes('procurement'))
    return <ShoppingCart color="#059669" size={size} />;
  if (lower.includes('info'))
    return <Info color="#6b7280" size={size} />;
  return <Bell color="#6b7280" size={size} />;
}

function openNotificationTarget(notification: Notification) {
  const type = `${notification.type ?? ''} ${notification.source_module ?? ''}`.toLowerCase();
  // Deep-links address the target by its public uuid; source_id is the raw internal FK.
  const sourceId = notification.source_uuid ?? notification.source_id;

  if (sourceId && type.includes('announcement')) {
    router.push({ pathname: '/announcements', params: { announcementId: String(sourceId) } } as never);
    return;
  }

  if (sourceId && (type.includes('po') || type.includes('purchase') || type.includes('food_service'))) {
    router.push({ pathname: '/(tabs)/procurement', params: { poId: String(sourceId) } } as never);
    return;
  }

  if (type.includes('report') || type.includes('accomplishment')) {
    router.push('/reports');
    return;
  }
}

export default function NotificationsScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const { data, isLoading, isError, refetch, fetchNextPage, hasNextPage, isFetchingNextPage, isFetchNextPageError } = useInfiniteQuery({
    queryKey: ['notifications'],
    queryFn: ({ pageParam }) => fetchNotifications(pageParam),
    initialPageParam: 1,
    getNextPageParam,
  });
  const notifications = flattenUniquePages(data?.pages);
  const { data: unreadCount = 0 } = useQuery({
    queryKey: ['notifications-unread-count'],
    queryFn: fetchUnreadCount,
  });

  const readMutation = useMutation({
    mutationFn: markRead,
    onMutate: async (id) => {
      await queryClient.cancelQueries({ queryKey: ['notifications'] });
      const prev = queryClient.getQueryData<typeof data>(['notifications']);
      queryClient.setQueryData<typeof data>(['notifications'], (old) =>
        old ? mapPageItems(old, (n) => (n.id === id ? { ...n, read: true } : n)) : old,
      );
      return { prev };
    },
    onError: (_err, _id, ctx) => {
      if (ctx?.prev) queryClient.setQueryData(['notifications'], ctx.prev);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
    },
  });

  const readAllMutation = useMutation({
    mutationFn: markAllRead,
    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: ['notifications'] });
      const prev = queryClient.getQueryData<typeof data>(['notifications']);
      queryClient.setQueryData<typeof data>(['notifications'], (old) =>
        old ? mapPageItems(old, (n) => ({ ...n, read: true })) : old,
      );
      return { prev };
    },
    onError: (_err, _vars, ctx) => {
      if (ctx?.prev) queryClient.setQueryData(['notifications'], ctx.prev);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      queryClient.invalidateQueries({ queryKey: ['notifications-unread-count'] });
    },
  });

  const renderItem = useCallback(
    ({ item }: { item: Notification }) => (
      <TouchableOpacity
        onPress={() => {
          if (!item.read) readMutation.mutate(item.id);
          openNotificationTarget(item);
        }}
        activeOpacity={0.7}
        className={`flex-row items-start px-4 py-4 border-b border-gray-100 ${
          item.read ? 'bg-white' : 'bg-emerald-50'
        }`}
      >
        {/* Unread dot */}
        <View className="mt-1 mr-3 w-2 h-2 rounded-full" style={{ backgroundColor: item.read ? 'transparent' : '#059669' }} />

        {/* Icon */}
        <View className="mt-0.5 mr-3">
          <NotifIcon type={item.type} />
        </View>

        {/* Content */}
        <View className="flex-1">
          <Text className={`text-sm font-semibold ${item.read ? 'text-gray-700' : 'text-gray-900'}`}>
            {item.title}
          </Text>
          <Text className="text-sm text-gray-500 mt-0.5" numberOfLines={2}>
            {item.message}
          </Text>
          <Text className="text-xs text-gray-400 mt-1 tabular-nums">
            {relativeTime(item.created_at)}
          </Text>
        </View>
      </TouchableOpacity>
    ),
    [readMutation],
  );

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
        <AlertCircle color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">Could not load notifications</Text>
        <TouchableOpacity
          className="mt-5 bg-emerald-600 px-6 py-3 rounded-lg"
          onPress={() => refetch()}
        >
          <Text className="text-white font-semibold">Retry</Text>
        </TouchableOpacity>
      </View>
    );
  }

  return (
    <View className="flex-1 bg-gray-50">
      {/* Mark all read button */}
      {unreadCount > 0 && (
        <View className="px-4 py-3 bg-white border-b border-gray-100">
          <TouchableOpacity
            onPress={() => readAllMutation.mutate()}
            disabled={readAllMutation.isPending}
            className="self-end"
          >
            <Text className="text-emerald-600 text-sm font-medium">
              {readAllMutation.isPending ? 'Marking…' : `Mark all read (${unreadCount})`}
            </Text>
          </TouchableOpacity>
        </View>
      )}

      <FlatList
        data={notifications}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
        onEndReached={() => { if (hasNextPage && !isFetchingNextPage) void fetchNextPage(); }}
        onEndReachedThreshold={0.4}
        ListFooterComponent={<PaginatedListFooter loading={isFetchingNextPage} error={isFetchNextPageError} onRetry={() => void fetchNextPage()} />}
        contentContainerStyle={{ paddingBottom: insets.bottom + 16, flexGrow: 1 }}
        ListEmptyComponent={
          <View className="flex-1 items-center justify-center py-20">
            <Bell color="#d1d5db" size={40} />
            <Text className="mt-4 text-gray-400 text-sm">No notifications</Text>
          </View>
        }
      />
    </View>
  );
}

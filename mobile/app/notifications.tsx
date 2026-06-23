import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
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

interface Notification {
  id: number;
  title: string;
  message: string;
  type: string;
  source_module: string | null;
  source_id: number | null;
  read: boolean;
  created_at: string;
}

async function fetchNotifications(): Promise<Notification[]> {
  const res = await api.get<{ data: Notification[] }>('/api/notifications');
  return res.data.data;
}

async function markRead(id: number): Promise<void> {
  await api.patch(`/api/notifications/${id}/read`);
}

async function markAllRead(): Promise<void> {
  await api.patch('/api/notifications/read-all');
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
  if (lower.includes('warning') || lower.includes('shortfall'))
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

export default function NotificationsScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const { data, isLoading, isError, refetch } = useQuery({
    queryKey: ['notifications'],
    queryFn: fetchNotifications,
  });

  const readMutation = useMutation({
    mutationFn: markRead,
    onMutate: async (id) => {
      await queryClient.cancelQueries({ queryKey: ['notifications'] });
      const prev = queryClient.getQueryData<Notification[]>(['notifications']);
      queryClient.setQueryData<Notification[]>(['notifications'], (old) =>
        old?.map((n) => (n.id === id ? { ...n, read: true } : n)),
      );
      return { prev };
    },
    onError: (_err, _id, ctx) => {
      if (ctx?.prev) queryClient.setQueryData(['notifications'], ctx.prev);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const readAllMutation = useMutation({
    mutationFn: markAllRead,
    onMutate: async () => {
      await queryClient.cancelQueries({ queryKey: ['notifications'] });
      const prev = queryClient.getQueryData<Notification[]>(['notifications']);
      queryClient.setQueryData<Notification[]>(['notifications'], (old) =>
        old?.map((n) => ({ ...n, read: true })),
      );
      return { prev };
    },
    onError: (_err, _vars, ctx) => {
      if (ctx?.prev) queryClient.setQueryData(['notifications'], ctx.prev);
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
    },
  });

  const unreadCount = data?.filter((n) => !n.read).length ?? 0;

  const renderItem = useCallback(
    ({ item }: { item: Notification }) => (
      <TouchableOpacity
        onPress={() => {
          if (!item.read) readMutation.mutate(item.id);
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
        data={data ?? []}
        keyExtractor={(item) => String(item.id)}
        renderItem={renderItem}
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

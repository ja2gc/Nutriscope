import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useCallback } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';
import { todayManilaKey } from '../../lib/localDate';

interface DashboardData {
  meals_to_log_today: number;
  pending_pos_count: number;
  today_service: { id: string; name: string; prepped: boolean }[];
  active_cycle: { id: string; name: string; service_day_count: number } | null;
}

interface Announcement {
  id: string | number;
  title: string;
  attachment?: string | null;
  attachments?: string[];
}

async function fetchDashboard(): Promise<DashboardData> {
  const response = await api.get<{ data: DashboardData }>('/api/fss/dashboard/summary');
  return response.data.data;
}

async function fetchLatestAnnouncements(): Promise<Announcement[]> {
  const response = await api.get<{ data: Announcement[] }>('/api/fss/announcements', { params: { per_page: 2, sort: 'latest' } });
  return response.data.data;
}

async function hasTodayLog(date: string): Promise<boolean> {
  const response = await api.get<{ data: { ward: string }[] }>('/api/fss/diet-list-counts', { params: { from: date, to: date } });
  return response.data.data.some((row) => row.ward === 'Accomplishment report');
}

export default function DashboardScreen() {
  const insets = useSafeAreaInsets();
  const today = todayManilaKey();
  const dashboard = useQuery({ queryKey: ['fss-dashboard'], queryFn: fetchDashboard, staleTime: 60_000 });
  const announcement = useQuery({ queryKey: ['fss-latest-announcements'], queryFn: fetchLatestAnnouncements, staleTime: 120_000 });
  const dailyLog = useQuery({ queryKey: ['fss-dashboard-daily-log', today], queryFn: () => hasTodayLog(today), staleTime: 30_000 });
  const onRefresh = useCallback(() => { void dashboard.refetch(); void announcement.refetch(); void dailyLog.refetch(); }, [dashboard, announcement, dailyLog]);

  if (dashboard.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator color="#087F5B" /><Text className="mt-2 text-sm text-[#6B7F77]">Loading dashboard…</Text></View>;
  if (dashboard.isError || !dashboard.data) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Text className="text-center text-sm text-red-700">Could not load dashboard.</Text><TouchableOpacity onPress={() => dashboard.refetch()} className="mt-4 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6"><Text className="font-bold text-white">Retry</Text></TouchableOpacity></View>;

  const data = dashboard.data;
  const latestAnnouncements = announcement.data ?? [];
  const next = !dailyLog.data && !dailyLog.isLoading && !dailyLog.isError
    ? { label: "Record today's Daily Log", path: '/(tabs)/accomplishments' as const }
    : data.meals_to_log_today > 0
      ? { label: 'Record served population', path: '/(tabs)/prep' as const }
      : data.pending_pos_count > 0
        ? { label: 'Continue procurement', path: '/(tabs)/procurement' as const }
        : { label: 'Review today’s menu', path: '/(tabs)/menu' as const };
  const todayItems = data.today_service.map((row) => row.name);

  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 20 }} refreshControl={<RefreshControl refreshing={dashboard.isFetching || announcement.isFetching || dailyLog.isFetching} onRefresh={onRefresh} tintColor="#087F5B" />}>
    <View className="flex-row gap-3">
      <TouchableOpacity onPress={() => router.push('/(tabs)/prep')} className="min-h-24 flex-1 justify-center rounded-2xl border border-[#E2EAE5] bg-white p-4" accessibilityRole="button" accessibilityLabel={`${data.meals_to_log_today} meals to record, open Meal Prep`}><Text className="text-xs font-semibold text-[#6B7F77]">Meals to record</Text><Text className="mt-1 text-2xl font-extrabold text-[#16352B]">{data.meals_to_log_today}</Text></TouchableOpacity>
      <TouchableOpacity onPress={() => router.push('/(tabs)/procurement')} className="min-h-24 flex-1 justify-center rounded-2xl border border-[#E2EAE5] bg-white p-4" accessibilityRole="button" accessibilityLabel={`${data.pending_pos_count} pending purchase orders, open Procurement`}><Text className="text-xs font-semibold text-[#6B7F77]">Pending POs</Text><Text className="mt-1 text-2xl font-extrabold text-[#16352B]">{data.pending_pos_count}</Text></TouchableOpacity>
    </View>

    <TouchableOpacity onPress={() => router.push('/(tabs)/menu')} className="mt-3 min-h-16 justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3" accessibilityRole="button" accessibilityLabel="Open this week's menu"><Text className="text-xs font-bold uppercase tracking-wide text-emerald-700">This week’s menu</Text><Text className="mt-1 text-sm font-semibold text-[#16352B]" numberOfLines={1}>{data.active_cycle?.service_day_count ? `${data.active_cycle.name} · ${data.active_cycle.service_day_count} planned days` : 'Menu not planned for this week yet.'}</Text></TouchableOpacity>

    <View className="mt-3 rounded-2xl border border-[#E2EAE5] bg-white p-4"><Text className="text-xs font-bold uppercase tracking-wide text-[#6B7F77]">Next action</Text><TouchableOpacity onPress={() => router.push(next.path)} className="mt-1 min-h-12 justify-center" accessibilityRole="button"><Text className="text-sm font-bold text-[#087F5B]">{next.label}</Text></TouchableOpacity></View>

    <TouchableOpacity onPress={() => router.push('/(tabs)/menu')} className="mt-3 min-h-16 justify-center rounded-2xl border border-[#E2EAE5] bg-white px-4 py-3" accessibilityRole="button" accessibilityLabel="Open today's menu"><Text className="text-xs font-bold uppercase tracking-wide text-[#6B7F77]">Today’s meals</Text><Text className="mt-1 text-sm text-[#30483F]" numberOfLines={1}>{todayItems.length ? `${todayItems.slice(0, 2).join(', ')}${todayItems.length > 2 ? ` +${todayItems.length - 2} more` : ''}` : 'No meals planned for today.'}</Text></TouchableOpacity>

    <View className="mt-3 rounded-2xl border border-[#E2EAE5] bg-white px-4 py-3"><View className="flex-row items-center justify-between"><Text className="text-xs font-bold uppercase tracking-wide text-[#6B7F77]">Announcements</Text><TouchableOpacity onPress={() => router.push('/(tabs)/announcements')} className="min-h-12 justify-center" accessibilityRole="button" accessibilityLabel="View all announcements"><Text className="text-xs font-bold text-[#087F5B]">See all</Text></TouchableOpacity></View>{announcement.isLoading ? <ActivityIndicator color="#087F5B" /> : announcement.isError ? <TouchableOpacity onPress={() => announcement.refetch()} className="min-h-12 justify-center"><Text className="text-sm text-red-700">Could not load. Retry</Text></TouchableOpacity> : latestAnnouncements.length ? latestAnnouncements.map((item, index) => <TouchableOpacity key={String(item.id)} onPress={() => router.push({ pathname: '/(tabs)/announcements', params: { announcementId: String(item.id) } })} className={`min-h-12 flex-row items-center gap-3 ${index > 0 ? 'border-t border-[#E2EAE5]' : ''}`} accessibilityRole="button" accessibilityLabel={`View announcement: ${item.title}`}><Text className="min-w-0 flex-1 text-sm text-[#30483F]" numberOfLines={1}>{item.title}</Text><Text className="text-xs font-bold text-[#087F5B]">{item.attachments?.length || item.attachment ? 'View image' : 'View'}</Text></TouchableOpacity>) : <Text className="mt-1 text-sm text-[#6B7F77]">No announcements.</Text>}</View>
  </ScrollView>;
}

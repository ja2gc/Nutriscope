import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useCallback } from 'react';
import { ActivityIndicator, RefreshControl, ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';
import { todayManilaKey } from '../../lib/localDate';
import { listAccomplishmentPeriods } from '../../lib/reports';
import { AnnouncementCard, type Announcement } from '../../components/AnnouncementCard';

interface DashboardData {
  meals_to_log_today: number;
  pending_pos_count: number;
  today_service: { id: string; name: string; meal_type: string; prepped: boolean }[];
  active_cycle: { id: string; name: string; service_day_count: number } | null;
}

async function fetchDashboard(): Promise<DashboardData> {
  const response = await api.get<{ data: DashboardData }>('/api/fss/dashboard/summary');
  return response.data.data;
}

async function fetchLatestAnnouncements(): Promise<Announcement[]> {
  const response = await api.get<{ data: Announcement[] }>('/api/fss/announcements', { params: { per_page: 3, sort: 'latest' } });
  return response.data.data;
}

export default function DashboardScreen() {
  const insets = useSafeAreaInsets();
  const today = todayManilaKey();
  const dashboard = useQuery({ queryKey: ['fss-dashboard'], queryFn: fetchDashboard, staleTime: 60_000 });
  const announcement = useQuery({ queryKey: ['fss-latest-announcements'], queryFn: fetchLatestAnnouncements, staleTime: 120_000 });
  const periods = useQuery({ queryKey: ['fss-accomplishment-periods'], queryFn: listAccomplishmentPeriods, staleTime: 30_000 });
  const onRefresh = useCallback(() => { void dashboard.refetch(); void announcement.refetch(); void periods.refetch(); }, [dashboard, announcement, periods]);

  if (dashboard.isLoading) return <View className="flex-1 items-center justify-center bg-[#F4F7F5]"><ActivityIndicator color="#087F5B" /><Text className="mt-2 text-sm text-[#6B7F77]">Loading dashboard…</Text></View>;
  if (dashboard.isError || !dashboard.data) return <View className="flex-1 items-center justify-center bg-[#F4F7F5] px-6"><Text className="text-center text-sm text-red-700">Could not load dashboard.</Text><TouchableOpacity onPress={() => dashboard.refetch()} className="mt-4 min-h-12 justify-center rounded-xl bg-[#087F5B] px-6"><Text className="font-bold text-white">Retry</Text></TouchableOpacity></View>;

  const data = dashboard.data;
  const latestAnnouncements = announcement.data ?? [];
  const logsDue = periods.data?.reduce((total, period) => total + period.days.filter((day) => day.date <= today && !day.logged).length, 0);
  return <ScrollView className="flex-1 bg-[#F4F7F5]" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 20 }} refreshControl={<RefreshControl refreshing={dashboard.isFetching || announcement.isFetching || periods.isFetching} onRefresh={onRefresh} tintColor="#087F5B" />}>
    <View className="flex-row gap-2">
      <TouchableOpacity onPress={() => router.push('/(tabs)/prep')} className="min-h-20 flex-1 rounded-2xl border border-[#E2EAE5] bg-white px-3 py-2" accessibilityRole="button" accessibilityLabel={`${data.meals_to_log_today} meals to record, open Meal Prep`}><Text className="text-[11px] font-semibold text-[#6B7F77]">Meals to record</Text><Text className="text-xl font-extrabold text-[#16352B]">{data.meals_to_log_today}</Text><Text className="text-[10px] font-bold text-[#087F5B]">View ›</Text></TouchableOpacity>
      <TouchableOpacity onPress={() => router.push('/(tabs)/accomplishments')} className="min-h-20 flex-1 rounded-2xl border border-[#E2EAE5] bg-white px-3 py-2" accessibilityRole="button" accessibilityLabel={`${logsDue ?? 'Unknown'} logs due, open Accomplish`}><Text className="text-[11px] font-semibold text-[#6B7F77]">Logs due</Text><Text className="text-xl font-extrabold text-[#16352B]">{logsDue ?? '—'}</Text><Text className="text-[10px] font-bold text-[#087F5B]">View ›</Text></TouchableOpacity>
      <TouchableOpacity onPress={() => router.push('/(tabs)/procurement')} className="min-h-20 flex-1 rounded-2xl border border-[#E2EAE5] bg-white px-3 py-2" accessibilityRole="button" accessibilityLabel={`${data.pending_pos_count} pending purchase orders, open Procurement`}><Text className="text-[11px] font-semibold text-[#6B7F77]">Pending POs</Text><Text className="text-xl font-extrabold text-[#16352B]">{data.pending_pos_count}</Text><Text className="text-[10px] font-bold text-[#087F5B]">View ›</Text></TouchableOpacity>
    </View>

    <TouchableOpacity onPress={() => router.push('/(tabs)/menu')} className="mt-3 min-h-16 justify-center rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3" accessibilityRole="button" accessibilityLabel="Open this week's menu"><Text className="text-xs font-bold uppercase tracking-wide text-emerald-700">This week’s menu</Text><Text className="mt-1 text-sm font-semibold text-[#16352B]" numberOfLines={1}>{data.active_cycle?.service_day_count ? `${data.active_cycle.name} · ${data.active_cycle.service_day_count} planned days` : 'Menu not planned for this week yet.'}</Text></TouchableOpacity>

    <TouchableOpacity onPress={() => router.push('/(tabs)/menu')} className="mt-3 rounded-2xl border border-[#E2EAE5] bg-white px-3 py-2" accessibilityRole="button" accessibilityLabel="Open today's menu">
      <Text className="text-xs font-bold uppercase tracking-wide text-[#6B7F77]">Today’s meals</Text>
      {data.today_service.length ? <View className="mt-1 flex-row flex-wrap">
        {data.today_service.map((row) => <View key={row.id} className="w-1/2 border-t border-[#E2EAE5] px-1 py-1.5">
          <Text className="text-[10px] font-semibold uppercase text-[#6B7F77]">{row.meal_type.replace(/_/g, ' ')}</Text>
          <Text className="text-xs text-[#30483F]" numberOfLines={2}>{row.name}</Text>
        </View>)}
      </View> : <Text className="mt-1 text-sm text-[#6B7F77]">No meals planned for today.</Text>}
    </TouchableOpacity>

    <View className="mt-3">
      <View className="flex-row items-center justify-between px-1"><Text className="text-xs font-bold uppercase tracking-wide text-[#6B7F77]">Announcements</Text><TouchableOpacity onPress={() => router.push('/(tabs)/announcements')} className="min-h-12 justify-center" accessibilityRole="button" accessibilityLabel="View all announcements"><Text className="text-xs font-bold text-[#087F5B]">See all</Text></TouchableOpacity></View>
      {announcement.isLoading ? <ActivityIndicator color="#087F5B" /> : announcement.isError ? <TouchableOpacity onPress={() => announcement.refetch()} className="min-h-12 justify-center"><Text className="text-sm text-red-700">Could not load. Retry</Text></TouchableOpacity> : latestAnnouncements.length ? latestAnnouncements.map((item) => <AnnouncementCard key={String(item.id)} item={item} onPress={() => router.push({ pathname: '/(tabs)/announcements', params: { announcementId: String(item.id) } })} />) : <Text className="mt-1 text-sm text-[#6B7F77]">No announcements.</Text>}
    </View>
  </ScrollView>;
}

import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import {
  AlertCircle,
  Bell,
  CalendarDays,
  ChevronRight,
  ClipboardList,
  Pin,
  ShoppingBag,
} from 'lucide-react-native';
import { useCallback } from 'react';
import {
  ActivityIndicator,
  RefreshControl,
  ScrollView,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';

interface ServiceRow {
  meal_type: string;
  name: string;
  prepped: boolean;
  has_shortfall: boolean;
}

interface PendingPo {
  id: number;
  po_number: string | null;
  procurement_track: 'food' | 'supplies';
  waiting_on: string[];
}

interface ActiveCycle {
  id: number;
  name: string;
  activation_date: string | null;
  service_day_count: number;
}

interface DashboardData {
  meals_to_log_today: number;
  pending_pos: PendingPo[];
  pending_pos_count: number;
  today_service: ServiceRow[];
  active_cycle: ActiveCycle | null;
}

interface AnnouncementAuthor {
  id: number | null;
  name: string | null;
  role: string | null;
}

interface Announcement {
  id: number;
  title: string;
  body: string;
  category: string | null;
  pinned: boolean;
  visibility: string;
  created_at: string;
  author: AnnouncementAuthor;
}

interface AnnouncementsMeta {
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

interface AnnouncementsResponse {
  data: Announcement[];
  meta: AnnouncementsMeta;
}

async function fetchDashboard(): Promise<DashboardData> {
  const res = await api.get<{ data: DashboardData }>('/api/fss/dashboard/summary');
  return res.data.data;
}

async function fetchAnnouncements(): Promise<Announcement[]> {
  const res = await api.get<AnnouncementsResponse>('/api/fss/announcements', {
    params: { per_page: 10 },
  });
  return res.data.data;
}

function relativeTime(isoString: string): string {
  const diffMs = Date.now() - new Date(isoString).getTime();
  const diffMins = Math.floor(diffMs / 60_000);
  if (diffMins < 1) return 'just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  const diffHrs = Math.floor(diffMins / 60);
  if (diffHrs < 24) return `${diffHrs}h ago`;
  const diffDays = Math.floor(diffHrs / 24);
  if (diffDays < 7) return `${diffDays}d ago`;
  return new Date(isoString).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
}

const WAITING_LABELS: Record<string, string> = {
  receipts: 'Needs receipts',
  served_population: 'Needs served population',
};

export default function DashboardScreen() {
  const insets = useSafeAreaInsets();

  const { data, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey: ['fss-dashboard'],
    queryFn: fetchDashboard,
    staleTime: 60_000,
  });

  const {
    data: announcements,
    isLoading: announcementsLoading,
    refetch: refetchAnnouncements,
    isFetching: announcementsFetching,
  } = useQuery({
    queryKey: ['fss-announcements'],
    queryFn: fetchAnnouncements,
    staleTime: 120_000,
  });

  const onRefresh = useCallback(() => {
    refetch();
    refetchAnnouncements();
  }, [refetch, refetchAnnouncements]);

  if (isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#059669" />
        <Text className="mt-3 text-gray-500 text-sm">Loading dashboard…</Text>
      </View>
    );
  }

  if (isError) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50 px-6">
        <AlertCircle color="#ef4444" size={40} />
        <Text className="mt-3 text-gray-700 text-base font-medium">
          Could not load dashboard
        </Text>
        <Text className="mt-1 text-gray-500 text-sm text-center">
          Check your connection and try again.
        </Text>
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
    <ScrollView
      className="flex-1 bg-gray-50"
      contentContainerStyle={{ paddingBottom: insets.bottom + 16, paddingTop: 16 }}
      refreshControl={
        <RefreshControl
          refreshing={(isFetching || announcementsFetching) && !isLoading}
          onRefresh={onRefresh}
        />
      }
    >
      {/* KPI Cards */}
      <View className="px-4 gap-3">
        <KpiCard
          icon={<ClipboardList color="#059669" size={22} />}
          label="Meals to log today"
          value={data?.meals_to_log_today ?? 0}
          accent="blue"
        />
        <KpiCard
          icon={<ShoppingBag color="#d97706" size={22} />}
          label="Pending POs"
          value={data?.pending_pos_count ?? 0}
          accent="amber"
          onPress={() => router.push('/(tabs)/procurement')}
        />
      </View>

      {/* Active Menu Week Card */}
      <View className="px-4 mt-4">
        {data?.active_cycle ? (
          <TouchableOpacity
            className="bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-3.5 flex-row items-center"
            onPress={() => router.push('/(tabs)/menu')}
            accessibilityRole="button"
            accessibilityLabel="Open active menu cycle"
            activeOpacity={0.8}
          >
            <View className="w-9 h-9 rounded-full bg-emerald-100 items-center justify-center mr-3">
              <CalendarDays color="#059669" size={18} />
            </View>
            <View className="flex-1">
              <Text className="text-xs text-emerald-600 font-medium uppercase tracking-wide">Active Menu Cycle</Text>
              <Text className="text-sm font-semibold text-gray-800 mt-0.5" numberOfLines={1}>
                {data.active_cycle.name}
              </Text>
              <Text className="text-xs text-gray-500 mt-0.5">
                {data.active_cycle.service_day_count} service day{data.active_cycle.service_day_count !== 1 ? 's' : ''} per week
                {data.active_cycle.activation_date
                  ? ` · started ${new Date(data.active_cycle.activation_date).toLocaleDateString('en-PH', { month: 'short', day: 'numeric' })}`
                  : ''}
              </Text>
            </View>
            <ChevronRight color="#059669" size={16} />
          </TouchableOpacity>
        ) : (
          <View className="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3.5 flex-row items-center gap-3">
            <AlertCircle color="#d97706" size={18} />
            <Text className="text-sm text-amber-700 flex-1">No active menu cycle — contact RND.</Text>
          </View>
        )}
      </View>

      {/* Pending POs Detail */}
      {(data?.pending_pos?.length ?? 0) > 0 && (
        <View className="px-4 mt-4">
          <Text className="text-base font-semibold text-gray-800 mb-2">Pending POs</Text>
          <View className="bg-white rounded-xl overflow-hidden border border-gray-100">
            {data!.pending_pos.map((po, idx) => (
              <TouchableOpacity
                key={po.id}
                className={`px-4 py-3 ${idx < data!.pending_pos.length - 1 ? 'border-b border-gray-100' : ''}`}
                onPress={() => router.push('/(tabs)/procurement')}
                accessibilityRole="button"
                activeOpacity={0.7}
              >
                <View className="flex-row items-center justify-between mb-1">
                  <Text className="text-sm font-semibold text-gray-800">
                    {po.po_number ?? `PO #${po.id}`}
                  </Text>
                  <View className={`px-2 py-0.5 rounded-full ${
                    po.procurement_track === 'food' ? 'bg-emerald-100' : 'bg-sky-100'
                  }`}>
                    <Text className={`text-xs font-medium ${
                      po.procurement_track === 'food' ? 'text-emerald-700' : 'text-sky-700'
                    }`}>
                      {po.procurement_track === 'food' ? 'Food' : 'Supplies'}
                    </Text>
                  </View>
                </View>
                <View className="flex-row flex-wrap gap-1.5 mt-1">
                  {po.waiting_on.map((reason) => (
                    <View key={reason} className="flex-row items-center gap-1 bg-amber-50 border border-amber-200 rounded-full px-2 py-0.5">
                      <AlertCircle color="#d97706" size={10} />
                      <Text className="text-xs text-amber-700">{WAITING_LABELS[reason] ?? reason}</Text>
                    </View>
                  ))}
                </View>
              </TouchableOpacity>
            ))}
          </View>
        </View>
      )}

      {/* Today's Service */}
      <View className="px-4 mt-6">
        <Text className="text-base font-semibold text-gray-800 mb-3">
          Today's service
        </Text>

        {data?.today_service && data.today_service.length > 0 ? (
          <View className="bg-white rounded-xl overflow-hidden border border-gray-100">
            {data.today_service.map((row, idx) => (
              <ServiceRowItem
                key={`${row.meal_type}-${idx}`}
                row={row}
                isLast={idx === data.today_service.length - 1}
              />
            ))}
          </View>
        ) : (
          <View className="bg-white rounded-xl border border-gray-100 px-4 py-6 items-center">
            <Text className="text-gray-400 text-sm">No service entries for today.</Text>
          </View>
        )}
      </View>

      {/* Announcements feed */}
      <View className="px-4 mt-6">
        <View className="flex-row items-center gap-2 mb-3">
          <Bell color="#7c3aed" size={18} />
          <Text className="text-base font-semibold text-gray-800">Announcements</Text>
        </View>

        {announcementsLoading ? (
          <View className="bg-white rounded-xl border border-gray-100 py-6 items-center">
            <ActivityIndicator color="#7c3aed" />
          </View>
        ) : !announcements || announcements.length === 0 ? (
          <View className="bg-white rounded-xl border border-gray-100 px-4 py-6 items-center">
            <Text className="text-gray-400 text-sm">No announcements.</Text>
          </View>
        ) : (
          <View className="bg-white rounded-xl overflow-hidden border border-gray-100">
            {announcements.map((ann, idx) => (
              <AnnouncementCard
                key={ann.id}
                announcement={ann}
                isLast={idx === announcements.length - 1}
              />
            ))}
          </View>
        )}
      </View>
    </ScrollView>
  );
}

function KpiCard({
  icon,
  label,
  value,
  accent,
  onPress,
}: {
  icon: React.ReactNode;
  label: string;
  value: number;
  accent: 'blue' | 'amber' | 'red';
  onPress?: () => void;
}) {
  const borderColor = {
    blue: 'border-emerald-200',
    amber: 'border-amber-200',
    red: 'border-red-200',
  }[accent];

  const bgColor = {
    blue: 'bg-emerald-50',
    amber: 'bg-amber-50',
    red: 'bg-red-50',
  }[accent];

  const valueColor = {
    blue: 'text-emerald-700',
    amber: 'text-amber-700',
    red: 'text-red-700',
  }[accent];

  const content = (
    <View className={`flex-row items-center bg-white rounded-xl px-4 py-4 border ${borderColor}`}>
      <View className={`w-10 h-10 rounded-full ${bgColor} items-center justify-center mr-4`}>
        {icon}
      </View>
      <View className="flex-1">
        <Text className="text-gray-500 text-xs">{label}</Text>
        <Text className={`text-2xl font-bold ${valueColor}`}>{value}</Text>
      </View>
      {onPress && <ChevronRight color="#9ca3af" size={16} />}
    </View>
  );

  if (!onPress) return content;

  return (
    <TouchableOpacity onPress={onPress} accessibilityRole="button" activeOpacity={0.8}>
      {content}
    </TouchableOpacity>
  );
}

function ServiceRowItem({ row, isLast }: { row: ServiceRow; isLast: boolean }) {
  return (
    <View className={`flex-row items-center px-4 py-3 ${isLast ? '' : 'border-b border-gray-100'}`}>
      <View className="flex-1">
        <Text className="text-xs text-gray-400 uppercase tracking-wide">{row.meal_type}</Text>
        <Text className="text-sm font-medium text-gray-800 mt-0.5">{row.name}</Text>
      </View>
      <View className="flex-row gap-2">
        <StatusBadge label="Prepped" active={row.prepped} activeColor="green" />
        {row.has_shortfall && (
          <StatusBadge label="Shortfall" active={true} activeColor="red" />
        )}
      </View>
    </View>
  );
}

function StatusBadge({
  label,
  active,
  activeColor,
}: {
  label: string;
  active: boolean;
  activeColor: 'green' | 'red';
}) {
  if (!active) return null;
  const color = activeColor === 'green'
    ? 'bg-green-100 text-green-700'
    : 'bg-red-100 text-red-700';
  return (
    <View className={`px-2 py-0.5 rounded-full ${color.split(' ')[0]}`}>
      <Text className={`text-xs font-medium ${color.split(' ')[1]}`}>{label}</Text>
    </View>
  );
}

function AnnouncementCard({
  announcement,
  isLast,
}: {
  announcement: Announcement;
  isLast: boolean;
}) {
  return (
    <View
      className={`px-4 py-3.5 ${isLast ? '' : 'border-b border-gray-100'} ${
        announcement.pinned ? 'bg-emerald-50' : 'bg-white'
      }`}
    >
      <View className="flex-row items-start justify-between gap-2">
        <View className="flex-1">
          <View className="flex-row items-center gap-1.5 flex-wrap mb-0.5">
            {announcement.pinned && (
              <Pin color="#7c3aed" size={12} />
            )}
            {announcement.category && (
              <View className="bg-emerald-100 px-1.5 py-0.5 rounded-full">
                <Text className="text-xs font-medium text-emerald-700">{announcement.category}</Text>
              </View>
            )}
          </View>
          <Text className="text-sm font-semibold text-gray-900 leading-5">
            {announcement.title}
          </Text>
        </View>
        <Text className="text-xs text-gray-400 tabular-nums mt-0.5 shrink-0">
          {relativeTime(announcement.created_at)}
        </Text>
      </View>
      <Text className="text-sm text-gray-600 mt-1 leading-5">{announcement.body}</Text>
      {announcement.author.name && (
        <Text className="text-xs text-gray-400 mt-1.5">
          {announcement.author.name}
          {announcement.author.role ? ` · ${announcement.author.role}` : ''}
        </Text>
      )}
    </View>
  );
}

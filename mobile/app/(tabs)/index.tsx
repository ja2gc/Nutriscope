import { useQuery } from '@tanstack/react-query';
import { AlertCircle, ClipboardList, Package, ShoppingBag } from 'lucide-react-native';
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

interface DashboardData {
  meals_to_log_today: number;
  pos_awaiting_receipt: number;
  inventory_no_stock: number;
  today_service: ServiceRow[];
}

async function fetchDashboard(): Promise<DashboardData> {
  const res = await api.get<{ data: DashboardData }>('/api/fss/dashboard/summary');
  return res.data.data;
}

export default function DashboardScreen() {
  const insets = useSafeAreaInsets();

  const { data, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey: ['fss-dashboard'],
    queryFn: fetchDashboard,
    staleTime: 60_000,
  });

  const onRefresh = useCallback(() => {
    refetch();
  }, [refetch]);

  if (isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#2563eb" />
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
          className="mt-5 bg-blue-600 px-6 py-3 rounded-lg"
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
        <RefreshControl refreshing={isFetching && !isLoading} onRefresh={onRefresh} />
      }
    >
      {/* KPI Cards */}
      <View className="px-4 gap-3">
        <KpiCard
          icon={<ClipboardList color="#2563eb" size={22} />}
          label="Meals to log today"
          value={data?.meals_to_log_today ?? 0}
          accent="blue"
        />
        <KpiCard
          icon={<ShoppingBag color="#d97706" size={22} />}
          label="POs awaiting receipt"
          value={data?.pos_awaiting_receipt ?? 0}
          accent="amber"
        />
        <KpiCard
          icon={<Package color="#dc2626" size={22} />}
          label="Items out of stock"
          value={data?.inventory_no_stock ?? 0}
          accent="red"
        />
      </View>

      {/* Today's Service */}
      <View className="px-4 mt-6">
        <Text className="text-base font-semibold text-gray-800 mb-3">
          Today's service
        </Text>

        {data?.today_service && data.today_service.length > 0 ? (
          <View className="bg-white rounded-xl overflow-hidden border border-gray-100">
            {data.today_service.map((row, idx) => (
              <ServiceRow
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
    </ScrollView>
  );
}

function KpiCard({
  icon,
  label,
  value,
  accent,
}: {
  icon: React.ReactNode;
  label: string;
  value: number;
  accent: 'blue' | 'amber' | 'red';
}) {
  const borderColor = {
    blue: 'border-blue-200',
    amber: 'border-amber-200',
    red: 'border-red-200',
  }[accent];

  const bgColor = {
    blue: 'bg-blue-50',
    amber: 'bg-amber-50',
    red: 'bg-red-50',
  }[accent];

  const valueColor = {
    blue: 'text-blue-700',
    amber: 'text-amber-700',
    red: 'text-red-700',
  }[accent];

  return (
    <View className={`flex-row items-center bg-white rounded-xl px-4 py-4 border ${borderColor}`}>
      <View className={`w-10 h-10 rounded-full ${bgColor} items-center justify-center mr-4`}>
        {icon}
      </View>
      <View className="flex-1">
        <Text className="text-gray-500 text-xs">{label}</Text>
        <Text className={`text-2xl font-bold ${valueColor}`}>{value}</Text>
      </View>
    </View>
  );
}

function ServiceRow({ row, isLast }: { row: ServiceRow; isLast: boolean }) {
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

import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { Bell, Megaphone, UserCircle } from 'lucide-react-native';
import { Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../lib/api';
import BrandLogo from './BrandLogo';

interface Notification {
  id: number;
  read: boolean;
}

async function fetchNotifications(): Promise<Notification[]> {
  const res = await api.get<{ data: Notification[] }>('/api/notifications');
  return res.data.data;
}

interface AppHeaderProps {
  title: string;
}

export default function AppHeader({ title }: AppHeaderProps) {
  const insets = useSafeAreaInsets();

  const { data } = useQuery({
    queryKey: ['notifications'],
    queryFn: fetchNotifications,
    staleTime: 30_000,
    refetchInterval: 60_000,
  });

  const unreadCount = data?.filter((n) => !n.read).length ?? 0;
  const badgeLabel = unreadCount > 9 ? '9+' : String(unreadCount);

  return (
    <View
      style={{ paddingTop: insets.top }}
      className="bg-white border-b border-gray-100"
    >
      <View className="flex-row items-center justify-between px-4 h-14">
        <View className="flex-row items-center gap-2.5">
          <BrandLogo size={24} showWordmark={false} />
          <Text className="text-base font-semibold text-gray-900">{title}</Text>
        </View>

        <View className="flex-row items-center gap-2">
          {/* Announcements + SOP */}
          <TouchableOpacity
            onPress={() => router.push('/announcements')}
            className="w-11 h-11 items-center justify-center"
            accessibilityLabel="Announcements and SOP"
            hitSlop={{ top: 4, bottom: 4, left: 4, right: 4 }}
          >
            <Megaphone color="#374151" size={22} />
          </TouchableOpacity>

          {/* Bell */}
          <TouchableOpacity
            onPress={() => router.push('/notifications')}
            className="w-11 h-11 items-center justify-center"
            accessibilityLabel={
              unreadCount > 0
                ? `Notifications, ${unreadCount} unread`
                : 'Notifications'
            }
            hitSlop={{ top: 4, bottom: 4, left: 4, right: 4 }}
          >
            <View className="relative">
              <Bell color="#374151" size={22} />
              {unreadCount > 0 && (
                <View className="absolute -top-1.5 -right-2 bg-red-500 rounded-full min-w-[16px] h-4 items-center justify-center px-0.5">
                  <Text className="text-white text-[10px] font-bold tabular-nums leading-none">
                    {badgeLabel}
                  </Text>
                </View>
              )}
            </View>
          </TouchableOpacity>

          {/* Account */}
          <TouchableOpacity
            onPress={() => router.push('/settings')}
            className="w-11 h-11 items-center justify-center"
            accessibilityLabel="Settings and profile"
            hitSlop={{ top: 4, bottom: 4, left: 4, right: 4 }}
          >
            <UserCircle color="#374151" size={22} />
          </TouchableOpacity>
        </View>
      </View>
    </View>
  );
}

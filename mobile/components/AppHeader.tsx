import { useQuery } from '@tanstack/react-query';
import { router } from 'expo-router';
import { AlertTriangle, Bell } from 'lucide-react-native';
import { useState } from 'react';
import { Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../lib/api';
import type { UserProfile } from '../lib/auth';
import BrandLogo from './BrandLogo';
import { MOBILE_THEME } from '../lib/theme';
import AccountMenu from './AccountMenu';
import { AnnouncementAuthorAvatar } from './AnnouncementMedia';

async function fetchUnreadCount(): Promise<number> {
  const res = await api.get<{ count: number }>('/api/notifications/unread-count');
  return res.data.count ?? 0;
}

async function fetchMe(): Promise<UserProfile> {
  const response = await api.get<{ data: UserProfile } | UserProfile>('/api/auth/me');
  return 'data' in response.data ? response.data.data : response.data;
}

export default function AppHeader() {
  const insets = useSafeAreaInsets();
  const [menuOpen, setMenuOpen] = useState(false);

  const { data: unreadData } = useQuery({
    queryKey: ['notifications-unread-count'],
    queryFn: fetchUnreadCount,
    staleTime: 30_000,
    refetchInterval: 60_000,
  });

  const { data: user } = useQuery({
    queryKey: ['me'],
    queryFn: fetchMe,
    staleTime: 30_000,
  });

  const unreadCount = unreadData ?? 0;
  const badgeLabel = unreadCount > 9 ? '9+' : String(unreadCount);

  return (
    <View
      style={{ paddingTop: insets.top }}
      className="bg-[#FBFCFB] border-b border-[#E2EAE5]"
    >
      <View className="flex-row items-center justify-between px-4 h-16">
        <View className="min-w-0 flex-1 flex-row items-center gap-2.5 pr-2">
          <BrandLogo size={32} />
        </View>

        <View className="shrink-0 flex-row items-center gap-2">
          {/* Bell */}
          <TouchableOpacity
            onPress={() => router.push('/notifications')}
            className="w-12 h-12 items-center justify-center"
            accessibilityLabel={
              unreadCount > 0
                ? `Notifications, ${unreadCount} unread`
                : 'Notifications'
            }
            hitSlop={{ top: 4, bottom: 4, left: 4, right: 4 }}
          >
            <View className="relative">
              <Bell color={MOBILE_THEME.colors.text} size={24} />
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
            onPress={() => setMenuOpen(true)}
            className="w-12 h-12 items-center justify-center"
            accessibilityLabel="Open profile menu"
            hitSlop={{ top: 4, bottom: 4, left: 4, right: 4 }}
          >
            <AnnouncementAuthorAvatar author={{ name: user?.display_name, profile_photo: user?.profile_photo }} size={32} />
          </TouchableOpacity>
        </View>
      </View>
      {user?.onboarding_required && user.onboarding_skipped ? (
        <TouchableOpacity
          onPress={() => router.push('/profile')}
          className="min-h-11 flex-row items-center gap-2 border-t border-amber-200 bg-amber-50 px-4 py-2"
          accessibilityRole="button"
          accessibilityLabel="Finish account setup in profile settings"
        >
          <AlertTriangle color="#b45309" size={17} />
          <Text className="flex-1 text-xs font-semibold leading-4 text-amber-900">
            Finish your password and recovery email setup in Profile settings.
          </Text>
          <Text className="text-xs font-bold text-amber-800">Open</Text>
        </TouchableOpacity>
      ) : null}
      <AccountMenu visible={menuOpen} onClose={() => setMenuOpen(false)} user={user} />
    </View>
  );
}

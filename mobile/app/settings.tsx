import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ChevronRight, LogOut, UserCircle } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import {
  Alert,
  ScrollView,
  Switch,
  Text,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../lib/api';
import { clearToken } from '../lib/auth';
import { AppPrefs, DisplayDensity, getPrefs, setPrefs } from '../lib/prefs';

async function markAllRead(): Promise<void> {
  await api.patch('/api/notifications/read-all');
}

async function logout(): Promise<void> {
  await api.post('/api/auth/logout');
}

function SectionLabel({ label }: { label: string }) {
  return (
    <Text className="text-xs font-semibold text-gray-400 uppercase tracking-wider px-4 mt-6 mb-1">
      {label}
    </Text>
  );
}

function RowLink({
  icon,
  label,
  onPress,
  destructive,
}: {
  icon?: React.ReactNode;
  label: string;
  onPress: () => void;
  destructive?: boolean;
}) {
  return (
    <TouchableOpacity
      onPress={onPress}
      activeOpacity={0.7}
      className="flex-row items-center px-4 py-4 bg-white border-b border-gray-100"
    >
      {icon && <View className="mr-3">{icon}</View>}
      <Text className={`flex-1 text-sm font-medium ${destructive ? 'text-red-600' : 'text-gray-800'}`}>
        {label}
      </Text>
      {!destructive && <ChevronRight color="#9ca3af" size={16} />}
    </TouchableOpacity>
  );
}

function RowSwitch({
  label,
  sublabel,
  value,
  onValueChange,
}: {
  label: string;
  sublabel?: string;
  value: boolean;
  onValueChange: (v: boolean) => void;
}) {
  return (
    <View className="flex-row items-center px-4 py-4 bg-white border-b border-gray-100">
      <View className="flex-1 mr-3">
        <Text className="text-sm font-medium text-gray-800">{label}</Text>
        {sublabel ? <Text className="text-xs text-gray-400 mt-0.5">{sublabel}</Text> : null}
      </View>
      <Switch
        value={value}
        onValueChange={onValueChange}
        trackColor={{ false: '#d1d5db', true: '#2563eb' }}
        thumbColor="#ffffff"
      />
    </View>
  );
}

function DensitySelector({
  value,
  onChange,
}: {
  value: DisplayDensity;
  onChange: (v: DisplayDensity) => void;
}) {
  const options: { key: DisplayDensity; label: string }[] = [
    { key: 'comfortable', label: 'Comfortable' },
    { key: 'compact', label: 'Compact' },
  ];
  return (
    <View className="flex-row px-4 py-3 bg-white border-b border-gray-100 gap-3">
      <Text className="text-sm font-medium text-gray-800 mr-auto self-center">Display density</Text>
      {options.map((opt) => (
        <TouchableOpacity
          key={opt.key}
          onPress={() => onChange(opt.key)}
          className={`px-3 py-1.5 rounded-full border ${
            value === opt.key ? 'bg-blue-600 border-blue-600' : 'bg-white border-gray-300'
          }`}
        >
          <Text className={`text-xs font-medium ${value === opt.key ? 'text-white' : 'text-gray-600'}`}>
            {opt.label}
          </Text>
        </TouchableOpacity>
      ))}
    </View>
  );
}

export default function SettingsScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const [prefs, setLocalPrefs] = useState<AppPrefs>({
    displayDensity: 'comfortable',
    reduceMotion: false,
  });

  useEffect(() => {
    getPrefs().then(setLocalPrefs);
  }, []);

  async function updatePref(patch: Partial<AppPrefs>) {
    const next = await setPrefs(patch);
    setLocalPrefs(next);
  }

  const readAllMutation = useMutation({
    mutationFn: markAllRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['notifications'] });
      Alert.alert('Done', 'All notifications marked as read.');
    },
    onError: () => {
      Alert.alert('Error', 'Could not mark notifications as read.');
    },
  });

  const logoutMutation = useMutation({
    mutationFn: logout,
    onSettled: async () => {
      await clearToken();
      queryClient.clear();
      router.replace('/login');
    },
  });

  function confirmLogout() {
    Alert.alert('Sign out', 'Are you sure you want to sign out?', [
      { text: 'Cancel', style: 'cancel' },
      {
        text: 'Sign out',
        style: 'destructive',
        onPress: () => logoutMutation.mutate(),
      },
    ]);
  }

  return (
    <ScrollView
      className="flex-1 bg-gray-50"
      contentContainerStyle={{ paddingBottom: insets.bottom + 24 }}
    >
      {/* Appearance */}
      <SectionLabel label="Appearance" />
      <DensitySelector
        value={prefs.displayDensity}
        onChange={(v) => updatePref({ displayDensity: v })}
      />
      <RowSwitch
        label="Reduce motion"
        sublabel="Minimise animations throughout the app"
        value={prefs.reduceMotion}
        onValueChange={(v) => updatePref({ reduceMotion: v })}
      />

      {/* Notifications */}
      <SectionLabel label="Notifications" />
      <RowLink
        label={readAllMutation.isPending ? 'Marking…' : 'Mark all notifications read'}
        onPress={() => readAllMutation.mutate()}
      />

      {/* Account */}
      <SectionLabel label="Account" />
      <RowLink
        icon={<UserCircle color="#374151" size={18} />}
        label="Profile"
        onPress={() => router.push('/profile')}
      />
      <RowLink
        icon={<LogOut color="#dc2626" size={18} />}
        label={logoutMutation.isPending ? 'Signing out…' : 'Sign out'}
        onPress={confirmLogout}
        destructive
      />
    </ScrollView>
  );
}

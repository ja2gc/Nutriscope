import { useMutation, useQueryClient } from '@tanstack/react-query';
import { router, type Href } from 'expo-router';
import { BellRing, ChevronRight, CircleHelp, Download, LogOut, Settings, UserCircle, X } from 'lucide-react-native';
import { Alert, ActivityIndicator, Modal, Pressable, Text, TouchableOpacity, View } from 'react-native';
import api from '../lib/api';
import { checkForAppUpdate, openAppDownloadPage } from '../lib/appUpdate';
import { clearToken, UserProfile } from '../lib/auth';

function MenuRow({ icon, label, onPress, danger = false }: { icon: React.ReactNode; label: string; onPress: () => void; danger?: boolean }) {
  return <TouchableOpacity onPress={onPress} className="min-h-12 flex-row items-center gap-3 border-b border-[#EDF2EF] px-4"><View className="w-6 items-center">{icon}</View><Text className={`flex-1 text-sm font-semibold ${danger ? 'text-red-700' : 'text-[#263D35]'}`}>{label}</Text>{!danger && <ChevronRight color="#7A8D85" size={17} />}</TouchableOpacity>;
}

export default function AccountMenu({ visible, onClose, user }: { visible: boolean; onClose: () => void; user?: UserProfile }) {
  const queryClient = useQueryClient();
  const logout = useMutation({
    mutationFn: () => api.post('/api/auth/logout'),
    onSettled: async () => { await clearToken(); queryClient.clear(); onClose(); router.replace('/login'); },
  });
  const updates = useMutation({
    mutationFn: checkForAppUpdate,
    onSuccess: ({ available, current, release }) => Alert.alert(
      available ? 'Update available' : 'NutriScope is up to date',
      available ? `Version ${release.version} is available. You have ${current}.` : `You have the latest version (${current}).`,
      available ? [{ text: 'Later', style: 'cancel' }, { text: 'Open download page', onPress: () => void openAppDownloadPage() }] : [{ text: 'OK' }],
    ),
    onError: () => Alert.alert('Could not check for updates', 'Check your connection and try again.'),
  });
  const open = (path: '/profile' | '/help' | '/settings' | '/notifications') => { onClose(); router.push(path as Href); };
  const confirmLogout = () => Alert.alert('Sign out', 'Are you sure you want to sign out?', [{ text: 'Cancel', style: 'cancel' }, { text: 'Sign out', style: 'destructive', onPress: () => logout.mutate() }]);

  return <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose} statusBarTranslucent>
    <View className="flex-1 flex-row bg-black/40"><Pressable className="flex-1" onPress={onClose} accessibilityLabel="Close account menu" /><View className="h-full w-[84%] max-w-[340px] bg-white pt-12 shadow-xl">
      <View className="flex-row items-start border-b border-[#E2EAE5] px-4 pb-4"><View className="h-11 w-11 items-center justify-center rounded-2xl bg-[#EAF7F1]"><UserCircle color="#087F5B" size={24} /></View><View className="ml-3 flex-1"><Text className="text-base font-extrabold text-[#16352B]" numberOfLines={1}>{user?.display_name ?? 'Food Service Staff'}</Text><Text className="mt-0.5 text-xs text-[#6B7F77]" numberOfLines={1}>{user?.email ?? 'FSS account'}</Text></View><TouchableOpacity onPress={onClose} className="h-11 w-11 items-center justify-center" accessibilityLabel="Close account menu"><X color="#53675F" size={21} /></TouchableOpacity></View>
      <MenuRow icon={<UserCircle color="#53675F" size={19} />} label="Profile" onPress={() => open('/profile')} />
      <MenuRow icon={<BellRing color="#53675F" size={19} />} label="Notifications" onPress={() => open('/notifications')} />
      <MenuRow icon={<CircleHelp color="#53675F" size={19} />} label="Help" onPress={() => open('/help')} />
      <MenuRow icon={<Settings color="#53675F" size={19} />} label="Settings" onPress={() => open('/settings')} />
      <MenuRow icon={updates.isPending ? <ActivityIndicator color="#087F5B" size="small" /> : <Download color="#53675F" size={19} />} label={updates.isPending ? 'Checking for updates…' : 'Check for updates'} onPress={() => updates.mutate()} />
      <MenuRow icon={logout.isPending ? <ActivityIndicator color="#DC2626" size="small" /> : <LogOut color="#DC2626" size={19} />} label={logout.isPending ? 'Signing out…' : 'Sign out'} onPress={confirmLogout} danger />
    </View></View>
  </Modal>;
}

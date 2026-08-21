import AsyncStorage from '@react-native-async-storage/async-storage';
import { useEffect } from 'react';
import { Alert } from 'react-native';
import { checkForAppUpdate, openAppDownloadPage } from '../lib/appUpdate';

const LAST_CHECK_KEY = 'app_update_last_check';
const PROMPTED_VERSION_KEY = 'app_update_prompted_version';
const CHECK_INTERVAL_MS = 12 * 60 * 60 * 1000;

export default function AppUpdatePrompt({ enabled }: { enabled: boolean }) {
  useEffect(() => {
    if (!enabled) return;
    let cancelled = false;
    void (async () => {
      const lastCheck = Number(await AsyncStorage.getItem(LAST_CHECK_KEY) ?? 0);
      if (Date.now() - lastCheck < CHECK_INTERVAL_MS) return;
      await AsyncStorage.setItem(LAST_CHECK_KEY, String(Date.now()));
      const result = await checkForAppUpdate();
      const prompted = await AsyncStorage.getItem(PROMPTED_VERSION_KEY);
      if (cancelled || !result.available || prompted === result.release.version) return;
      await AsyncStorage.setItem(PROMPTED_VERSION_KEY, result.release.version);
      Alert.alert('NutriScope update available', `Version ${result.release.version} is ready.`, [
        { text: 'Later', style: 'cancel' },
        { text: 'Open download page', onPress: () => void openAppDownloadPage() },
      ]);
    })().catch(() => undefined);
    return () => { cancelled = true; };
  }, [enabled]);
  return null;
}

import AsyncStorage from '@react-native-async-storage/async-storage';

export type DisplayDensity = 'comfortable' | 'compact';

export interface AppPrefs {
  displayDensity: DisplayDensity;
  reduceMotion: boolean;
}

const PREFS_KEY = 'app_prefs';

const defaults: AppPrefs = {
  displayDensity: 'comfortable',
  reduceMotion: false,
};

export async function getPrefs(): Promise<AppPrefs> {
  try {
    const raw = await AsyncStorage.getItem(PREFS_KEY);
    if (!raw) return defaults;
    return { ...defaults, ...JSON.parse(raw) } as AppPrefs;
  } catch {
    return defaults;
  }
}

export async function setPrefs(prefs: Partial<AppPrefs>): Promise<AppPrefs> {
  const current = await getPrefs();
  const next = { ...current, ...prefs };
  await AsyncStorage.setItem(PREFS_KEY, JSON.stringify(next));
  return next;
}

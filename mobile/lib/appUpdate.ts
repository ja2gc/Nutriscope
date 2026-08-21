import Constants from 'expo-constants';
import * as Linking from 'expo-linking';

export const APP_DOWNLOAD_PAGE = 'https://nutriscope.live/mobile-app';
export const APP_VERSION_URL = 'https://nutriscope.live/downloads/nutriscope-fss.json';

export interface AppRelease {
  version: string;
  version_code: number;
  download_url: string;
  sha256: string;
  published_at: string;
}

function parts(version: string): number[] {
  return version.split('.').map((value) => Number.parseInt(value, 10) || 0);
}

export function isNewerVersion(latest: string, current: string): boolean {
  const left = parts(latest);
  const right = parts(current);
  for (let index = 0; index < Math.max(left.length, right.length); index += 1) {
    if ((left[index] ?? 0) !== (right[index] ?? 0)) return (left[index] ?? 0) > (right[index] ?? 0);
  }
  return false;
}

export async function checkForAppUpdate(): Promise<{ current: string; release: AppRelease; available: boolean }> {
  const response = await fetch(APP_VERSION_URL, { headers: { Accept: 'application/json' } });
  if (!response.ok) throw new Error('Update information is unavailable.');
  const release = await response.json() as AppRelease;
  const current = Constants.expoConfig?.version ?? '0.0.0';
  return { current, release, available: isNewerVersion(release.version, current) };
}

export async function openAppDownloadPage(): Promise<void> {
  await Linking.openURL(APP_DOWNLOAD_PAGE);
}

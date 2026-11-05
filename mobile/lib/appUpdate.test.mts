import { expect, test, vi, afterEach } from 'vitest';
vi.mock('expo-constants', () => ({ default: { expoConfig: { version: '1.0.0', android: { versionCode: 33 } } } }));
vi.mock('expo-linking', () => ({ openURL: vi.fn() }));
import { checkForAppUpdate } from './appUpdate';
afterEach(() => vi.unstubAllGlobals());
test.each([[32, '1.2.28', false], [33, '1.0.0', false], [34, '1.0.0', true]])('uses build %s rather than display version %s', async (code, version, expected) => {
  vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => ({ version, version_code: code }) }));
  expect((await checkForAppUpdate()).available).toBe(expected);
});

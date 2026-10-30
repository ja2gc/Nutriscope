// @vitest-environment jsdom
import React, { act } from '../../frontend/node_modules/react/index.js';
import { createRoot } from '../../frontend/node_modules/react-dom/client.js';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import RootLayout from '../app/_layout';
import { getToken, subscribeAuth } from './auth';
import { router } from 'expo-router';

// Render the real root with platform boundaries replaced, using one React copy.
vi.mock('react', async () => await import('../../frontend/node_modules/react/index.js'));
vi.mock('../global.css', () => ({}));
vi.mock('@tanstack/react-query', () => ({
  QueryClient: class { clear() {} },
  QueryClientProvider: ({ children }) => children,
}));
vi.mock('react-native-safe-area-context', () => ({ SafeAreaProvider: ({ children }) => children }));
vi.mock('expo-router', () => ({
  Stack: Object.assign(({ children }) => React.createElement('main', { id: 'app-router' }, children), { Screen: () => null }),
  usePathname: () => '/login',
  router: { replace: vi.fn() },
}));
vi.mock('../components/AnimatedSplash', () => ({
  default: () => React.createElement('div', { role: 'status' }, 'Starting app'),
}));
vi.mock('../components/AppUpdatePrompt', () => ({ default: () => null }));
vi.mock('./auth', () => ({ getToken: vi.fn(), subscribeAuth: vi.fn(() => () => {}) }));

let container;
let root;
beforeEach(() => {
  vi.stubGlobal('React', React);
  vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
  container = document.createElement('div');
  document.body.appendChild(container);
  root = createRoot(container);
});
afterEach(async () => {
  await act(async () => root.unmount());
  container.remove();
  vi.restoreAllMocks();
  vi.clearAllMocks();
  vi.unstubAllGlobals();
});

test.each(['demo-session-token', null])('opens navigation as soon as token lookup resolves (%s)', async token => {
  let finish;
  vi.mocked(getToken).mockReturnValue(new Promise(resolve => { finish = resolve; }));
  await act(async () => root.render(React.createElement(RootLayout)));
  expect(container.querySelector('[role="status"]')).not.toBeNull();
  expect(container.querySelector('main')).toBeNull();

  // The splash never calls onDone: navigation must depend only on auth readiness.
  await act(async () => finish(token));
  expect(container.querySelector('#app-router')).not.toBeNull();
  expect(container.querySelector('[role="status"]')).toBeNull();
  expect(subscribeAuth).toHaveBeenCalledOnce();
  if (token) expect(router.replace).toHaveBeenCalledWith('/(tabs)');
  else expect(router.replace).not.toHaveBeenCalled();
});

test('storage failure does not leave startup blocked', async () => {
  vi.spyOn(console, 'error').mockImplementation(() => {});
  vi.mocked(getToken).mockRejectedValue(new Error('Test storage unavailable'));
  await act(async () => root.render(React.createElement(RootLayout)));
  expect(container.querySelector('#app-router')).not.toBeNull();
  expect(container.querySelector('[role="status"]')).toBeNull();
});

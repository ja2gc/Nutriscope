// @vitest-environment jsdom
import React, { act } from '../../frontend/node_modules/react/index.js';
import { createRoot } from '../../frontend/node_modules/react-dom/client.js';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import { AnnouncementAuthorAvatar } from '../components/AnnouncementMedia';
import { getToken } from './auth';

vi.mock('react', async () => await import('../../frontend/node_modules/react/index.js'));
vi.mock('./api', () => ({ default: { defaults: { baseURL: 'https://example.test/mobile-api' } } }));
vi.mock('./auth', () => ({ getToken: vi.fn() }));
vi.mock('react-native', () => ({
  View: ({ children }) => React.createElement('div', null, children),
  Text: ({ children }) => React.createElement('span', null, children),
  Image: ({ source, accessibilityLabel, onError }) => React.createElement('img', {
    src: source?.uri, alt: accessibilityLabel, onError,
    'data-authorization': source?.headers?.Authorization,
  }),
  useWindowDimensions: () => ({ width: 375 }),
}));

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
  vi.clearAllMocks();
  vi.unstubAllGlobals();
});

test('waits for authentication before requesting a protected photo, then falls back after an image failure', async () => {
  let finish;
  vi.mocked(getToken).mockReturnValue(new Promise(resolve => { finish = resolve; }));
  await act(async () => root.render(React.createElement(AnnouncementAuthorAvatar, {
    author: { name: 'Maria Santos', profile_photo: '/api/auth/profile-photo' },
  })));
  expect(container.querySelector('img')).toBeNull();
  expect(container.textContent).toBe('MS');
  await act(async () => finish('test-session'));
  expect(container.querySelector('img')?.getAttribute('data-authorization')).toBe('Bearer test-session');
  expect(container.querySelector('img')?.getAttribute('src')).toBe('https://example.test/mobile-api/api/auth/profile-photo');
  await act(async () => container.querySelector('img')?.dispatchEvent(new Event('error')));
  expect(container.querySelector('img')).toBeNull();
  expect(container.textContent).toBe('MS');
});

test('keeps initials when secure storage fails', async () => {
  vi.mocked(getToken).mockRejectedValue(new Error('Storage unavailable'));
  await act(async () => root.render(React.createElement(AnnouncementAuthorAvatar, {
    author: { name: 'Maria Santos', profile_photo: '/api/auth/profile-photo' },
  })));
  expect(container.querySelector('img')).toBeNull();
  expect(container.textContent).toBe('MS');
});

test('shows initials without a photo and supports inline photos without a session', async () => {
  vi.mocked(getToken).mockResolvedValue(null);
  await act(async () => root.render(React.createElement(AnnouncementAuthorAvatar, { author: { name: 'Maria Santos' } })));
  expect(container.textContent).toBe('MS');
  await act(async () => root.render(React.createElement(AnnouncementAuthorAvatar, {
    author: { name: 'Maria Santos', profile_photo: 'data:image/png;base64,demo' },
  })));
  expect(container.querySelector('img')?.getAttribute('src')).toBe('data:image/png;base64,demo');
});

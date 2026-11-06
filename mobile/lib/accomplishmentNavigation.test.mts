// @vitest-environment jsdom
import React, { act } from '../../frontend/node_modules/react/index.js';
import { createRoot } from '../../frontend/node_modules/react-dom/client.js';
import { afterEach, beforeEach, expect, test, vi } from 'vitest';
import ReportsScreen from '../components/ReportsScreen';

const { period } = vi.hoisted(() => ({ period: {
  start: '2026-11-01', end: '2026-11-15', saved_days: 6, expected_days: 15,
  status: 'draft', days: [
    { date: '2026-11-06', logged: true, off_duty: false },
    { date: '2026-11-07', logged: false, off_duty: false },
  ],
} }));
vi.mock('react', async () => await import('../../frontend/node_modules/react/index.js'));
vi.mock('@tanstack/react-query', () => ({
  useQuery: ({ queryKey }) => ({ data: queryKey[0] === 'fss-accomplishment-periods' ? [period] : period }),
  useInfiniteQuery: () => ({}), useQueryClient: () => ({}), useMutation: () => ({}),
}));
vi.mock('react-native-safe-area-context', () => ({ useSafeAreaInsets: () => ({ bottom: 0 }) }));
vi.mock('lucide-react-native', () => ({ ChevronLeft: () => null, ChevronRight: () => null, Download: () => null, Eye: () => null, FileText: () => null }));
vi.mock('./reports', () => ({ listAccomplishmentPeriods: vi.fn(), listReports: vi.fn(), getAccomplishmentPeriod: vi.fn(), changeAccomplishmentPeriod: vi.fn() }));
vi.mock('./localDate', () => ({ todayManilaKey: () => '2026-11-06' }));
vi.mock('../components/SearchInput', () => ({ SearchInput: () => null }));
vi.mock('react-native', () => ({
  View: ({ children }) => React.createElement('div', null, children),
  Text: ({ children }) => React.createElement('span', null, children),
  ScrollView: ({ children }) => React.createElement('div', null, children),
  TouchableOpacity: ({ children, onPress, disabled, accessibilityLabel }) => React.createElement('button', { onClick: onPress, disabled, 'aria-label': accessibilityLabel }, children),
  FlatList: ({ ListHeaderComponent, ListFooterComponent }) => React.createElement('div', null, ListHeaderComponent, ListFooterComponent),
  ActivityIndicator: () => null, Alert: {},
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
  container.remove(); vi.unstubAllGlobals();
});

test('shows draft completion before opening and prevents future-day navigation while keeping today editable', async () => {
  const edit = vi.fn();
  await act(async () => root.render(React.createElement(ReportsScreen, { embedded: true, onEditDay: edit })));
  expect(container.textContent).toContain('6/15 days logged');
  const draft = container.querySelector('button[aria-label*="6 of 15 days logged"]');
  expect(draft).not.toBeNull();
  await act(async () => draft.click());
  const future = container.querySelector('button[aria-label^="Nov 7,"]');
  expect(future?.disabled).toBe(true);
  await act(async () => future.click());
  expect(edit).not.toHaveBeenCalled();
  await act(async () => container.querySelector('button[aria-label^="Nov 6,"]').click());
  expect(edit).toHaveBeenCalledWith('2026-11-06');
});

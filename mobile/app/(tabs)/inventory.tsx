import { FlashList } from '@shopify/flash-list';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertCircle,
  Minus,
  Package,
  Plus,
  RefreshCw,
  Search,
} from 'lucide-react-native';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Modal,
  Platform,
  Pressable,
  RefreshControl,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../../lib/api';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

interface InventoryRow {
  item_id: number;
  item_type: 'ingredient' | 'supply' | 'recipe';
  inventory_id: number | null;
  name: string;
  category: string;
  quantity_in_stock: number | null;
  unit: string;
  status: 'ok' | 'no_stock';
  highlight: 'green' | 'red';
}

interface InventoryMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

interface InventoryResponse {
  data: InventoryRow[];
  meta: InventoryMeta;
  stats: { total: number; in_stock: number; no_stock: number };
}

type TypeFilter = 'all' | 'ingredient' | 'supply' | 'recipe';
type AdjustMode = 'add' | 'deduct';

// ---------------------------------------------------------------------------
// API helpers
// ---------------------------------------------------------------------------

async function fetchRows(params: {
  search: string;
  type: TypeFilter;
  page: number;
}): Promise<InventoryResponse> {
  const res = await api.get<InventoryResponse>('/api/fss/inventory/rows', {
    params: {
      search: params.search || undefined,
      type: params.type,
      page: params.page,
      per_page: 25,
    },
  });
  return res.data;
}

// ---------------------------------------------------------------------------
// Stock-adjust modal
// ---------------------------------------------------------------------------

interface AdjustModalProps {
  item: InventoryRow | null;
  visible: boolean;
  onClose: () => void;
  onSuccess: () => void;
}

function AdjustModal({ item, visible, onClose, onSuccess }: AdjustModalProps) {
  const qc = useQueryClient();
  const [mode, setMode] = useState<AdjustMode>('add');
  const [qty, setQty] = useState('');
  const [error, setError] = useState<string | null>(null);

  // Reset state when item changes
  useEffect(() => {
    if (visible) {
      setMode('add');
      setQty('');
      setError(null);
    }
  }, [visible, item?.item_id]);

  const restockMutation = useMutation({
    mutationFn: async ({ inventoryId, quantity }: { inventoryId: number; quantity: number }) => {
      const res = await api.post(`/api/fss/inventory/${inventoryId}/restock`, { quantity });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-inventory'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      onSuccess();
    },
    onError: (err: unknown) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const msg = (err as any)?.response?.data?.message ?? 'Adjustment failed.';
      setError(msg);
    },
  });

  const updateMutation = useMutation({
    mutationFn: async ({ inventoryId, quantity }: { inventoryId: number; quantity: number }) => {
      const res = await api.patch(`/api/fss/inventory/${inventoryId}`, {
        quantity_in_stock: quantity,
      });
      return res.data;
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['fss-inventory'] });
      qc.invalidateQueries({ queryKey: ['fss-dashboard'] });
      onSuccess();
    },
    onError: (err: unknown) => {
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const msg = (err as any)?.response?.data?.message ?? 'Update failed.';
      setError(msg);
    },
  });

  const isPending = restockMutation.isPending || updateMutation.isPending;

  const handleSubmit = () => {
    setError(null);
    const num = parseFloat(qty);
    if (isNaN(num) || num <= 0) {
      setError('Enter a positive quantity.');
      return;
    }
    if (!item?.inventory_id) {
      setError('No inventory record found for this item.');
      return;
    }

    if (mode === 'add') {
      restockMutation.mutate({ inventoryId: item.inventory_id, quantity: num });
    } else {
      // Deduct: compute new stock and PATCH it
      const current = item.quantity_in_stock ?? 0;
      const newQty = Math.max(0, current - num);
      updateMutation.mutate({ inventoryId: item.inventory_id, quantity: newQty });
    }
  };

  if (!item) return null;

  const currentStock = item.quantity_in_stock ?? 0;

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <KeyboardAvoidingView
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        className="flex-1"
      >
        <Pressable className="flex-1 bg-black/50 justify-end" onPress={onClose}>
          <Pressable onPress={(e) => e.stopPropagation()}>
            <View className="bg-white rounded-t-2xl px-5 pt-5 pb-8">
              {/* Header */}
              <View className="flex-row items-start justify-between mb-1">
                <View className="flex-1 pr-4">
                  <Text className="text-xs text-gray-400 uppercase tracking-wide mb-0.5">
                    {item.item_type}
                  </Text>
                  <Text className="text-base font-semibold text-gray-900" numberOfLines={2}>
                    {item.name}
                  </Text>
                </View>
                <TouchableOpacity
                  onPress={onClose}
                  hitSlop={{ top: 12, bottom: 12, left: 12, right: 12 }}
                  accessibilityLabel="Close adjust modal"
                >
                  <Text className="text-gray-400 text-lg">✕</Text>
                </TouchableOpacity>
              </View>

              {/* Current stock */}
              <View className="flex-row items-center gap-1.5 mb-5">
                <Text className="text-sm text-gray-500">Current stock:</Text>
                <Text className="text-sm font-semibold text-gray-900 tabular-nums">
                  {currentStock} {item.unit || ''}
                </Text>
              </View>

              {/* Add / Deduct toggle */}
              <View className="flex-row bg-gray-100 rounded-xl p-1 mb-4">
                <TouchableOpacity
                  className={`flex-1 flex-row items-center justify-center gap-1.5 py-2.5 rounded-lg ${
                    mode === 'add' ? 'bg-white shadow-sm' : ''
                  }`}
                  onPress={() => { setMode('add'); setError(null); }}
                  accessibilityLabel="Add stock mode"
                  accessibilityRole="button"
                >
                  <Plus color={mode === 'add' ? '#16a34a' : '#9ca3af'} size={16} />
                  <Text
                    className={`text-sm font-semibold ${
                      mode === 'add' ? 'text-green-700' : 'text-gray-400'
                    }`}
                  >
                    Add
                  </Text>
                </TouchableOpacity>
                <TouchableOpacity
                  className={`flex-1 flex-row items-center justify-center gap-1.5 py-2.5 rounded-lg ${
                    mode === 'deduct' ? 'bg-white shadow-sm' : ''
                  }`}
                  onPress={() => { setMode('deduct'); setError(null); }}
                  accessibilityLabel="Deduct stock mode"
                  accessibilityRole="button"
                >
                  <Minus color={mode === 'deduct' ? '#dc2626' : '#9ca3af'} size={16} />
                  <Text
                    className={`text-sm font-semibold ${
                      mode === 'deduct' ? 'text-red-600' : 'text-gray-400'
                    }`}
                  >
                    Deduct
                  </Text>
                </TouchableOpacity>
              </View>

              {/* Quantity input */}
              <View className="mb-4">
                <Text className="text-xs font-medium text-gray-500 mb-1.5">
                  Quantity {item.unit ? `(${item.unit})` : ''}
                </Text>
                <TextInput
                  value={qty}
                  onChangeText={(t) => {
                    setQty(t.replace(/[^0-9.]/g, ''));
                    setError(null);
                  }}
                  placeholder="0"
                  placeholderTextColor="#9ca3af"
                  keyboardType="decimal-pad"
                  returnKeyType="done"
                  className="border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-900 tabular-nums"
                  accessibilityLabel="Adjustment quantity"
                  autoFocus
                />
              </View>

              {/* Error */}
              {error && (
                <View className="flex-row items-center gap-2 bg-red-50 border border-red-200 rounded-xl px-3 py-2.5 mb-4">
                  <AlertCircle color="#ef4444" size={16} />
                  <Text className="text-sm text-red-700 flex-1">{error}</Text>
                </View>
              )}

              {/* Submit */}
              <TouchableOpacity
                className={`flex-row items-center justify-center gap-2 py-3.5 rounded-xl ${
                  isPending
                    ? 'bg-blue-400'
                    : mode === 'add'
                    ? 'bg-green-600'
                    : 'bg-red-600'
                }`}
                onPress={handleSubmit}
                disabled={isPending}
                accessibilityLabel="Confirm stock adjustment"
                accessibilityRole="button"
              >
                {isPending ? (
                  <ActivityIndicator color="#fff" size="small" />
                ) : mode === 'add' ? (
                  <Plus color="#fff" size={18} />
                ) : (
                  <Minus color="#fff" size={18} />
                )}
                <Text className="text-white font-semibold text-sm">
                  {isPending ? 'Saving…' : mode === 'add' ? 'Add stock' : 'Deduct stock'}
                </Text>
              </TouchableOpacity>
            </View>
          </Pressable>
        </Pressable>
      </KeyboardAvoidingView>
    </Modal>
  );
}

// ---------------------------------------------------------------------------
// Row skeleton
// ---------------------------------------------------------------------------

function RowSkeleton() {
  return (
    <View className="flex-row items-center px-4 py-3.5 bg-white border-b border-gray-100">
      <View className="flex-1">
        <View className="h-3.5 w-40 bg-gray-200 rounded mb-2" />
        <View className="h-3 w-24 bg-gray-100 rounded" />
      </View>
      <View className="h-5 w-16 bg-gray-100 rounded-full ml-3" />
    </View>
  );
}

// ---------------------------------------------------------------------------
// TYPE_FILTERS
// ---------------------------------------------------------------------------

const TYPE_FILTERS: { label: string; value: TypeFilter }[] = [
  { label: 'All', value: 'all' },
  { label: 'Ingredients', value: 'ingredient' },
  { label: 'Supplies', value: 'supply' },
  { label: 'Recipes', value: 'recipe' },
];

// ---------------------------------------------------------------------------
// Main screen
// ---------------------------------------------------------------------------

export default function InventoryScreen() {
  const insets = useSafeAreaInsets();
  const qc = useQueryClient();

  const [search, setSearch] = useState('');
  const [debouncedSearch, setDebouncedSearch] = useState('');
  const [typeFilter, setTypeFilter] = useState<TypeFilter>('all');
  const [page, setPage] = useState(1);
  const [allRows, setAllRows] = useState<InventoryRow[]>([]);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [hasMore, setHasMore] = useState(false);

  const [adjustItem, setAdjustItem] = useState<InventoryRow | null>(null);
  const [showAdjust, setShowAdjust] = useState(false);

  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  // Debounce search 400ms
  const handleSearchChange = useCallback((text: string) => {
    setSearch(text);
    if (debounceRef.current) clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(() => {
      setDebouncedSearch(text);
      setPage(1);
      setAllRows([]);
    }, 400);
  }, []);

  // When filter changes, reset pagination
  const handleTypeChange = useCallback((t: TypeFilter) => {
    setTypeFilter(t);
    setPage(1);
    setAllRows([]);
  }, []);

  const queryKey = ['fss-inventory', debouncedSearch, typeFilter, page] as const;

  const { data, isLoading, isError, refetch, isFetching } = useQuery({
    queryKey,
    queryFn: () => fetchRows({ search: debouncedSearch, type: typeFilter, page }),
    staleTime: 30_000,
  });

  // Accumulate rows across pages
  useEffect(() => {
    if (!data) return;
    if (page === 1) {
      setAllRows(data.data);
    } else {
      setAllRows((prev) => {
        const ids = new Set(prev.map((r) => `${r.item_type}-${r.item_id}`));
        const fresh = data.data.filter((r) => !ids.has(`${r.item_type}-${r.item_id}`));
        return [...prev, ...fresh];
      });
    }
    setHasMore(data.meta.current_page < data.meta.last_page);
    setIsLoadingMore(false);
  }, [data, page]);

  const onRefresh = useCallback(async () => {
    setPage(1);
    setAllRows([]);
    await qc.invalidateQueries({ queryKey: ['fss-inventory'] });
  }, [qc]);

  const onEndReached = useCallback(() => {
    if (!hasMore || isLoadingMore || isFetching) return;
    setIsLoadingMore(true);
    setPage((p) => p + 1);
  }, [hasMore, isLoadingMore, isFetching]);

  const openAdjust = useCallback((item: InventoryRow) => {
    setAdjustItem(item);
    setShowAdjust(true);
  }, []);

  const closeAdjust = useCallback(() => {
    setShowAdjust(false);
  }, []);

  const handleAdjustSuccess = useCallback(() => {
    setShowAdjust(false);
    setPage(1);
    setAllRows([]);
    qc.invalidateQueries({ queryKey: ['fss-inventory'] });
  }, [qc]);

  // ---------------------------------------------------------------------------
  // Render helpers
  // ---------------------------------------------------------------------------

  const renderItem = useCallback(
    ({ item }: { item: InventoryRow }) => {
      const isOk = item.highlight === 'green';
      const qty = item.quantity_in_stock;
      const displayQty = qty !== null && qty !== undefined ? qty : 0;

      return (
        <TouchableOpacity
          className="flex-row items-center px-4 py-3.5 bg-white border-b border-gray-100 active:bg-gray-50"
          onPress={() => openAdjust(item)}
          accessibilityLabel={`Adjust stock for ${item.name}`}
          accessibilityRole="button"
          style={{ minHeight: 56 }}
        >
          <View className="flex-1 pr-3">
            <Text className="text-sm font-medium text-gray-900" numberOfLines={1}>
              {item.name}
            </Text>
            <Text className="text-xs text-gray-400 mt-0.5 capitalize">{item.item_type}</Text>
          </View>
          <View className="items-end gap-1">
            <Text className="text-sm font-semibold text-gray-800 tabular-nums">
              {Number.isInteger(displayQty) ? displayQty : displayQty.toFixed(2)}{' '}
              <Text className="text-xs font-normal text-gray-500">{item.unit}</Text>
            </Text>
            <View
              className={`px-2 py-0.5 rounded-full ${isOk ? 'bg-green-100' : 'bg-red-100'}`}
            >
              <Text
                className={`text-xs font-medium ${isOk ? 'text-green-700' : 'text-red-700'}`}
              >
                {isOk ? 'In stock' : 'No stock'}
              </Text>
            </View>
          </View>
        </TouchableOpacity>
      );
    },
    [openAdjust],
  );

  const keyExtractor = useCallback(
    (item: InventoryRow) => `${item.item_type}-${item.item_id}`,
    [],
  );

  // ---------------------------------------------------------------------------
  // Loading skeleton (first load)
  // ---------------------------------------------------------------------------

  if (isLoading && allRows.length === 0) {
    return (
      <View
        className="flex-1 bg-gray-50"
        style={{ paddingTop: insets.top > 0 ? 0 : 8 }}
      >
        <SearchAndFilter
          search={search}
          onSearch={handleSearchChange}
          typeFilter={typeFilter}
          onTypeChange={handleTypeChange}
        />
        <View className="flex-1 bg-white">
          {Array.from({ length: 10 }).map((_, i) => (
            <RowSkeleton key={i} />
          ))}
        </View>
      </View>
    );
  }

  // ---------------------------------------------------------------------------
  // Error state
  // ---------------------------------------------------------------------------

  if (isError && allRows.length === 0) {
    return (
      <View className="flex-1 bg-gray-50">
        <SearchAndFilter
          search={search}
          onSearch={handleSearchChange}
          typeFilter={typeFilter}
          onTypeChange={handleTypeChange}
        />
        <View className="flex-1 items-center justify-center px-6">
          <AlertCircle color="#ef4444" size={40} />
          <Text className="mt-3 text-gray-700 text-base font-medium">
            Could not load inventory
          </Text>
          <Text className="mt-1 text-gray-500 text-sm text-center">
            Check your connection and try again.
          </Text>
          <TouchableOpacity
            className="mt-5 bg-blue-600 px-6 py-3 rounded-lg flex-row items-center gap-2"
            onPress={() => refetch()}
          >
            <RefreshCw color="#fff" size={16} />
            <Text className="text-white font-semibold">Retry</Text>
          </TouchableOpacity>
        </View>
      </View>
    );
  }

  // ---------------------------------------------------------------------------
  // Main list
  // ---------------------------------------------------------------------------

  return (
    <View className="flex-1 bg-gray-50" style={{ paddingBottom: insets.bottom }}>
      <SearchAndFilter
        search={search}
        onSearch={handleSearchChange}
        typeFilter={typeFilter}
        onTypeChange={handleTypeChange}
      />

      {/* Stats strip */}
      {data?.stats && (
        <View className="flex-row px-4 py-2.5 gap-4 border-b border-gray-100 bg-white">
          <StatChip label="Total" value={data.stats.total} color="text-gray-700" />
          <StatChip label="In stock" value={data.stats.in_stock} color="text-green-600" />
          <StatChip label="No stock" value={data.stats.no_stock} color="text-red-600" />
        </View>
      )}

      <FlashList
        data={allRows}
        renderItem={renderItem}
        keyExtractor={keyExtractor}
        onEndReached={onEndReached}
        onEndReachedThreshold={0.4}
        refreshControl={
          <RefreshControl
            refreshing={isFetching && page === 1 && !isLoading}
            onRefresh={onRefresh}
          />
        }
        ListEmptyComponent={
          <View className="items-center justify-center py-20 px-6">
            <Package color="#d1d5db" size={48} />
            <Text className="mt-4 text-gray-400 text-base font-medium">No items found</Text>
            {(debouncedSearch || typeFilter !== 'all') && (
              <Text className="mt-1 text-gray-400 text-sm text-center">
                Try clearing the search or filter.
              </Text>
            )}
          </View>
        }
        ListFooterComponent={
          isLoadingMore ? (
            <View className="py-4 items-center">
              <ActivityIndicator color="#2563eb" />
            </View>
          ) : null
        }
        contentContainerStyle={{ backgroundColor: '#fff' }}
      />

      <AdjustModal
        item={adjustItem}
        visible={showAdjust}
        onClose={closeAdjust}
        onSuccess={handleAdjustSuccess}
      />
    </View>
  );
}

// ---------------------------------------------------------------------------
// Sub-components
// ---------------------------------------------------------------------------

function SearchAndFilter({
  search,
  onSearch,
  typeFilter,
  onTypeChange,
}: {
  search: string;
  onSearch: (t: string) => void;
  typeFilter: TypeFilter;
  onTypeChange: (t: TypeFilter) => void;
}) {
  const insets = useSafeAreaInsets();
  return (
    <View
      className="bg-white border-b border-gray-100 px-4 pt-3 pb-2"
      style={{ paddingTop: insets.top > 0 ? insets.top + 8 : 12 }}
    >
      {/* Search box */}
      <View className="flex-row items-center bg-gray-100 rounded-xl px-3 py-2.5 mb-2.5">
        <Search color="#9ca3af" size={16} />
        <TextInput
          value={search}
          onChangeText={onSearch}
          placeholder="Search inventory…"
          placeholderTextColor="#9ca3af"
          className="flex-1 ml-2 text-sm text-gray-900"
          autoCapitalize="none"
          autoCorrect={false}
          returnKeyType="search"
          accessibilityLabel="Search inventory"
          style={{ height: 20 }}
        />
        {search.length > 0 && (
          <TouchableOpacity
            onPress={() => onSearch('')}
            hitSlop={{ top: 8, bottom: 8, left: 8, right: 8 }}
            accessibilityLabel="Clear search"
          >
            <Text className="text-gray-400 text-base leading-none">✕</Text>
          </TouchableOpacity>
        )}
      </View>

      {/* Type filter chips */}
      <View className="flex-row gap-2">
        {TYPE_FILTERS.map(({ label, value }) => (
          <TouchableOpacity
            key={value}
            onPress={() => onTypeChange(value)}
            className={`px-3 py-1.5 rounded-full border ${
              typeFilter === value
                ? 'bg-blue-600 border-blue-600'
                : 'bg-white border-gray-200'
            }`}
            accessibilityLabel={`Filter by ${label}`}
            accessibilityRole="button"
            accessibilityState={{ selected: typeFilter === value }}
          >
            <Text
              className={`text-xs font-medium ${
                typeFilter === value ? 'text-white' : 'text-gray-600'
              }`}
            >
              {label}
            </Text>
          </TouchableOpacity>
        ))}
      </View>
    </View>
  );
}

function StatChip({
  label,
  value,
  color,
}: {
  label: string;
  value: number;
  color: string;
}) {
  return (
    <View className="flex-row items-center gap-1">
      <Text className={`text-base font-bold tabular-nums ${color}`}>{value}</Text>
      <Text className="text-xs text-gray-400">{label}</Text>
    </View>
  );
}

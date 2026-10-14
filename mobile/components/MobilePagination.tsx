import { Text, TouchableOpacity, View } from 'react-native';

export function MobilePagination({ page, pages, total, onPrevious, onNext, loading = false, error = false }: {
  page: number;
  pages: number;
  total: number;
  onPrevious: () => void;
  onNext: () => void;
  loading?: boolean;
  error?: boolean;
}) {
  return <View className="mt-3 flex-row items-center justify-between gap-2 py-2" accessibilityLabel="List pages">
    <TouchableOpacity onPress={onPrevious} disabled={page <= 1 || loading} className="min-h-12 min-w-20 items-center justify-center rounded-xl border border-[#D9E3DD] px-2" accessibilityRole="button" accessibilityState={{ disabled: page <= 1 || loading }}><Text className={page <= 1 ? 'text-gray-400' : 'font-semibold text-[#087F5B]'}>Previous</Text></TouchableOpacity>
    <Text className="flex-1 text-center text-xs text-[#6B7F77]">Page {page} of {pages} · {total} items{error ? ' · Retry next page' : ''}</Text>
    <TouchableOpacity onPress={onNext} disabled={page >= pages || loading} className="min-h-12 min-w-20 items-center justify-center rounded-xl border border-[#D9E3DD] px-2" accessibilityRole="button" accessibilityState={{ disabled: page >= pages || loading }}><Text className={page >= pages ? 'text-gray-400' : 'font-semibold text-[#087F5B]'}>{loading ? 'Loading…' : error ? 'Retry' : 'Next'}</Text></TouchableOpacity>
  </View>;
}

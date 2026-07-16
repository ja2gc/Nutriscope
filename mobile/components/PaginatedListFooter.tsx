import { ActivityIndicator, Text, TouchableOpacity, View } from 'react-native';

export function PaginatedListFooter({ loading, error, onRetry }: {
  loading: boolean;
  error: boolean;
  onRetry: () => void;
}) {
  if (loading) return <View className="h-12 items-center justify-center"><ActivityIndicator color="#059669" /></View>;
  if (!error) return null;

  return (
    <TouchableOpacity className="min-h-12 items-center justify-center" onPress={onRetry}>
      <Text className="text-sm font-semibold text-emerald-700">Retry loading more</Text>
    </TouchableOpacity>
  );
}

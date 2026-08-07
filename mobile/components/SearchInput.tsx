import { Search, X } from 'lucide-react-native';
import { ActivityIndicator, TextInput as NativeTextInput, TouchableOpacity, View } from 'react-native';

type SearchInputProps = {
  label: string;
  value: string;
  onChangeText: (value: string) => void;
  placeholder: string;
  loading?: boolean;
};

export function SearchInput({ label, value, onChangeText, placeholder, loading = false }: SearchInputProps) {
  return (
    <View className="min-h-12 flex-row items-center rounded-xl border border-gray-300 bg-white px-3">
      <Search color="#9CA3AF" size={19} />
      <NativeTextInput
        accessibilityLabel={label}
        autoCapitalize="none"
        autoCorrect={false}
        className="min-h-12 flex-1 px-3 text-base text-gray-900"
        onChangeText={onChangeText}
        placeholder={placeholder}
        placeholderTextColor="#9CA3AF"
        returnKeyType="search"
        value={value}
      />
      {loading ? <ActivityIndicator color="#059669" size="small" /> : value ? (
        <TouchableOpacity accessibilityLabel="Clear search" accessibilityRole="button" className="h-11 w-11 items-center justify-center" onPress={() => onChangeText('')}>
          <X color="#6B7280" size={18} />
        </TouchableOpacity>
      ) : null}
    </View>
  );
}

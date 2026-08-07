import { useMemo, useState } from 'react';
import { ScrollView, Text, TouchableOpacity, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import HelpQuestionList from '../components/help/HelpQuestionList';
import { SearchInput } from '../components/SearchInput';
import { filterMobileHelpItems, groupMobileHelpItems, MOBILE_HELP_ITEMS } from '../lib/helpContent';

export default function HelpScreen() {
  const insets = useSafeAreaInsets();
  const [query, setQuery] = useState('');
  const results = useMemo(() => filterMobileHelpItems(query), [query]);
  const groups = useMemo(() => groupMobileHelpItems(results), [results]);
  const popular = MOBILE_HELP_ITEMS.filter((item) => item.popular).slice(0, 5);

  return (
    <ScrollView className="flex-1 bg-gray-50" keyboardShouldPersistTaps="handled" contentContainerStyle={{ padding: 16, paddingBottom: insets.bottom + 32 }}>
      <View className="mb-5 rounded-3xl bg-emerald-800 px-5 py-6">
        <Text className="text-xs font-semibold uppercase tracking-widest text-emerald-100">FSS guidance</Text>
        <Text className="mt-2 text-2xl font-bold text-white">How can we help?</Text>
        <Text className="mt-2 text-base leading-6 text-emerald-50">Find answers for your account and Food Service Staff workflows.</Text>
      </View>

      <Text className="mb-2 text-sm font-semibold text-gray-800">Search Help</Text>
      <SearchInput label="Search help" value={query} onChangeText={setQuery} placeholder="Search questions or topics" />
      <Text accessibilityLiveRegion="polite" className="mb-5 mt-2 text-xs text-gray-500">{results.length} {results.length === 1 ? 'answer' : 'answers'} found</Text>

      {!query.trim() && popular.length ? (
        <View className="mb-7"><Text className="mb-3 text-lg font-bold text-gray-900">Popular questions</Text><HelpQuestionList items={popular} /></View>
      ) : null}

      {groups.length ? groups.map(([topic, items]) => (
        <View className="mb-7" key={topic}><Text className="mb-3 text-lg font-bold text-gray-900">{topic}</Text><HelpQuestionList items={items} /></View>
      )) : (
        <View className="rounded-2xl border border-gray-200 bg-white px-5 py-8">
          <Text className="text-center text-lg font-bold text-gray-900">No answers found</Text>
          <Text className="mt-2 text-center text-base leading-6 text-gray-600">Try fewer words or search for the task, page, or message you saw.</Text>
          <TouchableOpacity accessibilityRole="button" className="mx-auto mt-5 min-h-12 justify-center rounded-xl bg-emerald-700 px-5" onPress={() => setQuery('')}>
            <Text className="text-base font-semibold text-white">Clear search</Text>
          </TouchableOpacity>
        </View>
      )}

      <View className="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-4"><Text className="text-base font-bold text-amber-900">Still need help?</Text><Text className="mt-1 text-base leading-6 text-amber-800">Contact your supervising RND or administrator. Never share passwords, verification codes, or unnecessary patient information.</Text></View>
    </ScrollView>
  );
}

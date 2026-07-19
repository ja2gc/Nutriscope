import { ChevronDown } from 'lucide-react-native';
import { useState } from 'react';
import { Text, TouchableOpacity, View } from 'react-native';
import type { MobileHelpItem } from '../../lib/helpContent';

export default function HelpQuestionList({ items }: { items: MobileHelpItem[] }) {
  const [expandedIds, setExpandedIds] = useState<Set<string>>(new Set());

  return (
    <View className="overflow-hidden rounded-2xl border border-gray-200 bg-white">
      {items.map((item, index) => {
        const expanded = expandedIds.has(item.id);
        return (
          <View key={item.id} className={index ? 'border-t border-gray-100' : ''}>
            <TouchableOpacity
              accessibilityRole="button"
              accessibilityState={{ expanded }}
              accessibilityLabel={`${item.question}. ${expanded ? 'Collapse answer' : 'Expand answer'}`}
              activeOpacity={0.7}
              className="min-h-12 flex-row items-center px-4 py-4"
              onPress={() => setExpandedIds((current) => {
                const next = new Set(current);
                if (expanded) next.delete(item.id);
                else next.add(item.id);
                return next;
              })}
            >
              <Text className="flex-1 pr-3 text-sm font-semibold leading-5 text-gray-900">{item.question}</Text>
              <ChevronDown color="#6B7F77" size={18} style={{ transform: [{ rotate: expanded ? '180deg' : '0deg' }] }} />
            </TouchableOpacity>
            {expanded ? (
              <View className="border-t border-gray-100 bg-gray-50 px-4 py-4">
                <Text className="text-base leading-6 text-gray-700">{item.answer}</Text>
              </View>
            ) : null}
          </View>
        );
      })}
    </View>
  );
}

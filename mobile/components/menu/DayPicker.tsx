import { useEffect, useRef } from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react-native';
import { ScrollView, Text, TouchableOpacity, View } from 'react-native';

type Props = {
  days: readonly string[];
  weekStartDate?: string | null;
  selectedDay: string;
  onSelect: (day: string) => void;
  onPrevious: () => void;
  onNext: () => void;
};

const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'] as const;

export function DayPicker({ days, weekStartDate, selectedDay, onSelect, onPrevious, onNext }: Props) {
  const scroll = useRef<ScrollView>(null);
  const selectedIndex = Math.max(0, days.indexOf(selectedDay));

  useEffect(() => {
    scroll.current?.scrollTo({ x: Math.max(0, selectedIndex * 70 - 120), animated: true });
  }, [selectedIndex]);

  const dateFor = (day: string) => {
    if (!weekStartDate) return null;
    const date = new Date(`${weekStartDate}T00:00:00`);
    date.setDate(date.getDate() + Math.max(0, WEEKDAYS.indexOf(day as (typeof WEEKDAYS)[number])));
    return date;
  };

  return (
    <View className="bg-white border-b border-[#E5ECE8] py-3">
      <View className="flex-row items-center">
      <TouchableOpacity onPress={onPrevious} className="min-h-12 min-w-12 items-center justify-center" accessibilityRole="button" accessibilityLabel="Previous day"><ChevronLeft color="#087F5B" size={22} /></TouchableOpacity>
      <ScrollView ref={scroll} horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
        {days.map((day) => {
          const active = day === selectedDay;
          const date = dateFor(day);
          return (
            <TouchableOpacity
              key={day}
              onPress={() => onSelect(day)}
              className={`w-[62px] min-h-[68px] rounded-2xl items-center justify-center border ${active ? 'bg-[#087F5B] border-[#087F5B]' : 'bg-[#F7F9F8] border-[#E2E9E5]'}`}
              accessibilityRole="button"
              accessibilityState={{ selected: active }}
              accessibilityLabel={`Show ${day} menu`}
            >
              <Text className={`text-[10px] font-bold uppercase tracking-wider ${active ? 'text-emerald-100' : 'text-[#71847B]'}`}>{day.slice(0, 3)}</Text>
              <Text className={`text-xl font-extrabold mt-1 ${active ? 'text-white' : 'text-[#203A31]'}`}>{date?.getDate() ?? '•'}</Text>
            </TouchableOpacity>
          );
        })}
      </ScrollView>
      <TouchableOpacity onPress={onNext} className="min-h-12 min-w-12 items-center justify-center" accessibilityRole="button" accessibilityLabel="Next day"><ChevronRight color="#087F5B" size={22} /></TouchableOpacity>
      </View>
    </View>
  );
}

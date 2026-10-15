import { Text, TouchableOpacity, View } from 'react-native';
import { AnnouncementAuthorAvatar, AnnouncementMedia } from './AnnouncementMedia';

export interface Announcement {
  id: number | string;
  title: string;
  body: string;
  category: string;
  visibility: string;
  pinned: boolean;
  attachment?: string | null;
  attachments?: string[];
  author?: { name: string; role: string; profile_photo?: string | null } | null;
  created_at: string;
}

export const CATEGORY: Record<string, { bg: string; text: string }> = {
  General: { bg: 'bg-zinc-100', text: 'text-zinc-700' },
  Event: { bg: 'bg-orange-50', text: 'text-orange-700' },
  Operational: { bg: 'bg-emerald-50', text: 'text-emerald-700' },
  Urgent: { bg: 'bg-red-50', text: 'text-red-700' },
  Memo: { bg: 'bg-sky-50', text: 'text-sky-700' },
};

export function fmt(date: string): string {
  return new Date(date).toLocaleString('en-US', {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  });
}

export function AnnouncementCard({ item, onPress }: { item: Announcement; onPress: () => void }) {
  const cat = CATEGORY[item.category] ?? CATEGORY.General;
  return (
    <TouchableOpacity
      className="rounded-2xl border border-gray-200 bg-white p-4 mb-3"
      activeOpacity={0.8}
      onPress={onPress}
      accessibilityRole="button"
      accessibilityLabel={`Open announcement: ${item.title}`}
    >
      <View className="flex-row items-center gap-2.5">
        <AnnouncementAuthorAvatar author={item.author} size={36} />
        <Text className="flex-1 text-[10px] font-semibold uppercase tracking-wider text-gray-400">
          {item.author?.name ?? 'Staff'} · {fmt(item.created_at)}
        </Text>
      </View>
      <View className="mt-2 flex-row flex-wrap gap-1.5">
        {item.pinned && (
          <View className="px-2 py-0.5 rounded-full bg-orange-50 border border-orange-200">
            <Text className="text-[9px] font-extrabold uppercase tracking-wider text-orange-700">Pinned</Text>
          </View>
        )}
        <View className={`px-2 py-0.5 rounded-full ${cat.bg}`}>
          <Text className={`text-[9px] font-extrabold uppercase tracking-wider ${cat.text}`}>{item.category}</Text>
        </View>
      </View>
      <Text className="text-sm font-bold text-gray-900 mt-2">{item.title}</Text>
      <Text className="text-xs text-gray-600 leading-6 mt-1" numberOfLines={2}>{item.body}</Text>
      <Text className="mt-1 text-xs font-bold text-emerald-700">Read more</Text>
      <AnnouncementMedia
        attachments={item.attachments?.length ? item.attachments : (item.attachment ? [item.attachment] : [])}
        title={item.title}
      />
    </TouchableOpacity>
  );
}

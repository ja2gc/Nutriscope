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


export function fmt(date: string): string {
  return new Date(date).toLocaleString('en-US', {
    month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit',
  });
}

export function AnnouncementCard({ item, onPress }: { item: Announcement; onPress: () => void }) {
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
          <View>
            <Text className="text-xs font-medium text-gray-500">Pinned</Text>
          </View>
        )}
        <View>
          <Text className="text-xs font-medium text-gray-500">{item.category}</Text>
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

import { useEffect, useState } from 'react';
import { Image, Text, useWindowDimensions, View } from 'react-native';
import api from '../lib/api';
import { getToken } from '../lib/auth';
import { authenticatedImageSource } from '../lib/mobileContracts';

export interface AnnouncementMediaAuthor {
  name?: string | null;
  profile_photo?: string | null;
}

function initials(name: string): string {
  return name
    .split(/\s+/)
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('');
}

export function AnnouncementAuthorAvatar({
  author,
  size = 40,
}: {
  author?: AnnouncementMediaAuthor | null;
  size?: number;
}) {
  const [token, setToken] = useState<string | null>(null);
  const [failed, setFailed] = useState(false);
  const photo = author?.profile_photo ?? null;
  const name = author?.name?.trim() || 'Staff';

  useEffect(() => {
    void getToken().then(setToken);
    setFailed(false);
  }, [photo]);

  return (
    <View
      className="shrink-0 items-center justify-center overflow-hidden rounded-full bg-emerald-800"
      style={{ width: size, height: size }}
    >
      {photo && !failed ? (
        <Image
          source={authenticatedImageSource(api.defaults.baseURL ?? '', photo, token)}
          accessibilityLabel={`${name} profile photo`}
          resizeMode="cover"
          className="h-full w-full"
          onError={() => setFailed(true)}
        />
      ) : (
        <Text className="text-xs font-extrabold uppercase text-white">{initials(name)}</Text>
      )}
    </View>
  );
}

export function AnnouncementMedia({
  attachments,
  title,
}: {
  attachments?: string[] | null;
  title: string;
}) {
  const { width } = useWindowDimensions();
  const src = attachments?.find(Boolean) ?? null;
  const height = Math.min(360, Math.max(180, Math.round((width - 64) * 9 / 16)));

  if (!src) return null;

  return (
    <View className="relative mt-3 w-full overflow-hidden rounded-xl bg-black" style={{ height }}>
      <Image
        source={{ uri: src }}
        accessibilityElementsHidden
        importantForAccessibility="no-hide-descendants"
        blurRadius={24}
        resizeMode="cover"
        className="absolute inset-0 h-full w-full opacity-60"
        style={{ transform: [{ scale: 1.08 }] }}
      />
      <View className="absolute inset-0 bg-black/20" />
      <Image
        source={{ uri: src }}
        accessibilityLabel={title}
        resizeMode="contain"
        className="h-full w-full"
      />
    </View>
  );
}

import '../global.css';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Stack, router } from 'expo-router';
import { useEffect, useState } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { getToken } from '../lib/auth';

const queryClient = new QueryClient();

export default function RootLayout() {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    async function bootstrap() {
      const token = await getToken();
      if (token) {
        router.replace('/(tabs)');
      } else {
        router.replace('/login');
      }
      setReady(true);
    }
    bootstrap();
  }, []);

  if (!ready) return null;

  return (
    <QueryClientProvider client={queryClient}>
      <SafeAreaProvider>
        <Stack
          screenOptions={{
            headerShown: true,
            headerStyle: { backgroundColor: '#ffffff' },
            headerTitleStyle: { fontWeight: '600', color: '#111827' },
            headerTintColor: '#2563eb',
          }}
        >
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="login" options={{ headerShown: false }} />
          <Stack.Screen
            name="notifications"
            options={{ title: 'Notifications' }}
          />
          <Stack.Screen
            name="profile"
            options={{ title: 'Profile' }}
          />
          <Stack.Screen
            name="settings"
            options={{ title: 'Settings' }}
          />
        </Stack>
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}

import '../global.css';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Stack, router } from 'expo-router';
import { useEffect, useState } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { getToken } from '../lib/auth';

const queryClient = new QueryClient();

function Redirector({ isAuthenticated }: { isAuthenticated: boolean }) {
  useEffect(() => {
    if (isAuthenticated) {
      router.replace('/(tabs)');
    } else {
      router.replace('/login');
    }
  }, [isAuthenticated]);

  return null;
}

export default function RootLayout() {
  const [ready, setReady] = useState(false);
  const [isAuthenticated, setIsAuthenticated] = useState(false);

  useEffect(() => {
    async function bootstrap() {
      try {
        const token = await getToken();
        setIsAuthenticated(!!token);
      } catch (err) {
        console.error('Failed to retrieve token during bootstrap:', err);
      } finally {
        setReady(true);
      }
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
            headerTintColor: '#059669',
          }}
        >
          <Stack.Screen name="(tabs)" options={{ headerShown: false }} />
          <Stack.Screen name="login" options={{ headerShown: false }} />
          <Stack.Screen
            name="announcements"
            options={{ title: 'Announcements' }}
          />
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
        <Redirector isAuthenticated={isAuthenticated} />
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}

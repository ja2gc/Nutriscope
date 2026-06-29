import '../global.css';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Stack, router, usePathname } from 'expo-router';
import { useEffect, useState } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import AnimatedSplash from '../components/AnimatedSplash';
import { getToken, subscribeAuth } from '../lib/auth';
import { getAuthRedirect } from '../lib/routeGuard';

const queryClient = new QueryClient();

function Redirector({ isAuthenticated }: { isAuthenticated: boolean }) {
  const pathname = usePathname();

  useEffect(() => {
    const redirect = getAuthRedirect(isAuthenticated, pathname);

    if (redirect) {
      router.replace(redirect);
    }
  }, [isAuthenticated, pathname]);

  return null;
}

export default function RootLayout() {
  const [ready, setReady] = useState(false);
  const [splashDone, setSplashDone] = useState(false);
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

    return subscribeAuth((token) => {
      setIsAuthenticated(!!token);
    });
  }, []);

  // Hold on the branded splash until BOTH the token check finished and the
  // animation has played out, so the launch always feels intentional.
  if (!ready || !splashDone) {
    return (
      <SafeAreaProvider>
        <AnimatedSplash onDone={() => setSplashDone(true)} />
      </SafeAreaProvider>
    );
  }

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
          <Stack.Screen name="forgot-password" options={{ headerShown: false }} />
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
          <Stack.Screen
            name="reports"
            options={{ title: 'Accomplishment Reports' }}
          />
        </Stack>
        <Redirector isAuthenticated={isAuthenticated} />
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}

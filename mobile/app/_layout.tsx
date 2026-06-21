import '../global.css';

import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { Slot, router } from 'expo-router';
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
        <Slot />
      </SafeAreaProvider>
    </QueryClientProvider>
  );
}

import { useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { ShieldCheck } from 'lucide-react-native';
import { useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import BrandLogo from '../components/BrandLogo';
import api from '../lib/api';
import type { UserProfile } from '../lib/auth';

function responseUser(value: UserProfile | { data: UserProfile }): UserProfile {
  return 'data' in value ? value.data : value;
}

export default function AccountSetupScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [recoveryEmail, setRecoveryEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function finishSetup() {
    if (password.length < 8) {
      setError('Password must be at least 8 characters.');
      return;
    }
    if (password !== confirmation) {
      setError('Passwords do not match.');
      return;
    }
    if (!/^\S+@\S+\.\S+$/.test(recoveryEmail.trim())) {
      setError('Enter a valid recovery email.');
      return;
    }

    setSubmitting(true);
    setError(null);
    try {
      const response = await api.post<{ user: UserProfile | { data: UserProfile } }>(
        '/api/auth/onboarding',
        {
          password,
          password_confirmation: confirmation,
          recovery_email: recoveryEmail.trim(),
        },
      );
      queryClient.setQueryData(['me'], responseUser(response.data.user));
      router.replace('/(tabs)');
    } catch (caught: unknown) {
      setError(
        (caught as { response?: { data?: { message?: string } } }).response?.data?.message
          ?? 'Account setup failed. Try again.',
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function doLater() {
    setSubmitting(true);
    setError(null);
    try {
      const response = await api.post<{ user: UserProfile | { data: UserProfile } }>(
        '/api/auth/onboarding/skip',
      );
      queryClient.setQueryData(['me'], responseUser(response.data.user));
      router.replace('/(tabs)');
    } catch (caught: unknown) {
      setError(
        (caught as { response?: { data?: { message?: string } } }).response?.data?.message
          ?? 'Setup could not be deferred.',
      );
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <KeyboardAvoidingView
      className="flex-1 bg-gray-50"
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        keyboardShouldPersistTaps="handled"
        contentContainerStyle={{
          flexGrow: 1,
          justifyContent: 'center',
          padding: 20,
          paddingTop: insets.top + 20,
          paddingBottom: insets.bottom + 20,
        }}
      >
        <View className="mx-auto w-full max-w-md">
          <View className="mb-6 items-center">
            <BrandLogo size={32} />
          </View>
          <View className="rounded-2xl border border-gray-200 bg-white p-6 shadow-sm">
            <View className="mb-5 flex-row items-start gap-3 border-b border-gray-100 pb-5">
              <View className="rounded-xl bg-emerald-50 p-2.5">
                <ShieldCheck color="#047857" size={22} />
              </View>
              <View className="flex-1">
                <Text className="text-xs font-bold uppercase tracking-widest text-emerald-700">
                  First login
                </Text>
                <Text className="mt-1 text-xl font-bold text-gray-900">Secure your account</Text>
                <Text className="mt-2 text-sm leading-5 text-gray-500">
                  Replace the temporary password and add a recovery email. No email code is needed now.
                </Text>
              </View>
            </View>

            <Text className="mb-1.5 text-sm font-semibold text-gray-700">New password</Text>
            <TextInput
              accessibilityLabel="New password"
              className="mb-4 h-12 rounded-lg border border-gray-300 px-4 text-base text-gray-900"
              secureTextEntry
              autoComplete="new-password"
              value={password}
              onChangeText={setPassword}
              editable={!submitting}
            />
            <Text className="mb-1.5 text-sm font-semibold text-gray-700">Confirm new password</Text>
            <TextInput
              accessibilityLabel="Confirm new password"
              className="mb-4 h-12 rounded-lg border border-gray-300 px-4 text-base text-gray-900"
              secureTextEntry
              autoComplete="new-password"
              value={confirmation}
              onChangeText={setConfirmation}
              editable={!submitting}
            />
            <Text className="mb-1.5 text-sm font-semibold text-gray-700">Recovery email</Text>
            <TextInput
              accessibilityLabel="Recovery email"
              className="h-12 rounded-lg border border-gray-300 px-4 text-base text-gray-900"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              autoComplete="email"
              value={recoveryEmail}
              onChangeText={setRecoveryEmail}
              editable={!submitting}
            />

            {error ? (
              <View className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <Text className="text-sm font-semibold text-red-700">{error}</Text>
              </View>
            ) : null}

            <TouchableOpacity
              className="mt-5 h-12 items-center justify-center rounded-lg bg-emerald-600"
              onPress={finishSetup}
              disabled={submitting}
              accessibilityRole="button"
            >
              {submitting ? <ActivityIndicator color="#ffffff" /> : <Text className="font-semibold text-white">Save account setup</Text>}
            </TouchableOpacity>
            <TouchableOpacity
              className="mt-2 h-12 items-center justify-center rounded-lg"
              onPress={doLater}
              disabled={submitting}
              accessibilityRole="button"
            >
              <Text className="font-semibold text-gray-600">Do later</Text>
            </TouchableOpacity>
          </View>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

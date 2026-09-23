import { useQuery, useQueryClient } from '@tanstack/react-query';
import { router } from 'expo-router';
import { useEffect, useState } from 'react';
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

async function fetchMe(): Promise<UserProfile> {
  const response = await api.get<{ data: UserProfile } | UserProfile>('/api/auth/me');
  return 'data' in response.data ? response.data.data : response.data;
}

export default function AccountSetupScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();
  const { data: user, isLoading } = useQuery({ queryKey: ['me'], queryFn: fetchMe });
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [recoveryEmail, setRecoveryEmail] = useState('');
  const [verificationCode, setVerificationCode] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [notice, setNotice] = useState<string | null>(null);
  const verificationEmail = user?.pending_recovery_email ?? user?.recovery_email;
  const passwordStage = Boolean(user?.must_change_password);
  const verificationStage = Boolean(user && !user.must_change_password && user.must_set_recovery_email && verificationEmail);
  const recoveryEmailStage = Boolean(user && !user.must_change_password && user.must_set_recovery_email && !verificationEmail);

  useEffect(() => {
    if (user && !user.onboarding_required) router.replace('/(tabs)');
  }, [user]);

  useEffect(() => {
    const stagedEmail = user?.pending_recovery_email ?? user?.recovery_email;
    if (stagedEmail) setRecoveryEmail((current) => current || stagedEmail);
  }, [user?.pending_recovery_email, user?.recovery_email]);

  async function updatePassword() {
    if (password.length < 8) {
      setError('Password must be at least 8 characters.');
      return;
    }
    if (password !== confirmation) {
      setError('Passwords do not match.');
      return;
    }
    setSubmitting(true);
    setError(null);
    setNotice(null);
    try {
      const response = await api.post<{ user: UserProfile | { data: UserProfile } }>(
        '/api/auth/onboarding',
        {
          password,
          password_confirmation: confirmation,
        },
      );
      queryClient.setQueryData(['me'], responseUser(response.data.user));
      setPassword('');
      setConfirmation('');
    } catch (caught: unknown) {
      setError(
        (caught as { response?: { data?: { message?: string } } }).response?.data?.message
          ?? 'Password could not be updated. Try again.',
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function verifyCode() {
    if (!/^\d{6}$/.test(verificationCode.trim())) {
      setError('Enter the 6-digit verification code.');
      return;
    }

    setSubmitting(true);
    setError(null);
    setNotice(null);
    try {
      const response = await api.post<{ user: UserProfile | { data: UserProfile } }>(
        '/api/auth/recovery-email/verify',
        { code: verificationCode.trim() },
      );
      const updated = responseUser(response.data.user);
      queryClient.setQueryData(['me'], updated);
      if (!updated.onboarding_required) router.replace('/(tabs)');
    } catch (caught: unknown) {
      setError(
        (caught as { response?: { data?: { message?: string } } }).response?.data?.message
          ?? 'Verification failed.',
      );
    } finally {
      setSubmitting(false);
    }
  }

  async function sendCode() {
    if (!/^\S+@\S+\.\S+$/.test(recoveryEmail.trim())) {
      setError('Enter a valid recovery email.');
      return;
    }

    setSubmitting(true);
    setError(null);
    setNotice(null);
    try {
      const response = await api.patch<{ message?: string; user: UserProfile | { data: UserProfile } }>(
        '/api/auth/recovery-email',
        { recovery_email: recoveryEmail.trim() },
      );
      queryClient.setQueryData(['me'], responseUser(response.data.user));
      setNotice(response.data.message ?? 'Verification code sent.');
    } catch (caught: unknown) {
      setError(
        (caught as { response?: { data?: { message?: string } } }).response?.data?.message
          ?? 'Verification code could not be sent. Check the address and try again.',
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

  if (isLoading || !user) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator color="#059669" size="large" />
      </View>
    );
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
            <View className="mb-5 border-b border-gray-100 pb-5">
              <View>
                <Text className="text-xs font-bold uppercase tracking-widest text-emerald-700">
                  First login
                </Text>
                <Text className="mt-1 text-xl font-bold text-gray-900">Secure your account</Text>
                <Text className="mt-2 text-sm leading-5 text-gray-500">
                  {passwordStage
                    ? 'Replace your temporary password.'
                    : recoveryEmailStage
                      ? 'Add a recovery email for password reset and account recovery.'
                      : 'Enter the six-digit code sent to your recovery email.'}
                </Text>
              </View>
            </View>

            {passwordStage ? (
              <>
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
                  className="h-12 rounded-lg border border-gray-300 px-4 text-base text-gray-900"
                  secureTextEntry
                  autoComplete="new-password"
                  value={confirmation}
                  onChangeText={setConfirmation}
                  editable={!submitting}
                />
              </>
            ) : recoveryEmailStage ? (
              <>
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
              </>
            ) : verificationStage ? (
              <>
                <Text className="mb-1.5 text-sm font-semibold text-gray-700">Verification code</Text>
                <TextInput
                  accessibilityLabel="Verification code"
                  className="h-12 rounded-lg border border-gray-300 px-4 text-base text-gray-900"
                  keyboardType="number-pad"
                  autoComplete="one-time-code"
                  maxLength={6}
                  value={verificationCode}
                  onChangeText={setVerificationCode}
                  editable={!submitting}
                />
              </>
            ) : null}

            {notice ? (
              <View className="mt-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3">
                <Text className="text-sm font-semibold text-emerald-700">{notice}</Text>
              </View>
            ) : null}
            {error ? (
              <View className="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3">
                <Text className="text-sm font-semibold text-red-700">{error}</Text>
              </View>
            ) : null}

            <TouchableOpacity
              className="mt-5 h-12 items-center justify-center rounded-lg bg-emerald-600"
              onPress={passwordStage ? updatePassword : recoveryEmailStage ? sendCode : verifyCode}
              disabled={submitting}
              accessibilityRole="button"
            >
              {submitting ? <ActivityIndicator color="#ffffff" /> : (
                <Text className="font-semibold text-white">
                  {passwordStage ? 'Next' : recoveryEmailStage ? 'Send verification code' : 'Verify recovery email'}
                </Text>
              )}
            </TouchableOpacity>
            {verificationStage ? (
              <TouchableOpacity
                className="mt-2 h-12 items-center justify-center rounded-lg"
                onPress={sendCode}
                disabled={submitting}
                accessibilityRole="button"
              >
                <Text className="font-semibold text-emerald-700">Send another code</Text>
              </TouchableOpacity>
            ) : null}
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

import api from '../lib/api';
import { router } from 'expo-router';
import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import BrandLogo from '../components/BrandLogo';

export default function ForgotPasswordScreen() {
  const [email, setEmail] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function handleSubmit() {
    if (!email.trim()) {
      setError('Enter your verified recovery email.');
      return;
    }
    setError(null);
    setMessage(null);
    setSubmitting(true);
    try {
      const res = await api.post('/api/auth/forgot-password', {
        email: email.trim(),
      });
      setMessage(res.data?.message ?? 'If that email exists, a password reset link has been sent.');
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Failed to request password reset.';
      setError(msg);
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <KeyboardAvoidingView
      className="flex-1 bg-zinc-50"
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <View className="flex-1 justify-center px-4">
        <View className="w-full max-w-md mx-auto">
          <View className="flex flex-col items-center mb-6">
            <BrandLogo size={32} />
            <Text className="text-[10px] font-semibold text-zinc-500 uppercase tracking-widest text-center mt-1">
              Account Recovery
            </Text>
          </View>

          <View className="bg-white px-6 py-8 border border-zinc-200 rounded-2xl shadow-sm">
            <View className="mb-6 border-b border-zinc-100 pb-4">
              <Text className="text-lg font-bold text-zinc-900">
                Reset Password
              </Text>
              <Text className="mt-1 text-xs text-zinc-500">
                Enter your verified recovery email to receive a reset link.
              </Text>
            </View>

            {message ? (
              <View className="p-3.5 bg-emerald-50 border border-emerald-100 rounded-lg mb-4">
                <Text className="text-xs font-semibold text-emerald-800 leading-4">
                  {message}
                </Text>
              </View>
            ) : null}

            {error ? (
              <View className="p-3.5 bg-red-50 border border-red-100 rounded-lg mb-4">
                <Text className="text-xs font-semibold text-red-800 leading-4">
                  {error}
                </Text>
              </View>
            ) : null}

            <Text className="text-xs font-semibold text-zinc-700 mb-1.5">Verified Recovery Email</Text>
            <TextInput
              className="border border-zinc-300 rounded-lg px-4 h-12 text-base text-zinc-900 mb-5 bg-white"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              value={email}
              onChangeText={(val) => {
                setEmail(val);
                setError(null);
                setMessage(null);
              }}
              editable={!submitting}
            />

            <TouchableOpacity
              className={`rounded-lg h-12 items-center justify-center ${submitting ? 'bg-emerald-400' : 'bg-emerald-600 active:bg-emerald-700'}`}
              onPress={handleSubmit}
              disabled={submitting}
              activeOpacity={0.8}
            >
              <Text className="text-white font-semibold text-base">
                {submitting ? 'Sending…' : 'Send Reset Link'}
              </Text>
            </TouchableOpacity>

            <TouchableOpacity
              className="h-11 items-center justify-center mt-3"
              onPress={() => router.replace('/login')}
            >
              <Text className="text-xs font-semibold text-zinc-500">
                Back to sign in
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      </View>
    </KeyboardAvoidingView>
  );
}

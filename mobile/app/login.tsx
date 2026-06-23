import api from '../lib/api';
import { setToken } from '../lib/auth';
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
import Svg, { Path } from 'react-native-svg';
import BrandLogo from '../components/BrandLogo';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleLogin() {
    if (!email.trim() || !password.trim()) {
      setError('Please enter both your email address and password.');
      return;
    }
    setError(null);
    setSubmitting(true);
    try {
      const res = await api.post('/api/auth/login', {
        email: email.trim(),
        password,
        device_name: 'Expo App',
        platform: 'app',
      });
      await setToken(res.data.token);
      router.replace('/(tabs)');
    } catch (err: unknown) {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data
          ?.message ?? 'Login failed. Check your credentials.';
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
          {/* Brand Header */}
          <View className="flex flex-col items-center mb-6">
            <BrandLogo size={32} />
            <Text className="text-[10px] font-semibold text-zinc-500 uppercase tracking-widest text-center mt-1">
              Clinical & Operational Care Console
            </Text>
          </View>

          {/* Login Card */}
          <View className="bg-white px-6 py-8 border border-zinc-200 rounded-2xl shadow-sm">
            <View className="mb-6 border-b border-zinc-100 pb-4">
              <Text className="text-lg font-bold text-zinc-900">
                Sign In
              </Text>
              <Text className="mt-1 text-xs text-zinc-500">
                Enter your credentials below to access your workspace.
              </Text>
            </View>

            {/* Error Message */}
            {error ? (
              <View className="p-3.5 bg-red-50 border border-red-100 rounded-lg mb-4 flex-row gap-2.5 items-start">
                <Svg
                  style={{ width: 18, height: 18, marginTop: 2 }}
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="#dc2626"
                  strokeWidth="2"
                >
                  <Path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                  />
                </Svg>
                <Text className="text-xs font-semibold text-red-800 flex-1 leading-4">
                  {error}
                </Text>
              </View>
            ) : null}

            {/* Inputs */}
            <Text className="text-xs font-semibold text-zinc-700 mb-1.5">Email Address</Text>
            <TextInput
              className="border border-zinc-300 rounded-lg px-4 h-12 text-base text-zinc-900 mb-4 bg-white"
              placeholder=""
              placeholderTextColor="#a1a1aa"
              keyboardType="email-address"
              autoCapitalize="none"
              autoCorrect={false}
              value={email}
              onChangeText={(val) => {
                setEmail(val);
                setError(null);
              }}
              editable={!submitting}
            />

            <Text className="text-xs font-semibold text-zinc-700 mb-1.5">Password</Text>
            <TextInput
              className="border border-zinc-300 rounded-lg px-4 h-12 text-base text-zinc-900 mb-6 bg-white"
              placeholder="••••••••"
              placeholderTextColor="#a1a1aa"
              secureTextEntry
              value={password}
              onChangeText={(val) => {
                setPassword(val);
                setError(null);
              }}
              editable={!submitting}
              onSubmitEditing={handleLogin}
            />

            <TouchableOpacity
              className={`rounded-lg h-12 items-center justify-center ${submitting ? 'bg-emerald-400' : 'bg-emerald-600 active:bg-emerald-700'
                }`}
              onPress={handleLogin}
              disabled={submitting}
              activeOpacity={0.8}
            >
              <Text className="text-white font-semibold text-base">
                {submitting ? 'Signing in…' : 'Sign In'}
              </Text>
            </TouchableOpacity>
          </View>

          {/* Footer Audit Notice */}
          <View className="mt-6">
            <Text className="text-[10px] text-zinc-400 text-center uppercase tracking-widest">
              Secure Connection • Activity Logs Active
            </Text>
          </View>
        </View>
      </View>
    </KeyboardAvoidingView>
  );
}

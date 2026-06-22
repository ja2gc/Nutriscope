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
import Svg, { Circle, Line, Path } from 'react-native-svg';

function BrandLogo() {
  return (
    <View className="flex-row items-center gap-2.5 justify-center mb-1">
      {/* Handcrafted Leaf + Crosshair Brand Icon */}
      <View className="relative flex items-center justify-center h-8 w-8">
        <Svg
          style={{ width: 28, height: 28 }}
          viewBox="0 0 32 32"
          fill="none"
        >
          {/* Diagnostic Scope Outer Rings / Lens Crosshairs */}
          <Circle
            cx="16"
            cy="16"
            r="12"
            stroke="#ea580c"
            strokeWidth="1.5"
            strokeDasharray="4 2"
            opacity="0.75"
          />
          <Circle
            cx="16"
            cy="16"
            r="6"
            stroke="#ea580c"
            strokeWidth="1"
            opacity="0.40"
          />
          {/* Scope reticle tick marks */}
          <Line x1="16" y1="2" x2="16" y2="6" stroke="#ea580c" strokeWidth="1.5" />
          <Line x1="16" y1="26" x2="16" y2="30" stroke="#ea580c" strokeWidth="1.5" />
          <Line x1="2" y1="16" x2="6" y2="16" stroke="#ea580c" strokeWidth="1.5" />
          <Line x1="26" y1="16" x2="30" y2="16" stroke="#ea580c" strokeWidth="1.5" />

          {/* Premium Green Leaf */}
          <Path
            d="M16 8C16 8 10 13 10 18C10 21.3137 12.6863 24 16 24C19.3137 24 22 21.3137 22 18C22 13 16 8 16 8Z"
            fill="#059669"
          />
          <Path
            d="M16 8C16 13 18.5 17 21 19.5"
            stroke="#d1fae5"
            strokeWidth="1"
            strokeLinecap="round"
            opacity="0.9"
          />
          <Path
            d="M16 24V14"
            stroke="#10b981"
            strokeWidth="1.2"
            strokeLinecap="round"
          />
        </Svg>
      </View>

      {/* Wordmark (Nutri + Scope) */}
      <View className="flex-row items-baseline">
        <Text className="text-xl font-extrabold tracking-tight text-emerald-600">
          Nutri
        </Text>
        <Text className="text-xl font-extrabold tracking-tight text-orange-600">
          Scope
        </Text>
      </View>
    </View>
  );
}

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
            <BrandLogo />
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

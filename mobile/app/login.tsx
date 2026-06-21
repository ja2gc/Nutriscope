import api from '../lib/api';
import { setToken } from '../lib/auth';
import { router } from 'expo-router';
import { useState } from 'react';
import {
  Alert,
  KeyboardAvoidingView,
  Platform,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';

export default function LoginScreen() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleLogin() {
    if (!email.trim() || !password.trim()) {
      setError('Email and password are required.');
      return;
    }
    setError(null);
    setSubmitting(true);
    try {
      const res = await api.post('/api/login', {
        email: email.trim(),
        password,
        device_name: 'Expo App',
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
      className="flex-1 bg-white"
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <View className="flex-1 justify-center px-6">
        <Text className="text-3xl font-bold text-gray-900 mb-2">
          Nutriscope FSS
        </Text>
        <Text className="text-base text-gray-500 mb-8">
          Food Service Supervisor
        </Text>

        {error ? (
          <View className="bg-red-50 border border-red-200 rounded-lg px-4 py-3 mb-4">
            <Text className="text-red-700 text-sm">{error}</Text>
          </View>
        ) : null}

        <Text className="text-sm font-medium text-gray-700 mb-1">Email</Text>
        <TextInput
          className="border border-gray-300 rounded-lg px-4 h-12 text-base text-gray-900 mb-4"
          placeholder="you@example.com"
          keyboardType="email-address"
          autoCapitalize="none"
          autoCorrect={false}
          value={email}
          onChangeText={setEmail}
          editable={!submitting}
        />

        <Text className="text-sm font-medium text-gray-700 mb-1">Password</Text>
        <TextInput
          className="border border-gray-300 rounded-lg px-4 h-12 text-base text-gray-900 mb-6"
          placeholder="••••••••"
          secureTextEntry
          value={password}
          onChangeText={setPassword}
          editable={!submitting}
          onSubmitEditing={handleLogin}
        />

        <TouchableOpacity
          className={`rounded-lg h-12 items-center justify-center ${
            submitting ? 'bg-blue-300' : 'bg-blue-600'
          }`}
          onPress={handleLogin}
          disabled={submitting}
          activeOpacity={0.8}
        >
          <Text className="text-white font-semibold text-base">
            {submitting ? 'Signing in…' : 'Sign in'}
          </Text>
        </TouchableOpacity>
      </View>
    </KeyboardAvoidingView>
  );
}

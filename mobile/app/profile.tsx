import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Eye, EyeOff } from 'lucide-react-native';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  KeyboardAvoidingView,
  Platform,
  ScrollView,
  Text,
  TextInput,
  TouchableOpacity,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import api from '../lib/api';

interface UserProfile {
  id: number;
  name: string;
  email: string;
}

async function fetchMe(): Promise<UserProfile> {
  const res = await api.get<{ data: UserProfile } | UserProfile>('/api/auth/me');
  // Handle both { data: {...} } and bare user object
  if (res.data && typeof (res.data as { data: UserProfile }).data === 'object') {
    return (res.data as { data: UserProfile }).data;
  }
  return res.data as UserProfile;
}

async function updateProfile(body: { name: string; email: string }): Promise<UserProfile> {
  const res = await api.patch<{ data: UserProfile } | UserProfile>('/api/auth/profile', body);
  if (res.data && typeof (res.data as { data: UserProfile }).data === 'object') {
    return (res.data as { data: UserProfile }).data;
  }
  return res.data as UserProfile;
}

async function changePassword(body: {
  current_password: string;
  password: string;
  password_confirmation: string;
}): Promise<void> {
  await api.post('/api/auth/password', body);
}

function FormField({
  label,
  value,
  onChangeText,
  placeholder,
  keyboardType,
  autoCapitalize,
  error,
  secure,
  onBlur,
  rightIcon,
  editable = true,
}: {
  label: string;
  value: string;
  onChangeText: (v: string) => void;
  placeholder?: string;
  keyboardType?: 'default' | 'email-address';
  autoCapitalize?: 'none' | 'words';
  error?: string | null;
  secure?: boolean;
  onBlur?: () => void;
  rightIcon?: React.ReactNode;
  editable?: boolean;
}) {
  return (
    <View className="mb-4">
      <Text className="text-sm font-medium text-gray-700 mb-1">{label}</Text>
      <View className="relative">
        <TextInput
          className={`border rounded-lg px-4 h-12 text-base text-gray-900 pr-12 ${
            error ? 'border-red-400' : 'border-gray-300'
          }`}
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          keyboardType={keyboardType ?? 'default'}
          autoCapitalize={autoCapitalize ?? 'sentences'}
          autoCorrect={false}
          secureTextEntry={secure}
          onBlur={onBlur}
          editable={editable}
        />
        {rightIcon && (
          <View className="absolute right-3 top-0 bottom-0 justify-center">
            {rightIcon}
          </View>
        )}
      </View>
      {error ? <Text className="text-red-500 text-xs mt-1">{error}</Text> : null}
    </View>
  );
}

export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  // ── Account form ──
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [nameError, setNameError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [profileMsg, setProfileMsg] = useState<{ ok: boolean; text: string } | null>(null);

  // ── Password form ──
  const [currentPw, setCurrentPw] = useState('');
  const [newPw, setNewPw] = useState('');
  const [confirmPw, setConfirmPw] = useState('');
  const [showCurrent, setShowCurrent] = useState(false);
  const [showNew, setShowNew] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [currentPwError, setCurrentPwError] = useState<string | null>(null);
  const [newPwError, setNewPwError] = useState<string | null>(null);
  const [confirmPwError, setConfirmPwError] = useState<string | null>(null);
  const [pwMsg, setPwMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const { data: user, isLoading } = useQuery({
    queryKey: ['me'],
    queryFn: fetchMe,
  });

  useEffect(() => {
    if (user) {
      setName(user.name);
      setEmail(user.email);
    }
  }, [user]);

  const profileMutation = useMutation({
    mutationFn: updateProfile,
    onSuccess: (updated) => {
      queryClient.setQueryData(['me'], updated);
      setProfileMsg({ ok: true, text: 'Profile updated.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Update failed.';
      setProfileMsg({ ok: false, text: msg });
    },
  });

  const passwordMutation = useMutation({
    mutationFn: changePassword,
    onSuccess: () => {
      setCurrentPw('');
      setNewPw('');
      setConfirmPw('');
      setPwMsg({ ok: true, text: 'Password changed.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Password change failed.';
      setPwMsg({ ok: false, text: msg });
    },
  });

  function validateProfile() {
    let valid = true;
    if (!name.trim()) { setNameError('Name is required.'); valid = false; } else setNameError(null);
    if (!email.trim()) { setEmailError('Email is required.'); valid = false; }
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) {
      setEmailError('Enter a valid email.'); valid = false;
    } else setEmailError(null);
    return valid;
  }

  function validatePassword() {
    let valid = true;
    if (!currentPw) { setCurrentPwError('Required.'); valid = false; } else setCurrentPwError(null);
    if (!newPw || newPw.length < 8) { setNewPwError('At least 8 characters.'); valid = false; } else setNewPwError(null);
    if (newPw !== confirmPw) { setConfirmPwError('Passwords do not match.'); valid = false; } else setConfirmPwError(null);
    return valid;
  }

  function submitProfile() {
    setProfileMsg(null);
    if (!validateProfile()) return;
    profileMutation.mutate({ name: name.trim(), email: email.trim() });
  }

  function submitPassword() {
    setPwMsg(null);
    if (!validatePassword()) return;
    passwordMutation.mutate({
      current_password: currentPw,
      password: newPw,
      password_confirmation: confirmPw,
    });
  }

  if (isLoading) {
    return (
      <View className="flex-1 items-center justify-center bg-gray-50">
        <ActivityIndicator size="large" color="#059669" />
      </View>
    );
  }

  return (
    <KeyboardAvoidingView
      className="flex-1 bg-gray-50"
      behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
    >
      <ScrollView
        contentContainerStyle={{ paddingBottom: insets.bottom + 24, paddingTop: 16 }}
        keyboardShouldPersistTaps="handled"
      >
        {/* Account card */}
        <View className="mx-4 bg-white rounded-xl border border-gray-100 p-4 mb-4">
          <Text className="text-base font-semibold text-gray-800 mb-4">Account</Text>

          <FormField
            label="Name"
            value={name}
            onChangeText={setName}
            placeholder="Your name"
            autoCapitalize="words"
            error={nameError}
            onBlur={() => { if (!name.trim()) setNameError('Name is required.'); else setNameError(null); }}
            editable={!profileMutation.isPending}
          />
          <FormField
            label="Email"
            value={email}
            onChangeText={setEmail}
            placeholder="you@example.com"
            keyboardType="email-address"
            autoCapitalize="none"
            error={emailError}
            onBlur={() => {
              if (!email.trim()) setEmailError('Email is required.');
              else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim())) setEmailError('Enter a valid email.');
              else setEmailError(null);
            }}
            editable={!profileMutation.isPending}
          />

          {profileMsg && (
            <View className={`rounded-lg px-4 py-3 mb-3 ${profileMsg.ok ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
              <Text className={`text-sm ${profileMsg.ok ? 'text-green-700' : 'text-red-700'}`}>{profileMsg.text}</Text>
            </View>
          )}

          <TouchableOpacity
            className={`rounded-lg h-12 items-center justify-center ${profileMutation.isPending ? 'bg-emerald-300' : 'bg-emerald-600'}`}
            onPress={submitProfile}
            disabled={profileMutation.isPending}
            activeOpacity={0.8}
          >
            <Text className="text-white font-semibold">
              {profileMutation.isPending ? 'Saving…' : 'Save changes'}
            </Text>
          </TouchableOpacity>
        </View>

        {/* Password card */}
        <View className="mx-4 bg-white rounded-xl border border-gray-100 p-4">
          <Text className="text-base font-semibold text-gray-800 mb-4">Change password</Text>

          <FormField
            label="Current password"
            value={currentPw}
            onChangeText={setCurrentPw}
            secure={!showCurrent}
            error={currentPwError}
            onBlur={() => { if (!currentPw) setCurrentPwError('Required.'); else setCurrentPwError(null); }}
            editable={!passwordMutation.isPending}
            rightIcon={
              <TouchableOpacity onPress={() => setShowCurrent((v) => !v)} className="w-8 h-8 items-center justify-center">
                {showCurrent ? <EyeOff color="#9ca3af" size={18} /> : <Eye color="#9ca3af" size={18} />}
              </TouchableOpacity>
            }
          />
          <FormField
            label="New password"
            value={newPw}
            onChangeText={setNewPw}
            secure={!showNew}
            error={newPwError}
            onBlur={() => { if (!newPw || newPw.length < 8) setNewPwError('At least 8 characters.'); else setNewPwError(null); }}
            editable={!passwordMutation.isPending}
            rightIcon={
              <TouchableOpacity onPress={() => setShowNew((v) => !v)} className="w-8 h-8 items-center justify-center">
                {showNew ? <EyeOff color="#9ca3af" size={18} /> : <Eye color="#9ca3af" size={18} />}
              </TouchableOpacity>
            }
          />
          <FormField
            label="Confirm new password"
            value={confirmPw}
            onChangeText={setConfirmPw}
            secure={!showConfirm}
            error={confirmPwError}
            onBlur={() => { if (newPw !== confirmPw) setConfirmPwError('Passwords do not match.'); else setConfirmPwError(null); }}
            editable={!passwordMutation.isPending}
            rightIcon={
              <TouchableOpacity onPress={() => setShowConfirm((v) => !v)} className="w-8 h-8 items-center justify-center">
                {showConfirm ? <EyeOff color="#9ca3af" size={18} /> : <Eye color="#9ca3af" size={18} />}
              </TouchableOpacity>
            }
          />

          {pwMsg && (
            <View className={`rounded-lg px-4 py-3 mb-3 ${pwMsg.ok ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
              <Text className={`text-sm ${pwMsg.ok ? 'text-green-700' : 'text-red-700'}`}>{pwMsg.text}</Text>
            </View>
          )}

          <TouchableOpacity
            className={`rounded-lg h-12 items-center justify-center ${passwordMutation.isPending ? 'bg-emerald-300' : 'bg-emerald-600'}`}
            onPress={submitPassword}
            disabled={passwordMutation.isPending}
            activeOpacity={0.8}
          >
            <Text className="text-white font-semibold">
              {passwordMutation.isPending ? 'Changing…' : 'Change password'}
            </Text>
          </TouchableOpacity>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

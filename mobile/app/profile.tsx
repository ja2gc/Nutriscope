import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Eye, EyeOff } from 'lucide-react-native';
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
import api from '../lib/api';
import type { UserProfile } from '../lib/auth';
import { changedPersonNameFields, personNameFormValues } from '../lib/personName';

async function fetchMe(): Promise<UserProfile> {
  const res = await api.get<{ data: UserProfile } | UserProfile>('/api/auth/me');
  if (res.data && typeof (res.data as { data: UserProfile }).data === 'object') {
    return (res.data as { data: UserProfile }).data;
  }
  return res.data as UserProfile;
}

async function updateProfile(body: {
  first_name?: string;
  last_name?: string;
  /** @deprecated Kept for API compatibility. */
  name?: string;
  email: string;
  contact_number: string | null;
}): Promise<UserProfile> {
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

async function updateRecoveryEmail(body: { recovery_email: string }): Promise<UserProfile> {
  const res = await api.patch<{ user: UserProfile | { data: UserProfile } }>('/api/auth/recovery-email', body);
  const user = res.data.user;
  if (user && typeof (user as { data: UserProfile }).data === 'object') {
    return (user as { data: UserProfile }).data;
  }
  return user as UserProfile;
}

async function verifyRecoveryEmail(body: { code: string }): Promise<UserProfile> {
  const res = await api.post<{ user: UserProfile | { data: UserProfile } }>('/api/auth/recovery-email/verify', body);
  const user = res.data.user;
  if (user && typeof (user as { data: UserProfile }).data === 'object') {
    return (user as { data: UserProfile }).data;
  }
  return user as UserProfile;
}

function FormField({
  label,
  value,
  onChangeText,
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
  keyboardType?: 'default' | 'email-address' | 'phone-pad';
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
          accessibilityLabel={label}
          onChangeText={onChangeText}
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

function ReadOnlyField({ label, value }: { label: string; value: string }) {
  return (
    <View className="mb-4">
      <Text className="text-sm font-medium text-gray-700 mb-1">{label}</Text>
      <View className="border border-gray-200 rounded-lg px-4 h-12 justify-center bg-gray-50">
        <Text className="text-base text-gray-600">{value}</Text>
      </View>
    </View>
  );
}

export default function ProfileScreen() {
  const insets = useSafeAreaInsets();
  const queryClient = useQueryClient();

  const [firstName, setFirstName] = useState('');
  const [lastName, setLastName] = useState('');
  const [email, setEmail] = useState('');
  const [contactNumber, setContactNumber] = useState('');
  const [firstNameError, setFirstNameError] = useState<string | null>(null);
  const [lastNameError, setLastNameError] = useState<string | null>(null);
  const [emailError, setEmailError] = useState<string | null>(null);
  const [profileMsg, setProfileMsg] = useState<{ ok: boolean; text: string } | null>(null);

  const [recoveryEmail, setRecoveryEmail] = useState('');
  const [recoveryCode, setRecoveryCode] = useState('');
  const [recoveryEmailError, setRecoveryEmailError] = useState<string | null>(null);
  const [recoveryCodeError, setRecoveryCodeError] = useState<string | null>(null);
  const [recoveryMsg, setRecoveryMsg] = useState<{ ok: boolean; text: string } | null>(null);

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
      const values = personNameFormValues(user);
      setFirstName(values.firstName);
      setLastName(values.lastName);
      setEmail(user.email);
      setRecoveryEmail(user.recovery_email ?? '');
      setContactNumber(user.contact_number ?? '');
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

  const recoveryEmailMutation = useMutation({
    mutationFn: updateRecoveryEmail,
    onSuccess: (updated) => {
      queryClient.setQueryData(['me'], updated);
      setRecoveryCode('');
      setRecoveryMsg({
        ok: true,
        text: updated.recovery_email_verified
          ? 'Recovery email saved.'
          : 'Verification code sent.',
      });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Recovery email update failed.';
      setRecoveryMsg({ ok: false, text: msg });
    },
  });

  const recoveryVerifyMutation = useMutation({
    mutationFn: verifyRecoveryEmail,
    onSuccess: (updated) => {
      queryClient.setQueryData(['me'], updated);
      setRecoveryCode('');
      setRecoveryMsg({ ok: true, text: 'Recovery email verified.' });
    },
    onError: (err: unknown) => {
      const msg =
        (err as { response?: { data?: { message?: string } } })?.response?.data?.message ??
        'Recovery email verification failed.';
      setRecoveryMsg({ ok: false, text: msg });
    },
  });

  function validatePersonName() {
    if (!user) return false;

    try {
      changedPersonNameFields(user, firstName, lastName);
      setFirstNameError(null);
      setLastNameError(null);
      return true;
    } catch (error) {
      const message = error instanceof Error
        ? error.message
        : 'First and last name are both required when changing your name.';
      const firstNameInvalid = !firstName.trim() || message.startsWith('First name');
      const lastNameInvalid = !lastName.trim() || message.startsWith('Last name');
      setFirstNameError(firstNameInvalid || !lastNameInvalid ? message : null);
      setLastNameError(lastNameInvalid ? message : null);
      return false;
    }
  }

  function validateProfile() {
    let valid = validatePersonName();
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

  function validateRecoveryEmail() {
    if (!recoveryEmail.trim()) {
      setRecoveryEmailError('Recovery email is required.');
      return false;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recoveryEmail.trim())) {
      setRecoveryEmailError('Enter a valid email.');
      return false;
    }
    setRecoveryEmailError(null);
    return true;
  }

  function validateRecoveryCode() {
    if (!/^\d{6}$/.test(recoveryCode.trim())) {
      setRecoveryCodeError('Enter the 6-digit code.');
      return false;
    }
    setRecoveryCodeError(null);
    return true;
  }

  function submitProfile() {
    setProfileMsg(null);
    if (!validateProfile()) return;
    if (!user) return;
    const nameFields = changedPersonNameFields(user, firstName, lastName);
    profileMutation.mutate({
      ...(nameFields ?? {}),
      email: email.trim(),
      contact_number: contactNumber.trim() || null,
    });
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

  function submitRecoveryEmail() {
    setRecoveryMsg(null);
    if (!validateRecoveryEmail()) return;
    recoveryEmailMutation.mutate({ recovery_email: recoveryEmail.trim() });
  }

  function submitRecoveryCode() {
    setRecoveryMsg(null);
    if (!validateRecoveryCode()) return;
    recoveryVerifyMutation.mutate({ code: recoveryCode.trim() });
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
        {/* Role & Status card */}
        <View className="mx-4 bg-white rounded-xl border border-gray-100 p-4 mb-4">
          <Text className="text-base font-semibold text-gray-800 mb-4">Account Info</Text>
          <View className="flex-row gap-3">
            <View className="flex-1">
              <ReadOnlyField label="Role" value={user?.role ?? '—'} />
            </View>
            <View className="flex-1">
              <View className="mb-4">
                <Text className="text-sm font-medium text-gray-700 mb-1">Status</Text>
                <View className="border border-gray-200 rounded-lg px-4 h-12 justify-center bg-gray-50 flex-row items-center gap-2">
                  <View className={`w-2 h-2 rounded-full ${user?.is_active ? 'bg-emerald-500' : 'bg-red-400'}`} />
                  <Text className={`text-base ${user?.is_active ? 'text-emerald-700' : 'text-red-600'}`}>
                    {user?.is_active ? 'Active' : 'Inactive'}
                  </Text>
                </View>
              </View>
            </View>
          </View>
        </View>

        {/* Account card */}
        <View className="mx-4 bg-white rounded-xl border border-gray-100 p-4 mb-4">
          <Text className="text-base font-semibold text-gray-800 mb-4">Account</Text>

          <FormField
            label="First name"
            value={firstName}
            onChangeText={setFirstName}
            autoCapitalize="words"
            error={firstNameError}
            onBlur={validatePersonName}
            editable={!profileMutation.isPending}
          />
          <FormField
            label="Last name"
            value={lastName}
            onChangeText={setLastName}
            autoCapitalize="words"
            error={lastNameError}
            onBlur={validatePersonName}
            editable={!profileMutation.isPending}
          />
          <FormField
            label="Sign-in email"
            value={email}
            onChangeText={setEmail}
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
          <Text className="text-xs text-gray-500 -mt-2 mb-4">
            Use this email when signing in. Password reset links are sent to your verified recovery email below.
          </Text>
          <FormField
            label="Contact Number"
            value={contactNumber}
            onChangeText={setContactNumber}
            keyboardType="phone-pad"
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
        <View className="mx-4 bg-white rounded-xl border border-gray-100 p-4 mb-4">
          <Text className="text-base font-semibold text-gray-800 mb-4">Recovery email</Text>
          <Text className="text-sm text-gray-500 mb-4 leading-5">
            {user?.must_set_recovery_email
              ? 'Add the recovery email required for first-login setup. No verification code is needed.'
              : 'Changing an existing recovery email requires a code before the old address is replaced.'}
          </Text>

          <FormField
            label="Recovery email for password resets"
            value={recoveryEmail}
            onChangeText={setRecoveryEmail}
            keyboardType="email-address"
            autoCapitalize="none"
            error={recoveryEmailError}
            onBlur={validateRecoveryEmail}
            editable={!recoveryEmailMutation.isPending}
          />

          <TouchableOpacity
            className={`rounded-lg h-12 items-center justify-center ${recoveryEmailMutation.isPending ? 'bg-emerald-300' : 'bg-emerald-600'}`}
            onPress={submitRecoveryEmail}
            disabled={recoveryEmailMutation.isPending}
            activeOpacity={0.8}
          >
            <Text className="text-white font-semibold">
              {recoveryEmailMutation.isPending
                ? 'Saving...'
                : user?.must_set_recovery_email
                  ? 'Save recovery email'
                  : 'Send verification code'}
            </Text>
          </TouchableOpacity>

          {!user?.must_set_recovery_email
            && (Boolean(user?.pending_recovery_email) || !user?.recovery_email_verified) ? (
            <>
              <View className="h-px bg-gray-100 my-4" />

          <FormField
            label="Verification code"
            value={recoveryCode}
            onChangeText={setRecoveryCode}
            keyboardType="phone-pad"
            error={recoveryCodeError}
            onBlur={validateRecoveryCode}
            editable={!recoveryVerifyMutation.isPending}
          />

          <TouchableOpacity
            className={`rounded-lg h-12 items-center justify-center ${recoveryVerifyMutation.isPending ? 'bg-emerald-300' : 'bg-emerald-600'}`}
            onPress={submitRecoveryCode}
            disabled={recoveryVerifyMutation.isPending}
            activeOpacity={0.8}
          >
            <Text className="text-white font-semibold">
              {recoveryVerifyMutation.isPending ? 'Verifying…' : 'Verify recovery email'}
            </Text>
          </TouchableOpacity>

            </>
          ) : null}

          {user?.recovery_email_verified && user.recovery_email === recoveryEmail ? (
            <Text className="text-emerald-700 text-sm mt-3">Recovery email verified.</Text>
          ) : null}

          {recoveryMsg && (
            <View className={`rounded-lg px-4 py-3 mt-3 ${recoveryMsg.ok ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'}`}>
              <Text className={`text-sm ${recoveryMsg.ok ? 'text-green-700' : 'text-red-700'}`}>{recoveryMsg.text}</Text>
            </View>
          )}
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

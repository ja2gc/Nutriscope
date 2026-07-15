import * as SecureStore from 'expo-secure-store';

export interface UserProfile {
  id: number;
  first_name: string | null;
  last_name: string | null;
  display_name: string;
  /** @deprecated Use display_name for presentation. */
  name: string;
  email: string;
  recovery_email: string | null;
  recovery_email_verified: boolean;
  contact_number: string | null;
  role: string;
  is_active: boolean;
}

export interface LoginResponse {
  token: string;
  user?: UserProfile;
}

const TOKEN_KEY = 'auth_token';
type AuthListener = (token: string | null) => void;

const listeners = new Set<AuthListener>();

function notify(token: string | null) {
  listeners.forEach((listener) => listener(token));
}

export async function getToken(): Promise<string | null> {
  return SecureStore.getItemAsync(TOKEN_KEY);
}

export async function setToken(token: string): Promise<void> {
  await SecureStore.setItemAsync(TOKEN_KEY, token);
  notify(token);
}

export async function clearToken(): Promise<void> {
  await SecureStore.deleteItemAsync(TOKEN_KEY);
  notify(null);
}

export function subscribeAuth(listener: AuthListener): () => void {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

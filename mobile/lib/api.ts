import axios from 'axios';
import { router } from 'expo-router';
import { clearToken, getToken } from './auth';

// All callers prefix routes with `/api/...`, so the base URL must be the bare
// origin. Normalize defensively: strip a trailing slash and a trailing `/api`
// so a misconfigured EXPO_PUBLIC_API_URL (e.g. ".../api") can't double up to
// `/api/api/...` in a production build.
const rawBase = process.env.EXPO_PUBLIC_API_URL ?? '';
const baseURL = rawBase.replace(/\/+$/, '').replace(/\/api$/, '');

const api = axios.create({
  baseURL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
});

// Inject stored token on every request
api.interceptors.request.use(async (config) => {
  const token = await getToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// On 401 → clear token and redirect to login
api.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      await clearToken();
      router.replace('/login');
    }
    return Promise.reject(error);
  },
);

export default api;

export type AuthRedirectTarget = '/(tabs)' | '/login' | null;

const publicRoutes = new Set(['/login', '/forgot-password']);

export function getAuthRedirect(
  isAuthenticated: boolean,
  pathname: string,
): AuthRedirectTarget {
  const isPublic = publicRoutes.has(pathname);

  if (isAuthenticated && isPublic) {
    return '/(tabs)';
  }

  if (!isAuthenticated && !isPublic) {
    return '/login';
  }

  return null;
}

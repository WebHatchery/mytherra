import type { User } from '../entities/auth';
import { useAuthStore, type GuestStoredSession } from '../stores/authStore';

export const FRONTPAGE_AUTH_STORAGE_KEY = 'auth-storage';

interface FrontpageAuthState {
  state?: {
    token?: string | null;
    user?: Partial<User> | null;
    loginUrl?: string | null;
    isAuthenticated?: boolean;
  };
  version?: number;
}

const parseStoredJson = <T>(key: string): T | null => {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : null;
  } catch {
    return null;
  }
};

const readFrontpageState = (): FrontpageAuthState => {
  return parseStoredJson<FrontpageAuthState>(FRONTPAGE_AUTH_STORAGE_KEY) ?? {};
};

export const readFrontpageToken = (): string | null => {
  const parsed = readFrontpageState();
  if (parsed.state?.user?.is_guest) return null;

  const token = parsed.state?.token;
  return typeof token === 'string' && token.trim() !== '' ? token : null;
};

export const readFrontpageUser = (): Partial<User> | null => {
  const user = readFrontpageState().state?.user;
  return user && !user.is_guest ? user : null;
};

export const persistFrontpageToken = (token: string): void => {
  localStorage.setItem(
    FRONTPAGE_AUTH_STORAGE_KEY,
    JSON.stringify({ state: { token, isAuthenticated: true, user: null }, version: 0 })
  );
};

export const clearFrontpageAuth = (): void => {
  localStorage.removeItem(FRONTPAGE_AUTH_STORAGE_KEY);
};

export const persistLoginUrl = (loginUrl: string): void => {
  useAuthStore.getState().setLoginUrl(loginUrl);
};

export const readGuestSession = (): GuestStoredSession | null => {
  return useAuthStore.getState().guestSession;
};

export const saveGuestSession = (session: GuestStoredSession): void => {
  useAuthStore.getState().setGuestSession(session);
};

export const clearGuestSession = (): void => {
  useAuthStore.getState().setGuestSession(null);
};

export const readActiveToken = (): string | null => {
  return readFrontpageToken() ?? readGuestSession()?.token ?? null;
};

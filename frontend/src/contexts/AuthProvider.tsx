import React, { useCallback, useEffect, useMemo, useRef, useState, type ReactNode } from 'react';
import type { User } from '../entities/auth';
import { AuthContext, type Preferences } from './authContext';
import { setTokenProvider } from './authHeaders';
import { apiClient } from '../api/apiClient';
import {
  clearFrontpageAuth,
  clearGuestSession,
  persistFrontpageToken,
  persistLoginUrl,
  readFrontpageToken,
  readFrontpageUser,
  readGuestSession,
  saveGuestSession,
} from '../utils/authStorage';

interface AuthProviderProps {
  children: ReactNode;
}

interface PortalUrlResponse {
  success: boolean;
  data?: {
    login_url?: string;
  };
}

interface SessionResponse {
  success: boolean;
  data?: {
    user?: User;
  } & Partial<User>;
  message?: string;
}

const withRedirectParam = (urlValue: string): string => {
  try {
    const url = new URL(urlValue, window.location.origin);
    url.searchParams.set('redirect', window.location.href);
    return url.toString();
  } catch {
    return urlValue;
  }
};

const isAxiosLikeError = (error: unknown): error is { response?: { status?: number } } => {
  return typeof error === 'object' && error !== null;
};

export const AuthProvider: React.FC<AuthProviderProps> = ({ children }) => {
  const [user, setUser] = useState<User | null>(null);
  const [token, setToken] = useState<string | null>(() => readFrontpageToken() || readGuestSession()?.token || null);
  const [authMode, setAuthMode] = useState<'frontpage' | 'guest' | null>(() => readFrontpageToken() ? 'frontpage' : (readGuestSession()?.token ? 'guest' : null));
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const hasAttemptedGuestLinkRef = useRef(false);

  const login = useCallback(async () => {
    try {
      const response = await apiClient.get<PortalUrlResponse>('/auth/login-info');
      const loginUrl = response.data.data?.login_url;
      if (response.data.success && loginUrl) {
        const targetUrl = withRedirectParam(loginUrl);
        persistLoginUrl(targetUrl);
        window.location.href = targetUrl;
      }
    } catch (err) {
      console.error('Failed to get login URL', err);
    }
  }, []);

  const continueAsGuest = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const existing = readGuestSession();
      if (existing?.token && existing.user) {
        setToken(existing.token);
        setAuthMode('guest');
        setUser(existing.user);
        return;
      }

      const response = await apiClient.post('/auth/guest-session');
      const data = response.data as { success: boolean; data?: { token?: string; user?: User }; message?: string };
      if (!data.success || !data.data?.token || !data.data?.user) {
        throw new Error(data.message || 'Failed to create guest session');
      }

      const session = { token: data.data.token, user: { ...data.data.user, is_guest: true, auth_type: 'guest' as const } };
      saveGuestSession(session);
      setToken(session.token);
      setAuthMode('guest');
      setUser(session.user);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const getLinkAccountUrl = useCallback(() => {
    const signupUrl = import.meta.env.VITE_WEB_HATCHERY_SIGNUP_URL;
    if (!signupUrl) {
      throw new Error('VITE_WEB_HATCHERY_SIGNUP_URL is required');
    }
    return withRedirectParam(signupUrl);
  }, []);

  const initializeUser = useCallback(async (authToken: string | null, mode: 'frontpage' | 'guest' | null) => {
    if (!authToken) {
      setIsLoading(false);
      return;
    }

    try {
      setIsLoading(true);
      const response = await apiClient.get<SessionResponse>('/auth/session', {
        headers: {
          Authorization: `Bearer ${authToken}`
        }
      });

      const data = response.data;
      const maybeUser = data.data && 'user' in data.data ? data.data.user : (data.data as User | undefined);
      if (data.success && maybeUser) {
        const frontpageUser = mode === 'frontpage' ? readFrontpageUser() : null;
        const isGuest = Boolean(maybeUser.is_guest || mode === 'guest');
        setUser({
          ...maybeUser,
          username: mode === 'frontpage' ? (frontpageUser?.username as string || maybeUser.username) : maybeUser.username,
          display_name: mode === 'frontpage' ? (frontpageUser?.display_name as string || maybeUser.display_name) : maybeUser.display_name,
          is_guest: isGuest,
          auth_type: isGuest ? 'guest' : 'frontpage',
        });
      }
    } catch (err: unknown) {
      if (isAxiosLikeError(err) && err.response?.status === 401) {
        setUser(null);
        setToken(null);
        setAuthMode(null);
      } else {
        setError(err instanceof Error ? err.message : 'Failed to initialize user');
      }
    } finally {
      setIsLoading(false);
    }
  }, []);

  useEffect(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const urlToken = urlParams.get('token');

    if (urlToken) {
      setToken(urlToken);
      setAuthMode('frontpage');
      persistFrontpageToken(urlToken);
      window.history.replaceState({}, document.title, window.location.pathname);
      void initializeUser(urlToken, 'frontpage');
      return;
    }

    if (token) {
      void initializeUser(token, authMode);
    } else {
      setIsLoading(false);
    }
  }, [authMode, initializeUser, token]);

  useEffect(() => {
    if (token) setTokenProvider(async () => token);
    else setTokenProvider(null);
  }, [token]);

  useEffect(() => {
    const guestSession = readGuestSession();
    if (!guestSession?.token || hasAttemptedGuestLinkRef.current) return;
    if (authMode !== 'frontpage' || !token || !user || user.is_guest) return;
    hasAttemptedGuestLinkRef.current = true;

    (async () => {
      try {
        await apiClient.post('/auth/link-guest', { guest_token: guestSession.token }, {
          headers: { Authorization: `Bearer ${token}` }
        });
        clearGuestSession();
      } catch (err) {
        setError(err instanceof Error ? err.message : 'Failed to link guest account');
      } finally {
        await initializeUser(token, 'frontpage');
      }
    })();
  }, [authMode, initializeUser, token, user]);

  const logout = useCallback(() => {
    if (authMode === 'guest') {
      clearGuestSession();
    }
    setUser(null);
    setToken(null);
    setAuthMode(null);
    clearFrontpageAuth();
  }, [authMode]);

  const refreshUser = useCallback(async () => {
    if (token) await initializeUser(token, authMode);
  }, [authMode, initializeUser, token]);

  const updatePreferences = useCallback(async (preferences: Preferences) => {
    if (!user || !token) return;
    try {
      const response = await apiClient.put('/auth/preferences', { preferences }, { headers: { Authorization: `Bearer ${token}` } });
      if (response.status >= 200 && response.status < 300) {
        setUser(prev => (prev ? { ...prev, game_preferences: preferences } : null));
      }
    } catch (err) {
      console.error('Failed to update preferences:', err);
    }
  }, [token, user]);

  const isAdmin = useCallback((): boolean => user?.role === 'admin', [user?.role]);
  const hasRole = useCallback((role: string): boolean => (user?.role === 'admin' ? true : user?.role === role), [user?.role]);

  const value = useMemo(() => ({
    user,
    token,
    isAuthenticated: !!user && !!token,
    isLoading,
    error,
    authMode,
    login,
    continueAsGuest,
    getLinkAccountUrl,
    logout,
    refreshUser,
    updatePreferences,
    isAdmin,
    hasRole,
  }), [authMode, continueAsGuest, error, getLinkAccountUrl, hasRole, isAdmin, isLoading, login, logout, refreshUser, token, updatePreferences, user]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

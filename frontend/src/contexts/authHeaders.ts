let tokenProvider: (() => Promise<string | null>) | null = null;
const GUEST_AUTH_STORAGE_KEY = 'mytherra-guest-session';

export const setTokenProvider = (provider: (() => Promise<string | null>) | null) => {
  tokenProvider = provider;
};

export const getAuthHeaders = async (): Promise<HeadersInit> => {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };

  if (tokenProvider) {
    try {
      const token = await tokenProvider();
      if (token) headers['Authorization'] = `Bearer ${token}`;
      return headers;
    } catch {
      // Fall through to storage
    }
  }

  let token: string | null = null;
  try {
    const storage = localStorage.getItem('auth-storage');
    if (storage) {
      const parsed = JSON.parse(storage) as { state?: { token?: string; user?: { is_guest?: boolean } } };
      token = parsed.state?.user?.is_guest ? null : (parsed.state?.token ?? null);
    }

    if (!token) {
      const guestStorage = localStorage.getItem(GUEST_AUTH_STORAGE_KEY);
      if (guestStorage) {
        const parsedGuest = JSON.parse(guestStorage) as { token?: string };
        token = parsedGuest.token ?? null;
      }
    }
  } catch {
    // ignore parse error
  }

  if (token) headers['Authorization'] = `Bearer ${token}`;
  return headers;
};

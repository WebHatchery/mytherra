import { readActiveToken } from '../utils/authStorage';

let tokenProvider: (() => Promise<string | null>) | null = null;

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

  const token = readActiveToken();
  if (token) headers['Authorization'] = `Bearer ${token}`;
  return headers;
};

import { createContext } from 'react';
import type { User } from '../entities/auth';

export type Preferences = Record<string, unknown>;

export interface AuthContextType {
  user: User | null;
  token: string | null;
  isAuthenticated: boolean;
  isLoading: boolean;
  error: string | null;
  authMode: 'frontpage' | 'guest' | null;
  login: () => Promise<void>;
  continueAsGuest: () => Promise<void>;
  getLinkAccountUrl: () => string;
  logout: () => void;
  refreshUser: () => Promise<void>;
  updatePreferences: (preferences: Preferences) => Promise<void>;
  isAdmin: () => boolean;
  hasRole: (role: string) => boolean;
}

export const AuthContext = createContext<AuthContextType | undefined>(undefined);

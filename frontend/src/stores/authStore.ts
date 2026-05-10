import { create } from 'zustand';
import { persist } from 'zustand/middleware';
import type { User } from '../entities/auth';

export interface AuthState {
    loginUrl: string | null;
    guestSession: GuestStoredSession | null;
    setLoginUrl: (url: string | null) => void;
    setGuestSession: (session: GuestStoredSession | null) => void;
    clearAuthState: () => void;
}

export interface GuestStoredSession {
    token: string;
    user: User;
}

export const useAuthStore = create<AuthState>()(
    persist(
        (set) => ({
            loginUrl: null,
            guestSession: null,
            setLoginUrl: (url) => set({ loginUrl: url }),
            setGuestSession: (session) => set({ guestSession: session }),
            clearAuthState: () => set({ loginUrl: null, guestSession: null }),
        }),
        {
            name: 'mytherra-auth-state',
            partialize: (state) => ({
                loginUrl: state.loginUrl,
                guestSession: state.guestSession,
            }),
        }
    )
);

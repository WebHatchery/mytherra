// @vitest-environment jsdom
import React, { type ReactNode } from 'react';
import { cleanup, render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, Route, Routes } from 'react-router-dom';
import { afterEach, describe, expect, it } from 'vitest';
import type { GameStatus } from '../src/api/apiService';
import Header from '../src/components/Header';
import BaseLayout from '../src/components/BaseLayout';
import DashboardLastTickPanel from '../src/components/DashboardLastTickPanel';
import { AuthContext, type AuthContextType } from '../src/contexts/authContext';
import type { User } from '../src/entities/auth';

const guestUser: User = {
  id: 'guest-1',
  auth_user_id: null,
  email: 'guest@example.test',
  username: 'guest',
  display_name: 'Guest Oracle',
  divine_influence: 100,
  divine_favor: 80,
  betting_stats: {},
  game_preferences: {},
  role: 'player',
  roles: ['player'],
  is_active: true,
  is_guest: true,
  auth_type: 'guest',
};

const authContext: AuthContextType = {
  user: guestUser,
  token: 'test-token',
  isAuthenticated: true,
  isLoading: false,
  error: null,
  authMode: 'guest',
  login: async () => {},
  continueAsGuest: async () => {},
  getLinkAccountUrl: () => 'https://example.test/signup',
  logout: () => {},
  refreshUser: async () => {},
  updatePreferences: async () => {},
  isAdmin: () => false,
  hasRole: role => guestUser.roles?.includes(role) ?? false,
};

const simulation: NonNullable<GameStatus['simulation']> = {
  enabled: true,
  lastTickAt: '2026-06-15T08:08:50.000Z',
  queue: {
    jobs: 2,
    failedJobs: 1,
    available: true,
  },
  lastTickResult: {
    previousYear: 14,
    currentYear: 15,
    completedAt: '2026-06-15T08:08:50.000Z',
    regions: {
      processed: 1,
      changed: 1,
      events: 1,
      changes: [
        {
          id: 'region-1',
          name: 'Mystic Vale',
          reason: 'Magic pressure shifted the region.',
          summary: 'Mystic Vale changed during the latest tick.',
          eventId: 'event-region-1',
          before: {
            dangerLevel: 39,
            chaos: 43,
            culture: 'martial',
          },
          after: {
            dangerLevel: 37,
            chaos: 41,
            culture: 'mystical',
          },
        },
      ],
    },
    settlements: {
      processed: 0,
      changed: 0,
      events: 0,
      changes: [],
    },
    resources: {
      processed: 0,
      changed: 0,
      events: 0,
      changes: [],
    },
    heroes: {
      processed: 0,
      changed: 0,
      events: 0,
      changes: [],
    },
    bets: {
      processed: 1,
      won: 1,
      lost: 0,
      expired: 0,
      resolved: [
        {
          id: 'bet-1',
          description: 'Mystic Vale danger falls',
          status: 'won',
          notes: 'Resolved by regional danger dropping below the threshold.',
          eventId: 'event-bet-1',
        },
      ],
    },
  },
};

const gameStatus: GameStatus = {
  currentYear: 15,
  divineFavor: 80,
  simulation,
};

const renderWithAuth = (children: ReactNode, initialEntries = ['/']) =>
  render(
    <AuthContext.Provider value={authContext}>
      <MemoryRouter initialEntries={initialEntries}>{children}</MemoryRouter>
    </AuthContext.Provider>
  );

const assertSharedSimulationStrip = () => {
  expect(screen.getByText('Current Year: 15')).toBeTruthy();
  expect(screen.getByText('Divine Favor: 80')).toBeTruthy();
  expect(screen.getByText('Simulation enabled')).toBeTruthy();
  expect(screen.getByText(/Last Tick: Year 14 to 15/)).toBeTruthy();
  expect(screen.getByText('2 queued, 1 failed')).toBeTruthy();
};

const CrossPageVisibilityRoutes: React.FC = () => {
  const page = (heading: string) => (
    <BaseLayout gameStatus={gameStatus}>
      <h2>{heading}</h2>
    </BaseLayout>
  );

  return (
    <Routes>
      <Route path="/" element={page('Chronicles of Mytherra')} />
      <Route path="/dashboard" element={page('World Dashboard')} />
      <Route path="/world-map" element={page('World of Mytherra')} />
      <Route path="/heroes" element={page('Heroes of Mytherra')} />
      <Route path="/betting" element={page('Divine Betting Workflow')} />
    </Routes>
  );
};

afterEach(() => {
  cleanup();
});

describe('rendered simulation visibility', () => {
  it('shows the shared simulation status strip from the status payload', () => {
    renderWithAuth(<Header gameStatus={gameStatus} />);

    assertSharedSimulationStrip();
  });

  it('shows last-tick ledger deltas and event links as rendered UI', () => {
    render(
      <MemoryRouter>
        <DashboardLastTickPanel simulation={simulation} />
      </MemoryRouter>
    );

    expect(screen.getByRole('heading', { name: 'Change Ledger' })).toBeTruthy();
    expect(screen.getByText('2 highlighted changes')).toBeTruthy();
    expect(screen.getByText('Danger Level: 39 -> 37 (-2)')).toBeTruthy();
    expect(screen.getByText('Chaos: 43 -> 41 (-2)')).toBeTruthy();
    expect(screen.getByText('Culture: martial -> mystical')).toBeTruthy();
    expect(
      screen.getAllByText('Resolved by regional danger dropping below the threshold.')
    ).toHaveLength(2);

    const regionEventLinks = screen.getAllByRole('link', { name: 'Event event-region-1' });
    const betEventLinks = screen.getAllByRole('link', { name: 'Event event-bet-1' });

    expect(regionEventLinks[0].getAttribute('href')).toBe('/events/event-region-1');
    expect(betEventLinks[0].getAttribute('href')).toBe('/events/event-bet-1');
  });

  it('keeps the shared tick status visible while navigating core pages', async () => {
    const user = userEvent.setup();
    renderWithAuth(<CrossPageVisibilityRoutes />, ['/dashboard']);

    const routes = [
      { href: '/', heading: 'Chronicles of Mytherra' },
      { href: '/world-map', heading: 'World of Mytherra' },
      { href: '/heroes', heading: 'Heroes of Mytherra' },
      { href: '/betting', heading: 'Divine Betting Workflow' },
      { href: '/dashboard', heading: 'World Dashboard' },
    ];

    expect(screen.getByRole('heading', { name: 'World Dashboard' })).toBeTruthy();
    assertSharedSimulationStrip();

    for (const route of routes) {
      const link = screen
        .getAllByRole('link')
        .find(element => element.getAttribute('href') === route.href);
      expect(link).toBeTruthy();

      await user.click(link as HTMLAnchorElement);

      expect(screen.getByRole('heading', { name: route.heading })).toBeTruthy();
      assertSharedSimulationStrip();
    }
  });
});

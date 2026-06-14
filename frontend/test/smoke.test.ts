import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';

const src = (...parts: string[]) => resolve(__dirname, '..', 'src', ...parts);

describe('frontend gameplay smoke wiring', () => {
  it('registers the primary protected game routes', () => {
    const appSource = readFileSync(src('App.tsx'), 'utf8');

    for (const route of ['"/"', '"/dashboard"', '"/world-map"', '"/heroes"', '"/betting"']) {
      expect(appSource).toContain(`path=${route}`);
    }
  });

  it('offers guest entry from the protected route gate', () => {
    const protectedRouteSource = readFileSync(src('components', 'ProtectedRoute.tsx'), 'utf8');

    expect(protectedRouteSource).toContain('Continue as Guest');
    expect(protectedRouteSource).toContain('Login with WebHatchery');
    expect(protectedRouteSource).toContain('continueAsGuest');
  });

  it('keeps gameplay API helpers wired to backend routes', () => {
    const apiSource = readFileSync(src('api', 'apiService.ts'), 'utf8');

    for (const endpoint of [
      'regions',
      'heroes',
      'events?page=',
      'settlements',
      'landmarks',
      'resource-nodes',
      'bets',
      'speculation-events',
      'betting-odds',
      'influence/region/${payload.entityId}',
      'influence/hero/${payload.entityId}',
    ]) {
      expect(apiSource).toContain(endpoint);
    }
  });

  it('keeps dashboard statistics and export wired', () => {
    const dashboardSource = readFileSync(src('pages', 'Dashboard.tsx'), 'utf8');

    expect(dashboardSource).toContain('statisticsService.getSummary()');
    expect(dashboardSource).toContain('statisticsService.getHeroStats()');
    expect(dashboardSource).toContain('statisticsService.getRegionStats()');
    expect(dashboardSource).toContain('statisticsService.getFinancialStats()');
    expect(dashboardSource).toContain("apiClient.get('/export/full'");
  });
});

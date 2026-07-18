import React, { useMemo } from 'react';
import { useSearchParams } from 'react-router-dom';
import BaseLayout from '../components/BaseLayout';
import PageHeader from '../components/PageHeader';
import EventsStats from '../components/EventsStats';
import EventSection from '../components/EventSection';
import EmptyState from '../components/EmptyState';
import Pagination from '../components/Pagination';
import { useGameStatus } from '../hooks/useGameStatus';
import { useEvents } from '../hooks/useEvents';
import type { GameEventFilters } from '../api/apiService';

const eventTypeOptions = [
  'general',
  'game_tick',
  'region_tick',
  'settlement_tick',
  'resource_tick',
  'admin_world_edit',
  'hero_level',
  'hero_travel',
  'hero_death',
  'hero_revival',
  'bet_resolution',
  'divine_influence',
  'region_influence',
  'hero_influence',
  'empower',
  'guide',
  'guide-research',
  'founding',
  'mystical',
  'economic',
] as const;

const formatLabel = (value: string): string =>
  value
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const EventsPage: React.FC = () => {
  const [searchParams, setSearchParams] = useSearchParams();
  const regionId = searchParams.get('regionId') ?? undefined;
  const heroId = searchParams.get('heroId') ?? undefined;
  const settlementId = searchParams.get('settlementId') ?? undefined;
  const landmarkId = searchParams.get('landmarkId') ?? undefined;
  const resourceId = searchParams.get('resourceId') ?? undefined;
  const era = searchParams.get('era') ?? undefined;
  const type = searchParams.get('type') ?? undefined;
  const status = searchParams.get('status') ?? undefined;
  const filters = useMemo<GameEventFilters>(
    () => ({
      regionId,
      heroId,
      settlementId,
      landmarkId,
      resourceId,
      era,
      type,
      status,
    }),
    [regionId, heroId, settlementId, landmarkId, resourceId, era, type, status]
  );

  const {
    gameStatus,
    isLoading: isLoadingGameStatus,
    error: gameStatusError,
  } = useGameStatus({
    autoRefresh: true,
    refreshInterval: 10000,
  });

  const {
    events,
    isLoading: isLoadingEvents,
    error: eventsError,
    currentPage,
    eventsPerPage,
    loadEventsPage,
    refetch,
    categorizedEvents,
  } = useEvents({
    autoRefresh: true,
    refreshInterval: 30000,
    eventsPerPage: 20,
    filters,
  });

  const isLoading = isLoadingGameStatus || isLoadingEvents;
  const error = gameStatusError || eventsError;

  const { heroEvents, worldEvents, systemEvents } = categorizedEvents;
  const hasFilters = Boolean(regionId || heroId || settlementId || landmarkId || resourceId || era || type || status);

  const updateFilter = (key: keyof GameEventFilters, value: string) => {
    const nextParams = new URLSearchParams(searchParams);
    if (value) {
      nextParams.set(key, value);
    } else {
      nextParams.delete(key);
    }
    setSearchParams(nextParams);
  };

  const clearFilters = () => {
    setSearchParams(new URLSearchParams());
  };

  return (
    <BaseLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading events..."
      errorPrefix="Error loading events"
    >
      {/* Page Header */}
      <PageHeader
        title="Chronicles of Mytherra"
        subtitle={`Year ${gameStatus?.currentYear || 1} - Witness the unfolding tales of heroes and kingdoms`}
        icon="📜"
      />

      <section className="mb-6 bg-gray-800 rounded-lg p-4 border border-gray-700">
        <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-8 gap-3">
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Region ID</span>
            <input
              value={regionId ?? ''}
              onChange={event => updateFilter('regionId', event.target.value.trim())}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            />
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Hero ID</span>
            <input
              value={heroId ?? ''}
              onChange={event => updateFilter('heroId', event.target.value.trim())}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            />
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Settlement ID</span>
            <input
              value={settlementId ?? ''}
              onChange={event => updateFilter('settlementId', event.target.value.trim())}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            />
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Landmark ID</span>
            <input
              value={landmarkId ?? ''}
              onChange={event => updateFilter('landmarkId', event.target.value.trim())}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            />
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Resource ID</span>
            <input
              value={resourceId ?? ''}
              onChange={event => updateFilter('resourceId', event.target.value.trim())}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            />
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Era</span>
            <input
              type="number"
              min={1}
              value={era ?? ''}
              onChange={event => updateFilter('era', event.target.value.trim())}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            />
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Type</span>
            <select
              value={type ?? ''}
              onChange={event => updateFilter('type', event.target.value)}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            >
              <option value="">All</option>
              {eventTypeOptions.map(option => (
                <option key={option} value={option}>
                  {formatLabel(option)}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm text-gray-300">
            <span className="mb-1 block">Status</span>
            <select
              value={status ?? ''}
              onChange={event => updateFilter('status', event.target.value)}
              className="w-full rounded bg-gray-700 border border-gray-600 px-3 py-2 text-gray-100"
            >
              <option value="">All</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
            </select>
          </label>
        </div>
        {hasFilters && (
          <div className="mt-3 flex items-center justify-between gap-3 text-sm">
            <div className="text-gray-400">
              Filtered timeline:{' '}
              {[regionId, heroId, settlementId, landmarkId, resourceId, era ? `Era ${era}` : null, type, status]
                .filter(Boolean)
                .join(' • ')}
            </div>
            <button
              type="button"
              onClick={clearFilters}
              className="rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
            >
              Clear Filters
            </button>
          </div>
        )}
      </section>

      {/* Quick Stats */}
      <EventsStats
        heroEventsCount={heroEvents.length}
        worldEventsCount={worldEvents.length}
        systemEventsCount={systemEvents.length}
      />

      {/* Events Feed */}
      <div className="space-y-6">
        <EventSection
          title="Hero Chronicles"
          icon="⚔️"
          events={heroEvents}
          borderColor="yellow"
          titleColor="yellow"
        />

        <EventSection
          title="World Events"
          icon="🌍"
          events={worldEvents}
          borderColor="blue"
          titleColor="blue"
        />

        <EventSection
          title="Divine & System Events"
          icon="🔮"
          events={systemEvents}
          borderColor="purple"
          titleColor="purple"
        />
      </div>

      {/* No Events Message */}
      {events.length === 0 && (
        <EmptyState
          title="The Chronicles Begin..."
          message="No events have been recorded yet. The world awaits its first heroes and legends."
          icon="📖"
          actionButton={{
            label: 'Refresh Events',
            onClick: refetch,
          }}
        />
      )}

      {/* Pagination */}
      <Pagination
        currentPage={currentPage}
        onPageChange={loadEventsPage}
        hasNextPage={events.length >= eventsPerPage}
        hasPreviousPage={currentPage > 1}
        isLoading={isLoadingEvents}
        onRefresh={refetch}
        showRefresh={true}
      />
    </BaseLayout>
  );
};

export default EventsPage;

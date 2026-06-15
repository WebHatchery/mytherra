import { useState, useEffect, useCallback } from 'react';
import { GameEvent } from '../entities/event';
import { getGameEvents, type GameEventFilters } from '../api/apiService';

interface UseEventsOptions {
  autoRefresh?: boolean;
  refreshInterval?: number;
  eventsPerPage?: number;
  filters?: GameEventFilters;
}

interface UseEventsReturn {
  events: GameEvent[];
  isLoading: boolean;
  error: string | null;
  currentPage: number;
  eventsPerPage: number;
  loadEventsPage: (page: number) => Promise<void>;
  refetch: () => Promise<void>;
  categorizedEvents: {
    heroEvents: GameEvent[];
    worldEvents: GameEvent[];
    systemEvents: GameEvent[];
  };
}

export const useEvents = (options: UseEventsOptions = {}): UseEventsReturn => {
  const {
    autoRefresh = false,
    refreshInterval = 30000,
    eventsPerPage = 20,
    filters = {},
  } = options;

  const [events, setEvents] = useState<GameEvent[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const { regionId, heroId, settlementId, landmarkId, resourceId, era, type, status } = filters;

  const categorizeEvents = useCallback((events: GameEvent[]) => {
    // Hero Events: All events that have related heroes
    const heroEvents = events.filter(
      event => Array.isArray(event.relatedHeroIds) && event.relatedHeroIds.length > 0
    );

    // World Events: All events that have related regions
    const worldEvents = events.filter(
      event => Array.isArray(event.relatedRegionIds) && event.relatedRegionIds.length > 0
    );

    // System Events: Events that have no heroes or regions
    const systemEvents = events.filter(
      event =>
        (!event.relatedHeroIds || event.relatedHeroIds.length === 0) &&
        (!event.relatedRegionIds || event.relatedRegionIds.length === 0)
    );

    return { heroEvents, worldEvents, systemEvents };
  }, []);

  const loadEventsPage = useCallback(
    async (page: number) => {
      try {
        setIsLoading(true);
        const data = await getGameEvents(page, eventsPerPage, {
          regionId,
          heroId,
          settlementId,
          landmarkId,
          resourceId,
          era,
          type,
          status,
        });

        if (Array.isArray(data)) {
          setEvents(data);
          setCurrentPage(page);
        } else {
          console.warn('Unexpected response format from events API:', data);
          setEvents([]);
        }

        setError(null);
      } catch (err) {
        if (err instanceof Error) {
          setError(err.message);
        } else {
          setError('An unknown error occurred');
        }
        console.error('Failed to load events:', err);
      } finally {
        setIsLoading(false);
      }
    },
    [eventsPerPage, regionId, heroId, settlementId, landmarkId, resourceId, era, type, status]
  );

  const refetch = useCallback(() => {
    return loadEventsPage(currentPage);
  }, [loadEventsPage, currentPage]);

  // Initial load
  useEffect(() => {
    loadEventsPage(1);
  }, [loadEventsPage]);

  // Auto-refresh effect
  useEffect(() => {
    if (!autoRefresh || refreshInterval <= 0) return;

    const interval = setInterval(() => {
      refetch();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, refetch]);

  const categorizedEvents = categorizeEvents(events);

  return {
    events,
    isLoading,
    error,
    currentPage,
    eventsPerPage,
    loadEventsPage,
    refetch,
    categorizedEvents,
  };
};

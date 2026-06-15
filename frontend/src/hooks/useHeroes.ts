import { useState, useEffect, useCallback } from 'react';
import { Hero } from '../entities/hero';
import { getHeroes } from '../api/apiService';

interface UseHeroesOptions {
  autoRefresh?: boolean;
  refreshInterval?: number;
}

interface UseHeroesReturn {
  heroes: Hero[];
  isLoading: boolean;
  error: string | null;
  selectedHero: Hero | null;
  selectHero: (hero: Hero | null) => void;
  refetch: () => Promise<void>;
}

export const useHeroes = (options: UseHeroesOptions = {}): UseHeroesReturn => {
  const { autoRefresh = false, refreshInterval = 30000 } = options;

  const [heroes, setHeroes] = useState<Hero[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedHero, setSelectedHero] = useState<Hero | null>(null);

  const fetchHeroes = useCallback(async () => {
    try {
      setIsLoading(true);
      const heroesData = await getHeroes();
      setHeroes(heroesData);
      setSelectedHero(current =>
        current ? (heroesData.find(hero => hero.id === current.id) ?? null) : current
      );
      setError(null);
    } catch (err) {
      if (err instanceof Error) {
        setError(err.message);
      } else {
        setError('An unknown error occurred while fetching heroes.');
      }
      console.error('Failed to load heroes:', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const selectHero = useCallback(
    (hero: Hero | null) => {
      setSelectedHero(selectedHero?.id === hero?.id ? null : hero);
    },
    [selectedHero]
  );

  const refetch = useCallback(() => {
    return fetchHeroes();
  }, [fetchHeroes]);

  // Initial load
  useEffect(() => {
    fetchHeroes();
  }, [fetchHeroes]);

  // Auto-refresh effect
  useEffect(() => {
    if (!autoRefresh || refreshInterval <= 0) return;

    const interval = setInterval(() => {
      fetchHeroes();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, fetchHeroes]);

  return {
    heroes,
    isLoading,
    error,
    selectedHero,
    selectHero,
    refetch,
  };
};

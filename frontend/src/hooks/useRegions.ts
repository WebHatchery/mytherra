import { useState, useEffect, useCallback } from 'react';
import { Region } from '../entities/region';
import { Settlement } from '../entities/settlement';
import { Landmark } from '../entities/landmark';
import { getLandmarks, getRegions, getSettlements } from '../api/apiService';

interface UseRegionsOptions {
  autoRefresh?: boolean;
  refreshInterval?: number;
}

interface UseRegionsReturn {
  regions: Region[];
  settlements: Settlement[];
  landmarks: Landmark[];
  isLoading: boolean;
  error: string | null;
  selectedRegion: Region | null;
  selectRegion: (region: Region | null) => void;
  getSettlementsByRegion: (regionId: string) => Settlement[];
  getLandmarksByRegion: (regionId: string) => Landmark[];
  refetch: () => Promise<void>;
}

export const useRegions = (options: UseRegionsOptions = {}): UseRegionsReturn => {
  const { autoRefresh = false, refreshInterval = 30000 } = options;

  const [regions, setRegions] = useState<Region[]>([]);
  const [settlements, setSettlements] = useState<Settlement[]>([]);
  const [landmarks, setLandmarks] = useState<Landmark[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedRegion, setSelectedRegion] = useState<Region | null>(null);

  const fetchRegions = useCallback(async () => {
    try {
      setIsLoading(true);
      const [regionsData, settlementsData, landmarksData] = await Promise.all([
        getRegions(),
        getSettlements(),
        getLandmarks(),
      ]);
      setRegions(regionsData);
      setSettlements(settlementsData);
      setLandmarks(landmarksData);
      setError(null);
    } catch (err) {
      if (err instanceof Error) {
        setError(err.message);
      } else {
        setError('An unknown error occurred while fetching regions.');
      }
      console.error('Failed to load regions:', err);
    } finally {
      setIsLoading(false);
    }
  }, []);

  const selectRegion = useCallback(
    (region: Region | null) => {
      setSelectedRegion(selectedRegion?.id === region?.id ? null : region);
    },
    [selectedRegion]
  );

  const refetch = useCallback(() => {
    return fetchRegions();
  }, [fetchRegions]);

  const getSettlementsByRegion = useCallback(
    (regionId: string): Settlement[] => settlements.filter(settlement => settlement.regionId === regionId),
    [settlements]
  );

  const getLandmarksByRegion = useCallback(
    (regionId: string): Landmark[] => landmarks.filter(landmark => landmark.regionId === regionId),
    [landmarks]
  );

  // Initial load
  useEffect(() => {
    fetchRegions();
  }, [fetchRegions]);

  // Auto-refresh effect
  useEffect(() => {
    if (!autoRefresh || refreshInterval <= 0) return;

    const interval = setInterval(() => {
      fetchRegions();
    }, refreshInterval);

    return () => clearInterval(interval);
  }, [autoRefresh, refreshInterval, fetchRegions]);

  return {
    regions,
    settlements,
    landmarks,
    isLoading,
    error,
    selectedRegion,
    selectRegion,
    getSettlementsByRegion,
    getLandmarksByRegion,
    refetch,
  };
};

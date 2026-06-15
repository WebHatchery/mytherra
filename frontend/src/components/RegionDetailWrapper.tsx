// F:\WebDevelopment\Mytherra\frontend\src\components\RegionDetailWrapper.tsx
import React, { useState, useEffect } from 'react';
import { Region } from '../entities/region';
import { Hero } from '../entities/hero';
import { ResourceNode } from '../entities/resourceNode';
import { Settlement } from '../entities/settlement';
import { Landmark } from '../entities/landmark';
import { getEntityHistorySummary, getHeroes, getResourceNodes } from '../api/apiService';
import type { EntityHistorySummaryResponse } from '../api/apiService';
import RegionDetailPanel from './RegionDetailPanel';

interface RegionDetailWrapperProps {
  region: Region;
  settlements: Settlement[];
  landmarks: Landmark[];
}

const RegionDetailWrapper: React.FC<RegionDetailWrapperProps> = ({
  region,
  settlements,
  landmarks,
}) => {
  const [heroes, setHeroes] = useState<Hero[]>([]);
  const [resources, setResources] = useState<ResourceNode[]>([]);
  const [historySummary, setHistorySummary] = useState<EntityHistorySummaryResponse | null>(null);
  const [loading, setLoading] = useState(true);
  const [historyLoading, setHistoryLoading] = useState(true);

  // Fetch region-scoped entities that are not already available in the region context.
  useEffect(() => {
    const fetchRegionEntities = async () => {
      try {
        setLoading(true);
        setHistoryLoading(true);
        const [allHeroes, allResources, history] = await Promise.all([
          getHeroes(),
          getResourceNodes(),
          getEntityHistorySummary({ limit: 12, eventsPerEntity: 4, regionId: region.id }),
        ]);
        const regionHeroes = allHeroes.filter(hero => hero.regionId === region.id);
        const regionResources = allResources.filter(resource => resource.regionId === region.id);
        setHeroes(regionHeroes);
        setResources(regionResources);
        setHistorySummary(history);
      } catch (error) {
        console.error('Error fetching region entities:', error);
        setHeroes([]);
        setResources([]);
        setHistorySummary(null);
      } finally {
        setLoading(false);
        setHistoryLoading(false);
      }
    };

    fetchRegionEntities();
  }, [region.id]);

  return (
    <RegionDetailPanel
      region={region}
      settlements={settlements}
      landmarks={landmarks}
      heroes={heroes}
      resources={resources}
      loading={loading}
      historySummary={historySummary}
      historyLoading={historyLoading}
    />
  );
};

export default RegionDetailWrapper;

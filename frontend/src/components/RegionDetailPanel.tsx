import React, { useState } from 'react';
import { Region } from '../entities/region';
import { Settlement } from '../entities/settlement';
import { Landmark } from '../entities/landmark';
import { Hero } from '../entities/hero';
import { ResourceNode } from '../entities/resourceNode';
import type { EntityHistorySummaryResponse } from '../api/apiService';
import RegionTabNav, { RegionTabType } from './RegionTabs/RegionTabNav';
import RegionHeader from './RegionTabs/RegionHeader';
import RegionOverviewTab from './RegionTabs/RegionOverviewTab';
import RegionSettlementsTab from './RegionTabs/RegionSettlementsTab';
import RegionLandmarksTab from './RegionTabs/RegionLandmarksTab';
import RegionHeroesList from './RegionTabs/RegionHeroesList';
import RegionResourcesTab from './RegionTabs/RegionResourcesTab';
import RegionHistoryTab from './RegionTabs/RegionHistoryTab';

interface RegionDetailPanelProps {
  region: Region;
  settlements: Settlement[];
  landmarks: Landmark[];
  heroes: Hero[];
  resources: ResourceNode[];
  historySummary?: EntityHistorySummaryResponse | null;
  loading?: boolean;
  historyLoading?: boolean;
  onSelectSettlement?: (settlement: Settlement) => void;
  onSelectLandmark?: (landmark: Landmark) => void;
  onSelectHero?: (hero: Hero) => void;
}

const RegionDetailPanel: React.FC<RegionDetailPanelProps> = ({
  region,
  settlements,
  landmarks,
  heroes,
  resources,
  historySummary,
  loading = false,
  historyLoading = false,
  onSelectSettlement,
  onSelectLandmark,
  onSelectHero,
}) => {
  const [activeTab, setActiveTab] = useState<RegionTabType>('overview');

  // Helper functions for regional data
  const getStatusColor = (status: string) => {
    switch (status) {
      case 'peaceful':
        return 'text-green-400';
      case 'corrupt':
        return 'text-yellow-400';
      case 'warring':
        return 'text-red-400';
      case 'abandoned':
        return 'text-gray-400';
      default:
        return 'text-gray-300';
    }
  };

  const getSettlementSummary = () => {
    return settlements.reduce(
      (counts, settlement) => {
        counts[settlement.type] = (counts[settlement.type] ?? 0) + 1;
        return counts;
      },
      {} as Partial<Record<Settlement['type'], number>>
    );
  };

  const getTotalPopulation = () => {
    return settlements.reduce((sum, settlement) => sum + settlement.population, 0);
  };

  const settlementCounts = getSettlementSummary();
  const totalPopulation = getTotalPopulation();
  const livingHeroesCount = heroes.filter(h => h.isAlive !== false).length;

  return (
    <div className="p-4 bg-gray-800 text-white rounded-lg shadow-xl">
      {/* Region Header */}
      <RegionHeader region={region} getStatusColor={getStatusColor} />

      {/* Tab Navigation */}
      <RegionTabNav
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        settlementsCount={settlements.length}
        landmarksCount={landmarks.length}
        resourcesCount={resources.length}
        heroesCount={livingHeroesCount}
        historyCount={
          historySummary
            ? [
                ...historySummary.entities.regions,
                ...historySummary.entities.settlements,
                ...historySummary.entities.landmarks,
                ...historySummary.entities.resources,
                ...historySummary.entities.heroes,
              ].filter(item => item.regionId === region.id || item.id === region.id).length
            : 0
        }
        loading={loading}
        historyLoading={historyLoading}
      />

      {/* Tab Content */}
      {activeTab === 'overview' && (
        <RegionOverviewTab
          region={region}
          settlementsCount={settlements.length}
          landmarksCount={landmarks.length}
          resourcesCount={resources.length}
          heroesCount={heroes.length}
          totalPopulation={totalPopulation}
          loading={loading}
          livingHeroesCount={livingHeroesCount}
        />
      )}

      {activeTab === 'settlements' && (
        <RegionSettlementsTab
          settlements={settlements}
          onSelectSettlement={onSelectSettlement}
          settlementCounts={settlementCounts}
          totalPopulation={totalPopulation}
        />
      )}

      {activeTab === 'landmarks' && (
        <RegionLandmarksTab landmarks={landmarks} onSelectLandmark={onSelectLandmark} />
      )}

      {activeTab === 'resources' && (
        <RegionResourcesTab resources={resources} settlements={settlements} loading={loading} />
      )}

      {activeTab === 'heroes' && (
        <RegionHeroesList heroes={heroes} loading={loading} onSelectHero={onSelectHero} />
      )}

      {activeTab === 'history' && (
        <RegionHistoryTab region={region} summary={historySummary ?? null} loading={historyLoading} />
      )}
    </div>
  );
};

export default RegionDetailPanel;

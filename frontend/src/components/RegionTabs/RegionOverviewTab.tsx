import React from 'react';
import { Region } from '../../entities/region';
import RegionCoreStats from './RegionCoreStats';
import RegionCharacteristics from './RegionCharacteristics';
import RegionOverviewStats from './RegionOverviewStats';
import RegionInfluenceCosts from './RegionInfluenceCosts';

interface RegionOverviewTabProps {
  region: Region;
  settlementsCount: number;
  landmarksCount: number;
  resourcesCount: number;
  heroesCount: number;
  totalPopulation: number;
  loading: boolean;
  livingHeroesCount: number;
}

const RegionOverviewTab: React.FC<RegionOverviewTabProps> = ({
  region,
  settlementsCount,
  landmarksCount,
  resourcesCount,
  heroesCount,
  totalPopulation,
  loading,
  livingHeroesCount,
}) => {
  return (
    <div>
      {/* Core Stats */}
      <RegionCoreStats region={region} />

      {/* Enhanced Information */}
      <RegionCharacteristics region={region} totalPopulation={totalPopulation} />

      {/* Quick Overview Stats */}
      <RegionOverviewStats
        settlementsCount={settlementsCount}
        landmarksCount={landmarksCount}
        resourcesCount={resourcesCount}
        heroesCount={heroesCount}
        totalPopulation={totalPopulation}
        loading={loading}
        livingHeroesCount={livingHeroesCount}
      />

      {/* Influence Actions */}
      <RegionInfluenceCosts region={region} />
    </div>
  );
};

export default RegionOverviewTab;

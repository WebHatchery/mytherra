import React from 'react';

interface RegionOverviewStatsProps {
  settlementsCount: number;
  landmarksCount: number;
  resourcesCount: number;
  heroesCount: number;
  totalPopulation: number;
  loading: boolean;
  livingHeroesCount: number;
}

const RegionOverviewStats: React.FC<RegionOverviewStatsProps> = ({
  settlementsCount,
  landmarksCount,
  resourcesCount,
  heroesCount,
  totalPopulation,
  loading,
  livingHeroesCount,
}) => {
  return (
    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
      <div className="p-3 bg-gray-700 rounded">
        <h4 className="text-sm font-medium text-gray-400 mb-2">Settlements</h4>
        <div className="text-2xl font-bold text-blue-400">{settlementsCount}</div>
        <div className="text-xs text-gray-400">
          Total Population: {totalPopulation.toLocaleString()}
        </div>
      </div>
      <div className="p-3 bg-gray-700 rounded">
        <h4 className="text-sm font-medium text-gray-400 mb-2">Landmarks</h4>
        <div className="text-2xl font-bold text-purple-400">{landmarksCount}</div>
      </div>
      <div className="p-3 bg-gray-700 rounded">
        <h4 className="text-sm font-medium text-gray-400 mb-2">Resources</h4>
        <div className="text-2xl font-bold text-amber-300">{resourcesCount}</div>
      </div>
      <div className="p-3 bg-gray-700 rounded">
        <h4 className="text-sm font-medium text-gray-400 mb-2">Heroes</h4>
        <div className="text-2xl font-bold text-yellow-400">{loading ? '...' : heroesCount}</div>
        {!loading && heroesCount > 0 && heroesCount !== livingHeroesCount && (
          <div className="text-xs text-gray-400">{livingHeroesCount} living</div>
        )}
      </div>
    </div>
  );
};

export default RegionOverviewStats;

import React from 'react';
import { Region } from '../entities/region';
import { Settlement } from '../entities/settlement';
import { Landmark } from '../entities/landmark';
import RegionCard from './RegionCard';

interface RegionGridProps {
  regions: Region[];
  settlements: Settlement[];
  landmarks: Landmark[];
  selectedRegion: Region | null;
  onSelectRegion: (region: Region | null) => void;
}

const RegionGrid: React.FC<RegionGridProps> = ({
  regions,
  settlements,
  landmarks,
  selectedRegion,
  onSelectRegion,
}) => {
  if (regions.length === 0) {
    return <div className="text-center p-4">No regions to display.</div>;
  }

  const settlementCountsByRegion = settlements.reduce(
    (counts, settlement) => {
      counts[settlement.regionId] = (counts[settlement.regionId] ?? 0) + 1;
      return counts;
    },
    {} as Record<string, number>
  );
  const landmarkCountsByRegion = landmarks.reduce(
    (counts, landmark) => {
      counts[landmark.regionId] = (counts[landmark.regionId] ?? 0) + 1;
      return counts;
    },
    {} as Record<string, number>
  );

  return (
    <div className="p-4 bg-gray-800 text-white rounded-lg shadow-xl">
      <h2 className="text-2xl font-bold mb-4 text-center">World Map (Click a region to select)</h2>
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        {regions.map(region => (
          <RegionCard
            key={region.id}
            region={region}
            isSelected={selectedRegion?.id === region.id}
            onSelect={() => onSelectRegion(region)}
            settlementCount={settlementCountsByRegion[region.id] ?? 0}
            landmarkCount={landmarkCountsByRegion[region.id] ?? 0}
          />
        ))}
      </div>
    </div>
  );
};

export default RegionGrid;

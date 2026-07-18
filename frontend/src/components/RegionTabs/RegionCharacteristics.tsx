import React from 'react';
import { Region } from '../../entities/region';

interface RegionCharacteristicsProps {
  region: Region;
  totalPopulation: number;
}

const RegionCharacteristics: React.FC<RegionCharacteristicsProps> = ({
  region,
  totalPopulation,
}) => {
  if (!(region.regionalTraits || region.climateType || region.culturalInfluence)) {
    return null;
  }

  return (
    <div className="mb-4 p-3 bg-gray-700 rounded">
      <h3 className="text-lg font-semibold mb-2">Regional Characteristics</h3>
      <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
        {region.climateType && (
          <div>
            <span className="text-gray-400">Climate:</span>
            <span className="ml-2 capitalize">{region.climateType}</span>
          </div>
        )}
        {region.culturalInfluence && (
          <div>
            <span className="text-gray-400">Culture:</span>
            <span className="ml-2 capitalize">{region.culturalInfluence}</span>
          </div>
        )}
        {region.tradeRoutes && region.tradeRoutes.length > 0 && (
          <div>
            <span className="text-gray-400">Trade Routes:</span>
            <span className="ml-2 text-cyan-300">
              {region.tradeRoutes.length} connected region
              {region.tradeRoutes.length === 1 ? '' : 's'}
            </span>
          </div>
        )}
        {region.dangerLevel !== undefined && (
          <div>
            <span className="text-gray-400">Danger Level:</span>
            <span className="ml-2 text-orange-400">{region.dangerLevel}%</span>
          </div>
        )}
        {totalPopulation > 0 && (
          <div>
            <span className="text-gray-400">Total Population:</span>
            <span className="ml-2 text-blue-400">{totalPopulation.toLocaleString()}</span>
          </div>
        )}
      </div>

      <RegionalTraits traits={region.regionalTraits} />
      <RegionTags tags={region.tags} />
    </div>
  );
};

interface RegionalTraitsProps {
  traits?: string[];
}

const RegionalTraits: React.FC<RegionalTraitsProps> = ({ traits }) => {
  if (!traits || traits.length === 0) return null;

  return (
    <div className="mt-2">
      <div className="text-gray-400 text-sm mb-1">Regional Traits:</div>
      <div className="flex flex-wrap gap-1">
        {traits.map(trait => (
          <span key={trait} className="px-2 py-1 bg-green-600 text-xs rounded-full">
            {trait}
          </span>
        ))}
      </div>
    </div>
  );
};

interface RegionTagsProps {
  tags?: string[];
}

const RegionTags: React.FC<RegionTagsProps> = ({ tags }) => {
  if (!tags || tags.length === 0) return null;

  return (
    <div className="mt-2">
      <div className="text-gray-400 text-sm mb-1">Tags:</div>
      <div className="flex flex-wrap gap-1">
        {tags.map(tag => (
          <span key={tag} className="px-2 py-1 bg-gray-600 text-xs rounded-full">
            {tag}
          </span>
        ))}
      </div>
    </div>
  );
};

export default RegionCharacteristics;

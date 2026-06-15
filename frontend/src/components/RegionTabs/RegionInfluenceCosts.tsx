import React from 'react';
import { Region } from '../../entities/region';

interface RegionInfluenceCostsProps {
  region: Region;
}

const RegionInfluenceCosts: React.FC<RegionInfluenceCostsProps> = ({ region }) => {
  if (!region.influenceActionCosts && !region.influenceEffectiveness) return null;
  const resonance = region.influenceEffectiveness;

  return (
    <div className="p-3 bg-gray-700 rounded">
      <h3 className="text-lg font-semibold mb-2">Divine Influence Costs</h3>
      {resonance && (
        <div className="mb-3 rounded bg-gray-800/70 p-2 text-sm">
          <div className="flex justify-between gap-3">
            <span className="text-gray-300">Resonance</span>
            <span className="text-purple-300">
              {resonance.label} ({resonance.divineResonance}%)
            </span>
          </div>
          <div className="mt-1 text-xs text-gray-400">{resonance.summary}</div>
        </div>
      )}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-sm">
        {region.influenceActionCosts?.blessRegion && (
          <div>
            <div className="flex justify-between">
              <span>Bless Region:</span>
              <span className="text-yellow-400">{region.influenceActionCosts.blessRegion}</span>
            </div>
            {resonance?.actions.blessRegion?.summary && (
              <div className="text-xs text-gray-400">{resonance.actions.blessRegion.summary}</div>
            )}
          </div>
        )}
        {region.influenceActionCosts?.corruptRegion && (
          <div>
            <div className="flex justify-between">
              <span>Corrupt Region:</span>
              <span className="text-yellow-400">{region.influenceActionCosts.corruptRegion}</span>
            </div>
            {resonance?.actions.corruptRegion?.summary && (
              <div className="text-xs text-gray-400">{resonance.actions.corruptRegion.summary}</div>
            )}
          </div>
        )}
        {region.influenceActionCosts?.guideResearch && (
          <div>
            <div className="flex justify-between">
              <span>Guide Research:</span>
              <span className="text-yellow-400">{region.influenceActionCosts.guideResearch}</span>
            </div>
            {resonance?.actions.guideResearch?.summary && (
              <div className="text-xs text-gray-400">{resonance.actions.guideResearch.summary}</div>
            )}
          </div>
        )}
      </div>
    </div>
  );
};

export default RegionInfluenceCosts;

import React from 'react';
import { Region } from '../entities/region';
import BaseInfluencePanel from './BaseInfluencePanel';
import { useInfluenceActions } from '../hooks/useInfluenceActions';

interface RegionInfluencePanelProps {
  selectedRegion: Region | null;
  currentDivineFavor: number;
  onActionSuccess: () => void;
}

const RegionInfluencePanel: React.FC<RegionInfluencePanelProps> = ({
  selectedRegion,
  currentDivineFavor,
  onActionSuccess,
}) => {
  const { handleInfluenceAction, getButtonClass, isLoadingAction, actionMessage } =
    useInfluenceActions(currentDivineFavor, onActionSuccess);

  return (
    <BaseInfluencePanel title="Regional Divine Influence" actionMessage={actionMessage}>
      {selectedRegion ? (
        <div className="text-center">
          <p className="mb-3 text-lg">
            Targeting Region:{' '}
            <span className="font-semibold text-yellow-400">{selectedRegion.name}</span>
          </p>
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            <button
              onClick={() =>
                handleInfluenceAction(
                  'Bless Region',
                  selectedRegion.id,
                  selectedRegion.name,
                  'region'
                )
              }
              disabled={
                (selectedRegion.influenceActionCosts?.blessRegion !== undefined &&
                  currentDivineFavor < selectedRegion.influenceActionCosts.blessRegion) ||
                isLoadingAction[`Bless Region-${selectedRegion.id}`]
              }
              className={getButtonClass(
                'bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base',
                selectedRegion.influenceActionCosts?.blessRegion,
                `Bless Region-${selectedRegion.id}`
              )}
            >
              Bless {selectedRegion.name}
              {selectedRegion.influenceActionCosts?.blessRegion !== undefined && (
                <span className="text-xs">
                  {' '}
                  (Cost: {selectedRegion.influenceActionCosts.blessRegion})
                </span>
              )}
            </button>

            <button
              onClick={() =>
                handleInfluenceAction(
                  'Corrupt Region',
                  selectedRegion.id,
                  selectedRegion.name,
                  'region'
                )
              }
              disabled={
                (selectedRegion.influenceActionCosts?.corruptRegion !== undefined &&
                  currentDivineFavor < selectedRegion.influenceActionCosts.corruptRegion) ||
                isLoadingAction[`Corrupt Region-${selectedRegion.id}`]
              }
              className={getButtonClass(
                'bg-purple-500 hover:bg-purple-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base',
                selectedRegion.influenceActionCosts?.corruptRegion,
                `Corrupt Region-${selectedRegion.id}`
              )}
            >
              Corrupt {selectedRegion.name}
              {selectedRegion.influenceActionCosts?.corruptRegion !== undefined && (
                <span className="text-xs">
                  {' '}
                  (Cost: {selectedRegion.influenceActionCosts.corruptRegion})
                </span>
              )}
            </button>

            <button
              onClick={() =>
                handleInfluenceAction(
                  'Guide Research in Region',
                  selectedRegion.id,
                  selectedRegion.name,
                  'region'
                )
              }
              disabled={
                (selectedRegion.influenceActionCosts?.guideResearch !== undefined &&
                  currentDivineFavor < selectedRegion.influenceActionCosts.guideResearch) ||
                isLoadingAction[`Guide Research in Region-${selectedRegion.id}`]
              }
              className={getButtonClass(
                'bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 col-span-1 sm:col-span-2 md:col-span-1 text-sm sm:text-base',
                selectedRegion.influenceActionCosts?.guideResearch,
                `Guide Research in Region-${selectedRegion.id}`
              )}
            >
              Guide Research {selectedRegion.name}
              {selectedRegion.influenceActionCosts?.guideResearch !== undefined && (
                <span className="text-xs">
                  {' '}
                  (Cost: {selectedRegion.influenceActionCosts.guideResearch})
                </span>
              )}
            </button>
          </div>
        </div>
      ) : (
        <p className="text-center text-gray-400 py-8">
          Select a region to exert your divine influence upon it.
        </p>
      )}
    </BaseInfluencePanel>
  );
};

export default RegionInfluencePanel;

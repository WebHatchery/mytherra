import React from 'react';
import { Region, RegionInfluenceActionKey } from '../entities/region';
import BaseInfluencePanel from './BaseInfluencePanel';
import { useInfluenceActions } from '../hooks/useInfluenceActions';

interface RegionInfluencePanelProps {
  selectedRegion: Region | null;
  currentDivineFavor: number;
  onActionSuccess: () => void;
}

interface RegionActionConfig {
  action: string;
  actionKey: RegionInfluenceActionKey;
  buttonClass: string;
  label: (regionName: string) => string;
}

const regionActionConfigs: RegionActionConfig[] = [
  {
    action: 'Bless Region',
    actionKey: 'blessRegion',
    buttonClass:
      'bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base',
    label: regionName => `Bless ${regionName}`,
  },
  {
    action: 'Corrupt Region',
    actionKey: 'corruptRegion',
    buttonClass:
      'bg-purple-500 hover:bg-purple-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base',
    label: regionName => `Corrupt ${regionName}`,
  },
  {
    action: 'Guide Research in Region',
    actionKey: 'guideResearch',
    buttonClass:
      'bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 col-span-1 sm:col-span-2 md:col-span-1 text-sm sm:text-base',
    label: regionName => `Guide Research ${regionName}`,
  },
];

const RegionInfluencePanel: React.FC<RegionInfluencePanelProps> = ({
  selectedRegion,
  currentDivineFavor,
  onActionSuccess,
}) => {
  const { handleInfluenceAction, getButtonClass, isLoadingAction, actionMessage } =
    useInfluenceActions(currentDivineFavor, onActionSuccess);
  const resonance = selectedRegion?.influenceEffectiveness;

  return (
    <BaseInfluencePanel title="Regional Divine Influence" actionMessage={actionMessage}>
      {selectedRegion ? (
        <div className="text-center">
          <p className="mb-3 text-lg">
            Targeting Region:{' '}
            <span className="font-semibold text-yellow-400">{selectedRegion.name}</span>
          </p>
          {resonance && (
            <div className="mb-4 rounded bg-gray-800/70 p-3 text-left">
              <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <span className="text-sm font-semibold text-purple-300">
                  Divine Resonance: {resonance.label} ({resonance.divineResonance}%)
                </span>
                <span className="text-xs text-gray-300">{resonance.summary}</span>
              </div>
            </div>
          )}
          <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
            {regionActionConfigs.map(config => {
              const cost = selectedRegion.influenceActionCosts?.[config.actionKey];
              const actionPreview = resonance?.actions[config.actionKey];
              const actionKey = `${config.action}-${selectedRegion.id}`;

              return (
                <button
                  key={config.actionKey}
                  onClick={() =>
                    handleInfluenceAction(
                      config.action,
                      selectedRegion.id,
                      selectedRegion.name,
                      'region'
                    )
                  }
                  disabled={
                    (cost !== undefined && currentDivineFavor < cost) || isLoadingAction[actionKey]
                  }
                  className={getButtonClass(config.buttonClass, cost, actionKey)}
                >
                  <span className="block break-words">{config.label(selectedRegion.name)}</span>
                  {cost !== undefined && (
                    <span className="mt-1 block text-xs font-normal">Cost: {cost}</span>
                  )}
                  {actionPreview?.summary && (
                    <span className="mt-1 block text-xs font-normal text-white/85">
                      Effect: {actionPreview.summary}
                    </span>
                  )}
                </button>
              );
            })}
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

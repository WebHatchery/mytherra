import React from 'react';
import { Hero } from '../entities/hero';
import BaseInfluencePanel from './BaseInfluencePanel';
import { useInfluenceActions } from '../hooks/useInfluenceActions';

interface HeroInfluencePanelProps {
  selectedHero: Hero | null;
  currentDivineFavor: number;
  onActionSuccess: () => void;
}

const HeroInfluencePanel: React.FC<HeroInfluencePanelProps> = ({
  selectedHero,
  currentDivineFavor,
  onActionSuccess,
}) => {
  const { handleInfluenceAction, getButtonClass, isLoadingAction, actionMessage } =
    useInfluenceActions(currentDivineFavor, onActionSuccess);

  return (
    <BaseInfluencePanel title="Heroic Divine Influence" actionMessage={actionMessage}>
      {selectedHero ? (
        <div className="text-center">
          <p className="mb-3 text-lg">
            Targeting Hero: <span className="font-semibold text-cyan-400">{selectedHero.name}</span>
          </p>

          {/* Show resurrection option for dead heroes */}
          {selectedHero.isAlive === false ? (
            <div className="text-center">
              <p className="text-red-500 mb-2 font-bold">This hero has fallen</p>
              <button
                onClick={() =>
                  handleInfluenceAction('Revive Hero', selectedHero.id, selectedHero.name, 'hero')
                }
                disabled={
                  (selectedHero.influenceActionCosts?.reviveHero !== undefined &&
                    currentDivineFavor < selectedHero.influenceActionCosts.reviveHero) ||
                  isLoadingAction[`Revive Hero-${selectedHero.id}`]
                }
                className={getButtonClass(
                  'bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base w-full max-w-md mx-auto',
                  selectedHero.influenceActionCosts?.reviveHero,
                  `Revive Hero-${selectedHero.id}`
                )}
              >
                Resurrect {selectedHero.name}
                {selectedHero.influenceActionCosts?.reviveHero !== undefined && (
                  <span className="text-xs">
                    {' '}
                    (Cost: {selectedHero.influenceActionCosts.reviveHero})
                  </span>
                )}
              </button>
            </div>
          ) : (
            /* Original buttons for living heroes */
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <button
                onClick={() =>
                  handleInfluenceAction('Guide Hero', selectedHero.id, selectedHero.name, 'hero')
                }
                disabled={
                  (selectedHero.influenceActionCosts?.guideHero !== undefined &&
                    currentDivineFavor < selectedHero.influenceActionCosts.guideHero) ||
                  isLoadingAction[`Guide Hero-${selectedHero.id}`]
                }
                className={getButtonClass(
                  'bg-sky-500 hover:bg-sky-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base',
                  selectedHero.influenceActionCosts?.guideHero,
                  `Guide Hero-${selectedHero.id}`
                )}
              >
                Guide {selectedHero.name}
                {selectedHero.influenceActionCosts?.guideHero !== undefined && (
                  <span className="text-xs">
                    {' '}
                    (Cost: {selectedHero.influenceActionCosts.guideHero})
                  </span>
                )}
              </button>

              <button
                onClick={() =>
                  handleInfluenceAction('Empower Hero', selectedHero.id, selectedHero.name, 'hero')
                }
                disabled={
                  (selectedHero.influenceActionCosts?.empowerHero !== undefined &&
                    currentDivineFavor < selectedHero.influenceActionCosts.empowerHero) ||
                  isLoadingAction[`Empower Hero-${selectedHero.id}`]
                }
                className={getButtonClass(
                  'bg-amber-500 hover:bg-amber-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 text-sm sm:text-base',
                  selectedHero.influenceActionCosts?.empowerHero,
                  `Empower Hero-${selectedHero.id}`
                )}
              >
                Empower {selectedHero.name}
                {selectedHero.influenceActionCosts?.empowerHero !== undefined && (
                  <span className="text-xs">
                    {' '}
                    (Cost: {selectedHero.influenceActionCosts.empowerHero})
                  </span>
                )}
              </button>

              <button
                onClick={() =>
                  handleInfluenceAction(
                    'Start Notable Event',
                    selectedHero.id,
                    selectedHero.name,
                    'hero'
                  )
                }
                disabled={
                  (selectedHero.influenceActionCosts?.forceNotableEvent !== undefined &&
                    currentDivineFavor < selectedHero.influenceActionCosts.forceNotableEvent) ||
                  isLoadingAction[`Trigger Random Event-${selectedHero.id}`]
                }
                className={getButtonClass(
                  'bg-emerald-500 hover:bg-emerald-600 text-white font-bold py-3 px-4 rounded transition-colors duration-150 col-span-1 sm:col-span-2 text-sm sm:text-base',
                  selectedHero.influenceActionCosts?.forceNotableEvent,
                  `Trigger Random Event-${selectedHero.id}`
                )}
              >
                Start Notable Event
                {selectedHero.influenceActionCosts?.forceNotableEvent !== undefined && (
                  <span className="text-xs">
                    {' '}
                    (Cost: {selectedHero.influenceActionCosts.forceNotableEvent})
                  </span>
                )}
              </button>
            </div>
          )}
        </div>
      ) : (
        <p className="text-center text-gray-400 py-8">
          Select a hero to exert your divine influence upon them.
        </p>
      )}
    </BaseInfluencePanel>
  );
};

export default HeroInfluencePanel;

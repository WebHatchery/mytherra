import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import {
  cultivateChampion,
  designateChampion,
  type ChampionActionResponse,
} from '../api/apiService';
import type { ChampionFocus, Hero } from '../entities/hero';
import BaseInfluencePanel from './BaseInfluencePanel';

interface HeroChampionPanelProps {
  selectedHero: Hero | null;
  currentDivineFavor: number;
  onActionSuccess: () => void;
}

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Champion action failed';
};

const getResponseMessage = (response: ChampionActionResponse): string => {
  if (response.success === false) {
    return `Failed: ${response.message || 'Champion action failed'}`;
  }

  return response.message || 'Champion action completed.';
};

const HeroChampionPanel: React.FC<HeroChampionPanelProps> = ({
  selectedHero,
  currentDivineFavor,
  onActionSuccess,
}) => {
  const [isLoadingAction, setIsLoadingAction] = useState<Record<string, boolean>>({});
  const [actionMessage, setActionMessage] = useState<string | null>(null);
  const championStatus = selectedHero?.championStatus;
  const champion = championStatus?.profile;
  const focusOptions = championStatus?.focusOptions ?? [];

  const withLoading = async (key: string, action: () => Promise<ChampionActionResponse>) => {
    setIsLoadingAction(prev => ({ ...prev, [key]: true }));
    setActionMessage(null);

    try {
      const response = await action();
      setActionMessage(getResponseMessage(response));
      if (response.success !== false) {
        onActionSuccess();
      }
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setIsLoadingAction(prev => ({ ...prev, [key]: false }));
    }
  };

  const handleDesignate = () => {
    if (!selectedHero) return;
    void withLoading(`designate-${selectedHero.id}`, () => designateChampion(selectedHero.id));
  };

  const handleCultivate = (focus: ChampionFocus) => {
    if (!selectedHero) return;
    void withLoading(`cultivate-${focus}-${selectedHero.id}`, () =>
      cultivateChampion(selectedHero.id, focus)
    );
  };

  const actionButtonClass = (isDisabled: boolean): string =>
    [
      'rounded bg-cyan-600 px-4 py-3 text-sm font-bold text-white transition-colors duration-150 hover:bg-cyan-500',
      isDisabled ? 'cursor-not-allowed opacity-50' : '',
    ]
      .filter(Boolean)
      .join(' ');

  return (
    <BaseInfluencePanel title="Mortal Champion Bond" actionMessage={actionMessage}>
      {selectedHero ? (
        <div className="space-y-4">
          <div className="text-center">
            <p className="text-lg">
              Candidate: <span className="font-semibold text-cyan-400">{selectedHero.name}</span>
            </p>
            {championStatus && (
              <p className="text-sm text-gray-400">
                Champions {championStatus.activeChampionCount}/{championStatus.rosterLimit}
              </p>
            )}
          </div>

          {champion ? (
            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-3 text-center text-sm sm:grid-cols-4">
                <div className="rounded bg-gray-700 px-3 py-2">
                  <p className="text-xs uppercase text-gray-400">Rank</p>
                  <p className="font-semibold text-yellow-300">{champion.rank}</p>
                </div>
                <div className="rounded bg-gray-700 px-3 py-2">
                  <p className="text-xs uppercase text-gray-400">Bond</p>
                  <p className="font-semibold text-cyan-300">{champion.bond}/100</p>
                </div>
                <div className="rounded bg-gray-700 px-3 py-2">
                  <p className="text-xs uppercase text-gray-400">Focus</p>
                  <p className="font-semibold text-emerald-300">{champion.focusLabel}</p>
                </div>
                <div className="rounded bg-gray-700 px-3 py-2">
                  <p className="text-xs uppercase text-gray-400">Quests</p>
                  <p className="font-semibold text-purple-300">{champion.questsCompleted}</p>
                </div>
              </div>

              {champion.currentQuest && (
                <div className="rounded bg-gray-700 px-4 py-3">
                  <p className="text-sm font-semibold text-gray-100">
                    {champion.currentQuest.title}
                  </p>
                  <p className="mt-1 text-sm text-gray-300">{champion.currentQuest.summary}</p>
                  <p className="mt-2 text-xs font-semibold text-cyan-300">
                    Progress: {Math.max(0, Math.min(100, champion.currentQuest.progress))}%
                  </p>
                </div>
              )}

              {champion.latestOutcome && (
                <div className="rounded bg-gray-700 px-4 py-3">
                  <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                      <p className="text-sm font-semibold text-gray-100">
                        Latest Outcome: {champion.latestOutcome.title}
                      </p>
                      <p className="mt-1 text-sm text-gray-300">
                        {champion.latestOutcome.summary}
                      </p>
                    </div>
                    {champion.latestOutcome.eventId && (
                      <Link
                        to={`/events/${champion.latestOutcome.eventId}`}
                        className="text-xs font-semibold text-blue-300 hover:text-blue-100"
                      >
                        Event
                      </Link>
                    )}
                  </div>
                  <p className="mt-2 text-xs text-gray-400">
                    {champion.latestOutcome.legacyReason ?? champion.latestOutcome.effectSummary}
                  </p>
                </div>
              )}

              {champion.outcomes.length > 1 && (
                <div className="space-y-2 rounded bg-gray-700 px-4 py-3">
                  <p className="text-xs font-semibold uppercase text-gray-400">
                    Recent Champion Outcomes
                  </p>
                  {champion.outcomes.slice(1, 4).map(outcome => (
                    <div key={outcome.id} className="text-xs text-gray-300">
                      <span className="font-semibold text-gray-100">{outcome.title}</span>
                      <span className="text-gray-500"> | Year {outcome.year}</span>
                    </div>
                  ))}
                </div>
              )}

              <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                {focusOptions.map(option => {
                  const cost = championStatus?.cultivationCosts[option.key] ?? 0;
                  const loadingKey = `cultivate-${option.key}-${selectedHero.id}`;
                  const isDisabled =
                    currentDivineFavor < cost || Boolean(isLoadingAction[loadingKey]);

                  return (
                    <button
                      key={option.key}
                      type="button"
                      onClick={() => handleCultivate(option.key)}
                      disabled={isDisabled}
                      className={actionButtonClass(isDisabled)}
                      title={option.summary}
                    >
                      Cultivate {option.label}
                      <span className="block text-xs font-normal">Cost: {cost}</span>
                    </button>
                  );
                })}
              </div>
            </div>
          ) : (
            <div className="space-y-3 text-center">
              <p className="text-sm text-gray-300">
                Designate a living hero as a champion to create a durable divine bond.
              </p>
              {championStatus?.eligibilityReason && (
                <p className="text-sm text-amber-300">{championStatus.eligibilityReason}</p>
              )}
              {(() => {
                const loadingKey = `designate-${selectedHero.id}`;
                const designationCost = championStatus?.designationCost ?? 25;
                const isDisabled =
                  !championStatus?.eligible ||
                  currentDivineFavor < designationCost ||
                  Boolean(isLoadingAction[loadingKey]);

                return (
                  <button
                    type="button"
                    onClick={handleDesignate}
                    disabled={isDisabled}
                    className={actionButtonClass(isDisabled)}
                  >
                    Designate Champion
                    <span className="block text-xs font-normal">Cost: {designationCost}</span>
                  </button>
                );
              })()}
            </div>
          )}
        </div>
      ) : (
        <p className="py-8 text-center text-gray-400">
          Select a hero to review champion eligibility and cultivation.
        </p>
      )}
    </BaseInfluencePanel>
  );
};

export default HeroChampionPanel;

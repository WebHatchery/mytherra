// F:\WebDevelopment\Mytherra\frontend\src\components\DivineBettingPanel.tsx
import React, { useState, useEffect } from 'react';
import {
  BetTargetState,
  DivineBet,
  DivineBetSummary,
  SpeculationEvent,
  BettingOdds,
  OddsFactor,
} from '../entities/divineBet';
import {
  getDivineBets,
  getDivineBetSummary,
  getSpeculationEvents,
  getBettingOdds,
  placeDivineBet,
  CreateDivineBetPayload,
} from '../api/apiService';
import { getConfidenceColor, getBetStatusColor } from '../utils/colorUtils';
import { formatDate } from '../utils/dateUtils';

interface DivineBettingPanelProps {
  currentDivineFavor: number;
  onBetPlaced?: () => void;
}

type BettingTabId = 'events' | 'bets' | 'odds';
type BetFilterId = 'all' | DivineBet['status'];

const betFilters: BetFilterId[] = ['all', 'active', 'won', 'lost', 'expired'];

const formatLabel = (value: string): string =>
  value
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const TargetStateBlock: React.FC<{ state?: BetTargetState | null }> = ({ state }) => {
  if (!state) {
    return null;
  }

  return (
    <div className="mt-2 p-2 bg-gray-800 rounded border border-gray-600">
      <div className="text-xs text-gray-400 mb-1">Current Target State</div>
      <div className="text-sm text-gray-200">{state.summary}</div>
      {state.signals.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-1">
          {state.signals.slice(0, 5).map(signal => (
            <span
              key={`${signal.label}-${signal.value}`}
              className="px-2 py-1 bg-gray-700 rounded text-xs"
            >
              <span className="text-gray-400">{signal.label}:</span>{' '}
              <span className="text-gray-200">{signal.value}</span>
            </span>
          ))}
        </div>
      )}
    </div>
  );
};

const OddsFactorsList: React.FC<{ factors?: OddsFactor[] }> = ({ factors }) => {
  if (!factors || factors.length === 0) {
    return null;
  }

  return (
    <div className="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
      {factors.slice(0, 6).map(factor => (
        <div key={`${factor.label}-${factor.value}`} className="text-xs bg-gray-800 rounded p-2">
          <div className="flex justify-between gap-2">
            <span className="text-gray-400">{factor.label}</span>
            <span className="text-yellow-300">{factor.value}</span>
          </div>
          {factor.effect && <div className="mt-1 text-gray-500">{factor.effect}</div>}
        </div>
      ))}
    </div>
  );
};

const PayoutProfileLine: React.FC<{
  payoutProfile?: DivineBet['payoutProfile'];
  fallbackPayout?: number;
}> = ({ payoutProfile, fallbackPayout }) => {
  if (!payoutProfile && fallbackPayout === undefined) {
    return null;
  }

  if (!payoutProfile) {
    return <div className="text-xs text-gray-400">Potential payout: {fallbackPayout}</div>;
  }

  return (
    <div className="text-xs text-gray-400">
      Payout: <span className="text-yellow-300">{payoutProfile.grossPayout}</span> (
      {payoutProfile.grossMultiplier}x gross, +{payoutProfile.netProfit} net) • Risk:{' '}
      <span className="text-gray-200">{formatLabel(payoutProfile.riskBand)}</span> • Implied:{' '}
      {payoutProfile.probabilityPercent}%
    </div>
  );
};

const BetSummaryPanel: React.FC<{ summary: DivineBetSummary | null }> = ({ summary }) => {
  if (!summary) {
    return null;
  }

  const winRate = summary.winRatePercent === null ? 'n/a' : `${summary.winRatePercent}%`;

  return (
    <div className="rounded border border-gray-600 bg-gray-700 p-3">
      <div className="mb-2 flex flex-col gap-1 md:flex-row md:items-center md:justify-between">
        <h3 className="text-sm font-semibold text-gray-100">Bet Portfolio</h3>
        <span className="text-xs text-gray-400">{summary.summary}</span>
      </div>
      <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
        <div className="rounded bg-gray-800 p-2">
          <div className="text-xs text-gray-400">Active Stake</div>
          <div className="text-lg font-semibold text-yellow-300">{summary.activeStake}</div>
        </div>
        <div className="rounded bg-gray-800 p-2">
          <div className="text-xs text-gray-400">Potential</div>
          <div className="text-lg font-semibold text-blue-300">
            {summary.activePotentialPayout}
          </div>
        </div>
        <div className="rounded bg-gray-800 p-2">
          <div className="text-xs text-gray-400">Win Rate</div>
          <div className="text-lg font-semibold text-green-300">{winRate}</div>
        </div>
        <div className="rounded bg-gray-800 p-2">
          <div className="text-xs text-gray-400">Resolved Net</div>
          <div
            className={`text-lg font-semibold ${
              summary.netResolvedFavor >= 0 ? 'text-green-300' : 'text-red-300'
            }`}
          >
            {summary.netResolvedFavor}
          </div>
        </div>
      </div>
      {summary.topBetTypes.length > 0 && (
        <div className="mt-2 flex flex-wrap gap-2 text-xs">
          {summary.topBetTypes.map(type => (
            <span key={type.betType} className="rounded bg-gray-800 px-2 py-1 text-gray-300">
              {formatLabel(type.betType)}: {type.total}
            </span>
          ))}
        </div>
      )}
    </div>
  );
};

const DivineBettingPanel: React.FC<DivineBettingPanelProps> = ({
  currentDivineFavor,
  onBetPlaced,
}) => {
  const [activeBets, setActiveBets] = useState<DivineBet[]>([]);
  const [betSummary, setBetSummary] = useState<DivineBetSummary | null>(null);
  const [speculationEvents, setSpeculationEvents] = useState<SpeculationEvent[]>([]);
  const [bettingOdds, setBettingOdds] = useState<BettingOdds[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedTab, setSelectedTab] = useState<BettingTabId>('events');
  const [betFilter, setBetFilter] = useState<BetFilterId>('all');
  const [isPlacingBet, setIsPlacingBet] = useState(false);

  useEffect(() => {
    fetchBettingData();
  }, []);

  const fetchBettingData = async () => {
    try {
      setIsLoading(true);
      const [bets, summary, events, odds] = await Promise.all([
        getDivineBets(),
        getDivineBetSummary(),
        getSpeculationEvents(),
        getBettingOdds(),
      ]);

      setActiveBets(bets);
      setBetSummary(summary);
      setSpeculationEvents(events);
      setBettingOdds(odds);
      setError(null);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to fetch betting data');
    } finally {
      setIsLoading(false);
    }
  };

  const handlePlaceBet = async (betData: CreateDivineBetPayload) => {
    try {
      setIsPlacingBet(true);
      await placeDivineBet(betData);
      await fetchBettingData(); // Refresh data
      onBetPlaced?.();
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Failed to place bet');
    } finally {
      setIsPlacingBet(false);
    }
  };
  // Utility functions have been moved to utils/colorUtils.ts

  if (isLoading) {
    return <div className="p-4 bg-gray-800 text-white rounded-lg">Loading betting data...</div>;
  }

  const filteredBets =
    betFilter === 'all' ? activeBets : activeBets.filter(bet => bet.status === betFilter);

  return (
    <div className="p-4 bg-gray-800 text-white rounded-lg shadow-xl">
      <div className="flex justify-between items-center mb-4">
        <h2 className="text-2xl font-bold">Divine Observatory</h2>
        <div className="text-sm">
          <span className="text-gray-400">Divine Favor:</span>
          <span className="text-yellow-400 font-bold ml-1">{currentDivineFavor}</span>
        </div>
      </div>

      {error && (
        <div className="mb-4 p-3 bg-red-900 border border-red-500 rounded text-red-200">
          {error}
        </div>
      )}

      {/* Tab Navigation */}
      <div className="flex space-x-1 mb-4 border-b border-gray-600">
        {(
          [
            {
              key: 'events',
              label: 'Speculation Events',
              count: speculationEvents.length,
            },
            {
              key: 'bets',
              label: 'My Bets',
              count: activeBets.length,
            },
            { key: 'odds', label: 'Betting Odds', count: bettingOdds.length },
          ] as const satisfies ReadonlyArray<{
            key: BettingTabId;
            label: string;
            count: number;
          }>
        ).map(tab => (
          <button
            key={tab.key}
            onClick={() => setSelectedTab(tab.key)}
            className={`px-4 py-2 text-sm font-medium rounded-t-lg transition-colors ${
              selectedTab === tab.key
                ? 'bg-blue-600 text-white border-b-2 border-blue-400'
                : 'text-gray-400 hover:text-white hover:bg-gray-700'
            }`}
          >
            {tab.label} ({tab.count})
          </button>
        ))}
      </div>

      {/* Tab Content */}
      <div className="max-h-96 overflow-y-auto">
        {selectedTab === 'events' && (
          <div className="space-y-3">
            {speculationEvents.length === 0 ? (
              <div className="text-center text-gray-400 py-8">
                No speculation events available at this time.
              </div>
            ) : (
              speculationEvents.map(event => (
                <div key={event.id} className="p-3 bg-gray-700 rounded">
                  <div className="flex justify-between items-start mb-2">
                    <h3 className="font-semibold text-lg">{event.title}</h3>
                    <span className="text-xs bg-blue-500 px-2 py-1 rounded-full">
                      {event.eventType}
                    </span>
                  </div>
                  <p className="text-gray-300 text-sm mb-3">{event.description}</p>
                  <TargetStateBlock state={event.targetState} />

                  <div className="grid grid-cols-2 gap-2 text-xs text-gray-400 mb-3">
                    <div>
                      Timeframe: {event.timeframe.minimum}-{event.timeframe.maximum} years
                    </div>
                    <div>Options: {event.bettingOptions.length} available</div>
                  </div>

                  {event.bettingOptions.map(option => (
                    <div key={option.id} className="p-2 bg-gray-600 rounded mb-2">
                      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                        <div className="flex-1">
                          <p className="text-sm">{option.description}</p>
                          <div className="text-xs text-gray-400">
                            Min stake: {option.minimumStake} • Odds: {option.currentOdds}:1
                          </div>
                          <PayoutProfileLine
                            payoutProfile={option.payoutProfile}
                            fallbackPayout={option.potentialPayout}
                          />
                        </div>
                        <button
                          onClick={() => {
                            const betData: CreateDivineBetPayload = {
                              betType: option.betType ?? event.eventType,
                              targetId: option.targetId || event.targetId || event.id,
                              description: option.description,
                              timeframe: option.timeframe ?? event.timeframe.maximum,
                              confidence: option.confidence ?? 'possible',
                              divineFavorStake: option.minimumStake,
                            };
                            handlePlaceBet(betData);
                          }}
                          disabled={isPlacingBet || currentDivineFavor < option.minimumStake}
                          className="px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-500 text-xs rounded transition-colors md:ml-2"
                        >
                          {isPlacingBet ? 'Placing...' : 'Bet'}
                        </button>
                      </div>
                      <TargetStateBlock state={option.targetState} />
                      <OddsFactorsList factors={option.oddsFactors} />
                    </div>
                  ))}
                </div>
              ))
            )}
          </div>
        )}

        {selectedTab === 'bets' && (
          <div className="space-y-3">
            <BetSummaryPanel summary={betSummary} />
            <div className="flex flex-wrap gap-2">
              {betFilters.map(filter => {
                const count =
                  filter === 'all'
                    ? activeBets.length
                    : activeBets.filter(bet => bet.status === filter).length;
                return (
                  <button
                    key={filter}
                    onClick={() => setBetFilter(filter)}
                    className={`px-3 py-1 rounded text-xs transition-colors ${
                      betFilter === filter
                        ? 'bg-blue-600 text-white'
                        : 'bg-gray-700 text-gray-300 hover:bg-gray-600'
                    }`}
                  >
                    {formatLabel(filter)} ({count})
                  </button>
                );
              })}
            </div>
            {filteredBets.length === 0 ? (
              <div className="text-center text-gray-400 py-8">
                {betFilter === 'all'
                  ? 'No bets found.'
                  : `No ${formatLabel(betFilter).toLowerCase()} bets found.`}
              </div>
            ) : (
              filteredBets.map(bet => (
                <div key={bet.id} className="p-3 bg-gray-700 rounded">
                  <div className="flex justify-between items-start mb-2">
                    <h3 className="font-semibold">{bet.description}</h3>
                    <span className={`text-sm font-medium ${getBetStatusColor(bet.status)}`}>
                      {bet.status}
                    </span>
                  </div>

                  <div className="grid grid-cols-2 gap-2 text-sm">
                    <div>
                      <span className="text-gray-400">Stake:</span> {bet.divineFavorStake}
                    </div>
                    <div>
                      <span className="text-gray-400">Potential:</span> {bet.potentialPayout}
                    </div>
                    <div>
                      <span className="text-gray-400">Odds:</span> {bet.currentOdds}:1
                    </div>
                    <div>
                      <span className="text-gray-400">Confidence:</span>
                      <span className={`ml-1 ${getConfidenceColor(bet.confidence)}`}>
                        {bet.confidence}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400">Timeframe:</span> {bet.timeframe} years
                    </div>
                  </div>
                  <div className="mt-2">
                    <PayoutProfileLine
                      payoutProfile={bet.payoutProfile}
                      fallbackPayout={bet.potentialPayout}
                    />
                  </div>

                  <div className="mt-2 text-xs text-gray-400">
                    Placed in year {bet.placedYear} • Type: {bet.betType}
                    {bet.resolvedYear && ` • Resolved in year ${bet.resolvedYear}`}
                  </div>

                  {bet.resolutionNotes && (
                    <div className="mt-2 p-2 bg-gray-600 rounded text-sm border border-gray-500">
                      <span className="text-gray-400">Resolution:</span> {bet.resolutionNotes}
                    </div>
                  )}
                </div>
              ))
            )}
          </div>
        )}

        {selectedTab === 'odds' && (
          <div className="space-y-3">
            {bettingOdds.length === 0 ? (
              <div className="text-center text-gray-400 py-8">No betting odds available.</div>
            ) : (
              bettingOdds.map(odds => (
                <div key={odds.eventId} className="p-3 bg-gray-700 rounded">
                  <h3 className="font-semibold mb-2">Event {odds.eventId}</h3>
                  <div className="space-y-1">
                    {Object.entries(odds.odds).map(([optionId, oddValue]) => (
                      <div key={optionId} className="flex justify-between text-sm">
                        <span className="text-gray-300">Option {optionId}</span>
                        <span className="text-yellow-400">{oddValue}:1</span>
                      </div>
                    ))}
                  </div>{' '}
                  <div className="text-xs text-gray-400 mt-2">
                    Last updated: {formatDate(odds.lastUpdated)}
                  </div>
                </div>
              ))
            )}
          </div>
        )}
      </div>

      <div className="mt-4 text-xs text-gray-400 text-center">
        Divine bets are resolved automatically as events unfold in the world.
      </div>
    </div>
  );
};

export default DivineBettingPanel;

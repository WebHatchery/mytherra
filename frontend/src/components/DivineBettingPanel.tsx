// F:\WebDevelopment\Mytherra\frontend\src\components\DivineBettingPanel.tsx
import React, { useState, useEffect } from 'react';
import { DivineBet, SpeculationEvent, BettingOdds } from '../entities/divineBet';
import {
  getDivineBets,
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

const DivineBettingPanel: React.FC<DivineBettingPanelProps> = ({
  currentDivineFavor,
  onBetPlaced,
}) => {
  const [activeBets, setActiveBets] = useState<DivineBet[]>([]);
  const [speculationEvents, setSpeculationEvents] = useState<SpeculationEvent[]>([]);
  const [bettingOdds, setBettingOdds] = useState<BettingOdds[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedTab, setSelectedTab] = useState<BettingTabId>('events');
  const [isPlacingBet, setIsPlacingBet] = useState(false);

  useEffect(() => {
    fetchBettingData();
  }, []);

  const fetchBettingData = async () => {
    try {
      setIsLoading(true);
      const [bets, events, odds] = await Promise.all([
        getDivineBets(),
        getSpeculationEvents(),
        getBettingOdds(),
      ]);

      setActiveBets(bets);
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
              count: activeBets.filter(bet => bet.status === 'active').length,
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

                  <div className="grid grid-cols-2 gap-2 text-xs text-gray-400 mb-3">
                    <div>
                      Timeframe: {event.timeframe.minimum}-{event.timeframe.maximum} years
                    </div>
                    <div>Options: {event.bettingOptions.length} available</div>
                  </div>

                  {event.bettingOptions.map(option => (
                    <div
                      key={option.id}
                      className="flex justify-between items-center p-2 bg-gray-600 rounded mb-2"
                    >
                      <div className="flex-1">
                        <p className="text-sm">{option.description}</p>
                        <div className="text-xs text-gray-400">
                          Min stake: {option.minimumStake} • Odds: {option.currentOdds}:1
                        </div>
                      </div>
                      <button
                        onClick={() => {
                          const betData: CreateDivineBetPayload = {
                            betType: event.eventType,
                            targetId: option.targetId || event.targetId || event.id,
                            description: option.description,
                            timeframe: event.timeframe.maximum,
                            confidence: 'possible',
                            divineFavorStake: option.minimumStake,
                          };
                          handlePlaceBet(betData);
                        }}
                        disabled={isPlacingBet || currentDivineFavor < option.minimumStake}
                        className="ml-2 px-3 py-1 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-500 text-xs rounded transition-colors"
                      >
                        {isPlacingBet ? 'Placing...' : 'Bet'}
                      </button>
                    </div>
                  ))}
                </div>
              ))
            )}
          </div>
        )}

        {selectedTab === 'bets' && (
          <div className="space-y-3">
            {activeBets.length === 0 ? (
              <div className="text-center text-gray-400 py-8">You have no active bets.</div>
            ) : (
              activeBets.map(bet => (
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
                      <span className="text-gray-400">Confidence:</span>
                      <span className={`ml-1 ${getConfidenceColor(bet.confidence)}`}>
                        {bet.confidence}
                      </span>
                    </div>
                    <div>
                      <span className="text-gray-400">Timeframe:</span> {bet.timeframe} years
                    </div>
                  </div>

                  <div className="mt-2 text-xs text-gray-400">
                    Placed in year {bet.placedYear} • Type: {bet.betType}
                  </div>

                  {bet.resolutionNotes && (
                    <div className="mt-2 p-2 bg-gray-600 rounded text-sm">
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

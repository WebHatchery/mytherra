import React from 'react';
import { GameStatus } from '../api/apiService';
import UserInfo from './UserInfo';
import { useAuth } from '../contexts/useAuth';

interface HeaderProps {
  gameStatus: GameStatus | null;
}

const Header: React.FC<HeaderProps> = ({ gameStatus }) => {
  const { user } = useAuth();
  const simulation = gameStatus?.simulation;
  const lastTick = simulation?.lastTickResult;
  const lastTickYear =
    lastTick?.previousYear !== undefined && lastTick?.currentYear !== undefined
      ? `Year ${lastTick.previousYear} to ${lastTick.currentYear}`
      : 'No completed tick';
  const lastTickTime = simulation?.lastTickAt ?? lastTick?.completedAt;
  const lastTickLabel = lastTickTime
    ? `${lastTickYear} • ${new Date(lastTickTime).toLocaleString()}`
    : lastTickYear;
  const queueLabel = simulation?.queue.available
    ? `${simulation.queue.jobs ?? 0} queued, ${simulation.queue.failedJobs ?? 0} failed`
    : 'Queue unavailable';

  return (
    <header className="bg-gray-800 border-b border-gray-700">
      <div className="container mx-auto px-4 py-6">
        <div className="flex items-center justify-between">
          <div className="text-center flex-1">
            <h1 className="text-4xl md:text-5xl font-bold text-yellow-400">Mytherra</h1>
            {gameStatus && (
              <div className="text-lg md:text-xl text-gray-300 mt-2">
                <span className="mr-6">Current Year: {gameStatus.currentYear}</span>
                {/* Show server divine favor or user's favor if available */}
                <span>Divine Favor: {user?.divine_favor || gameStatus.divineFavor}</span>
              </div>
            )}
            {simulation && (
              <div className="mx-auto mt-3 flex max-w-4xl flex-wrap justify-center gap-2 text-xs text-gray-300">
                <span
                  className={`rounded border px-2 py-1 ${
                    simulation.enabled
                      ? 'border-emerald-700 bg-emerald-950/40 text-emerald-200'
                      : 'border-amber-700 bg-amber-950/40 text-amber-200'
                  }`}
                >
                  Simulation {simulation.enabled ? 'enabled' : 'paused'}
                </span>
                <span className="rounded border border-gray-700 bg-gray-900/60 px-2 py-1">
                  Last Tick: {lastTickLabel}
                </span>
                <span
                  className={`rounded border px-2 py-1 ${
                    simulation.queue.available
                      ? 'border-sky-800 bg-sky-950/40 text-sky-200'
                      : 'border-gray-700 bg-gray-900/60 text-gray-400'
                  }`}
                >
                  {queueLabel}
                </span>
              </div>
            )}
          </div>

          <div className="flex items-center">
            <UserInfo />
          </div>
        </div>
      </div>
    </header>
  );
};

export default Header;

import React from 'react';
import { Link } from 'react-router-dom';
import type { CivilizationStatusResponse } from '../entities/civilization';

interface DashboardCivilizationPanelProps {
  civilization?: CivilizationStatusResponse | null;
}

const tierTone = (tier?: string): string => {
  if (tier === 'urgent') return 'text-red-200 border-red-500/60';
  if (tier === 'active') return 'text-amber-200 border-amber-500/60';
  if (tier === 'watch') return 'text-cyan-200 border-cyan-500/50';
  return 'text-gray-300 border-[#2f334d]';
};

const DashboardCivilizationPanel: React.FC<DashboardCivilizationPanelProps> = ({
  civilization,
}) => {
  if (!civilization) {
    return null;
  }

  const topAgenda = civilization.topAgenda;
  const latestDecision = civilization.recentDecisions[0] ?? null;

  return (
    <section className="mb-8 rounded-lg border border-[#2f334d] bg-[#1a1b26] p-6">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">Civilization</h2>
          <div className="mt-1 text-sm text-gray-400">{civilization.summary}</div>
        </div>
        <Link to="/civilization" className="text-sm font-semibold text-blue-300 hover:text-blue-100">
          Open Civilization
        </Link>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {topAgenda ? (
          <div className={`rounded border bg-[#16161e] p-4 ${tierTone(topAgenda.priorityTier)}`}>
            <div className="text-xs uppercase text-gray-500">Top Agenda</div>
            <div className="mt-1 flex items-center justify-between gap-3">
              <div className="text-sm font-semibold text-gray-100">{topAgenda.regionName}</div>
              <div className="text-lg font-bold">{topAgenda.score}</div>
            </div>
            <div className="mt-1 text-sm text-gray-300">{topAgenda.dominantBehaviorLabel}</div>
            <div className="mt-2 text-xs text-gray-500">{topAgenda.summary}</div>
          </div>
        ) : (
          <div className="rounded border border-[#2f334d] bg-[#16161e] p-4 text-sm text-gray-500">
            No top agenda recorded.
          </div>
        )}

        <div className="rounded border border-[#2f334d] bg-[#16161e] p-4 lg:col-span-2">
          <div className="text-xs uppercase text-gray-500">Behavior Spread</div>
          <div className="mt-3 grid grid-cols-2 gap-2 md:grid-cols-6">
            {Object.entries(civilization.behaviorCounts).map(([behavior, count]) => (
              <div key={behavior} className="rounded bg-[#10111a] px-2 py-2">
                <div className="text-[11px] capitalize text-gray-500">{behavior}</div>
                <div className="text-sm font-bold text-gray-200">{count}</div>
              </div>
            ))}
          </div>

          {latestDecision && (
            <div className="mt-4 rounded bg-[#10111a] px-3 py-2 text-xs">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-semibold text-gray-200">
                  {latestDecision.regionName}: {latestDecision.behaviorLabel}
                </span>
                <span className="text-gray-500">Year {latestDecision.year}</span>
              </div>
              <div className="mt-1 text-gray-400">{latestDecision.effectSummary}</div>
              {latestDecision.eventId && (
                <Link
                  to={`/events/${latestDecision.eventId}`}
                  className="mt-2 inline-block text-blue-300 hover:text-blue-100"
                >
                  Event
                </Link>
              )}
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

export default DashboardCivilizationPanel;

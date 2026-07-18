import React from 'react';
import { Link } from 'react-router-dom';
import type { PantheonStatusResponse } from '../entities/pantheon';

interface DashboardPantheonPanelProps {
  pantheon?: PantheonStatusResponse | null;
}

const tierTone = (tier?: string): string => {
  if (tier === 'dominant') return 'text-red-200 border-red-500/60';
  if (tier === 'active') return 'text-amber-200 border-amber-500/60';
  if (tier === 'watch') return 'text-cyan-200 border-cyan-500/50';
  return 'text-gray-300 border-[#2f334d]';
};

const DashboardPantheonPanel: React.FC<DashboardPantheonPanelProps> = ({ pantheon }) => {
  if (!pantheon) {
    return null;
  }

  const topActor = pantheon.topActor ?? pantheon.deities[0] ?? null;
  const latestIntervention = pantheon.recentInterventions[0] ?? null;
  const latestArc = pantheon.relationshipArcs?.[0] ?? null;

  return (
    <section className="mb-8 rounded-lg border border-[#2f334d] bg-[#1a1b26] p-6">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">AI Pantheon</h2>
          <div className="mt-1 text-sm text-gray-400">{pantheon.summary}</div>
        </div>
        <Link to="/pantheon" className="text-sm font-semibold text-blue-300 hover:text-blue-100">
          Open Pantheon
        </Link>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-3">
        {topActor ? (
          <div className={`rounded border bg-[#16161e] p-4 ${tierTone(topActor.pressureTier)}`}>
            <div className="text-xs uppercase text-gray-500">Top Divine Pressure</div>
            <div className="mt-1 flex items-center justify-between gap-3">
              <div className="text-sm font-semibold text-gray-100">{topActor.name}</div>
              <div className="text-lg font-bold">{topActor.pressureScore}</div>
            </div>
            <div className="mt-1 text-sm capitalize text-gray-300">{topActor.domain}</div>
            <div className="mt-2 text-xs text-gray-500">
              Target: {topActor.targetRegionName ?? 'No region'}
            </div>
          </div>
        ) : (
          <div className="rounded border border-[#2f334d] bg-[#16161e] p-4 text-sm text-gray-500">
            No pantheon pressure recorded.
          </div>
        )}

        <div className="rounded border border-[#2f334d] bg-[#16161e] p-4">
          <div className="text-xs uppercase text-gray-500">Recent Intervention</div>
          {latestIntervention ? (
            <div className="mt-3 text-xs">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-semibold text-gray-200">
                  {latestIntervention.deityName}: {latestIntervention.targetRegionName}
                </span>
                <span className="text-gray-500">Year {latestIntervention.year}</span>
              </div>
              <div className="mt-1 text-gray-400">{latestIntervention.summary}</div>
              {latestIntervention.eventId && (
                <Link
                  to={`/events/${latestIntervention.eventId}`}
                  className="mt-2 inline-block text-blue-300 hover:text-blue-100"
                >
                  Event
                </Link>
              )}
            </div>
          ) : (
            <div className="mt-3 text-xs text-gray-500">
              No autonomous pantheon intervention has resolved yet.
            </div>
          )}
        </div>

        <div className="rounded border border-[#2f334d] bg-[#16161e] p-4">
          <div className="text-xs uppercase text-gray-500">Latest Political Arc</div>
          {latestArc ? (
            <div className="mt-3 text-xs">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <span className="font-semibold text-gray-200">
                  {latestArc.sourceName} to {latestArc.targetName}
                </span>
                <span className="text-gray-500">Step {latestArc.stepCount}</span>
              </div>
              <div className="mt-1 text-gray-400">{latestArc.summary}</div>
              <div className="mt-2 flex flex-wrap items-center gap-3">
                {latestArc.eventId && (
                  <Link
                    to={`/events/${latestArc.eventId}`}
                    className="text-blue-300 hover:text-blue-100"
                  >
                    Event
                  </Link>
                )}
                <span className="text-gray-500">{latestArc.momentum}/100 momentum</span>
              </div>
            </div>
          ) : (
            <div className="mt-3 text-xs text-gray-500">
              No alliance or rivalry arc has advanced yet.
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

export default DashboardPantheonPanel;

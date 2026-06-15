import React from 'react';
import { Link } from 'react-router-dom';
import type { EraPressureSummary, EraPressureTrigger } from '../api/apiService';

interface DashboardEraPressurePanelProps {
  eraPressure?: EraPressureSummary | null;
}

const tierTone = (tier?: string): string => {
  switch (tier) {
    case 'breaking':
      return 'text-red-200 bg-red-950/40 border-red-600/60';
    case 'critical':
      return 'text-orange-200 bg-orange-950/40 border-orange-500/60';
    case 'dangerous':
      return 'text-amber-200 bg-amber-950/40 border-amber-500/60';
    case 'watchful':
      return 'text-cyan-200 bg-cyan-950/30 border-cyan-500/50';
    default:
      return 'text-emerald-200 bg-emerald-950/30 border-emerald-500/50';
  }
};

const triggerTone = (score: number): string => {
  if (score >= 70) {
    return 'text-orange-200 border-orange-500/50';
  }
  if (score >= 55) {
    return 'text-amber-200 border-amber-500/50';
  }
  if (score >= 35) {
    return 'text-cyan-200 border-cyan-500/40';
  }

  return 'text-gray-300 border-[#2f334d]';
};

const timelineLinkForTrigger = (trigger: EraPressureTrigger): string | null => {
  const regionId = trigger.relatedRegionIds?.[0];
  const heroId = trigger.relatedHeroIds?.[0];
  const settlementId = trigger.relatedSettlementIds?.[0];
  const landmarkId = trigger.relatedLandmarkIds?.[0];
  const resourceId = trigger.relatedResourceIds?.[0];

  if (regionId) return `/events?regionId=${encodeURIComponent(regionId)}`;
  if (heroId) return `/events?heroId=${encodeURIComponent(heroId)}`;
  if (settlementId) return `/events?settlementId=${encodeURIComponent(settlementId)}`;
  if (landmarkId) return `/events?landmarkId=${encodeURIComponent(landmarkId)}`;
  if (resourceId) return `/events?resourceId=${encodeURIComponent(resourceId)}`;

  return null;
};

const TriggerCard: React.FC<{ trigger: EraPressureTrigger }> = ({ trigger }) => {
  const timelineLink = timelineLinkForTrigger(trigger);

  return (
    <div className={`rounded border bg-[#16161e] p-3 ${triggerTone(trigger.score)}`}>
      <div className="flex items-start justify-between gap-3">
        <div>
          <h3 className="text-sm font-semibold text-gray-100">{trigger.label}</h3>
          <div className="mt-1 text-xs text-gray-400">{trigger.summary}</div>
        </div>
        <div className="text-lg font-bold">{trigger.score}</div>
      </div>
      <div className="mt-3 grid grid-cols-2 gap-2">
        {trigger.signals.slice(0, 4).map(signal => (
          <div key={`${trigger.code}-${signal.label}`} className="rounded bg-[#10111a] px-2 py-1">
            <div className="text-[11px] text-gray-500">{signal.label}</div>
            <div className="text-xs font-semibold text-gray-200">{signal.value}</div>
          </div>
        ))}
      </div>
      {timelineLink && (
        <Link to={timelineLink} className="mt-3 inline-block text-xs text-blue-300 hover:text-blue-100">
          Timeline
        </Link>
      )}
    </div>
  );
};

const DashboardEraPressurePanel: React.FC<DashboardEraPressurePanelProps> = ({ eraPressure }) => {
  if (!eraPressure) {
    return null;
  }

  const filledSegments = Math.max(0, Math.min(10, Math.ceil(eraPressure.pressureScore / 10)));

  return (
    <section className="mb-8 bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">Era Pressure</h2>
          <div className="mt-1 text-sm text-gray-400">
            Era {eraPressure.currentEra} • Year {eraPressure.currentYear} • Era year{' '}
            {eraPressure.eraYear} of {eraPressure.eraLengthYears}
          </div>
        </div>
        <div className={`min-w-48 rounded border px-4 py-3 ${tierTone(eraPressure.tier)}`}>
          <div className="text-xs uppercase text-gray-400">Pressure</div>
          <div className="text-2xl font-bold">{eraPressure.pressureScore}/100</div>
          <div className="text-sm capitalize">{eraPressure.tierLabel}</div>
        </div>
      </div>

      <div className="mt-5">
        <div className="grid grid-cols-10 gap-1" aria-hidden="true">
          {Array.from({ length: 10 }).map((_, index) => (
            <div
              key={`era-pressure-segment-${index}`}
              className={`h-2 rounded ${index < filledSegments ? 'bg-amber-400' : 'bg-[#10111a]'}`}
            />
          ))}
        </div>
        <p className="mt-3 text-sm text-gray-300">{eraPressure.summary}</p>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-3 xl:grid-cols-5">
        {eraPressure.triggers.slice(0, 5).map(trigger => (
          <TriggerCard key={trigger.code} trigger={trigger} />
        ))}
      </div>

      <div className="mt-5 grid grid-cols-1 gap-3 lg:grid-cols-2">
        {eraPressure.warnings.slice(0, 4).map(warning => (
          <div key={warning} className="rounded bg-[#16161e] p-3 text-xs text-gray-300">
            {warning}
          </div>
        ))}
        {eraPressure.eventId && (
          <Link
            to={`/events/${eraPressure.eventId}`}
            className="rounded bg-[#16161e] p-3 text-xs text-blue-300 hover:text-blue-100"
          >
            Event {eraPressure.eventId}
          </Link>
        )}
      </div>
    </section>
  );
};

export default DashboardEraPressurePanel;

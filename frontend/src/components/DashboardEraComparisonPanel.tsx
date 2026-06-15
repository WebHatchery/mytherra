import React from 'react';
import { Link } from 'react-router-dom';
import type {
  EraComparisonMetricDelta,
  EraComparisonSummary,
  EraComparisonWorldSnapshot,
} from '../api/apiService';

interface DashboardEraComparisonPanelProps {
  eraComparison?: EraComparisonSummary | null;
}

const formatNumber = (value: number): string =>
  Number.isInteger(value) ? value.toLocaleString() : value.toLocaleString(undefined, { maximumFractionDigits: 1 });

const formatDelta = (delta: EraComparisonMetricDelta): string => {
  if (delta.delta === 0) {
    return 'No change';
  }

  const prefix = delta.delta > 0 ? '+' : '-';
  return `${prefix}${formatNumber(Math.abs(delta.delta))} ${delta.unit}`;
};

const directionTone = (direction: EraComparisonMetricDelta['direction']): string => {
  if (direction === 'up') {
    return 'text-emerald-300';
  }
  if (direction === 'down') {
    return 'text-rose-300';
  }

  return 'text-gray-400';
};

const SnapshotMetric: React.FC<{ label: string; value: string | number; tone: string }> = ({
  label,
  value,
  tone,
}) => (
  <div>
    <div className="text-xs text-gray-500">{label}</div>
    <div className={`text-lg font-bold ${tone}`}>{typeof value === 'number' ? formatNumber(value) : value}</div>
  </div>
);

const DeltaRow: React.FC<{ delta: EraComparisonMetricDelta }> = ({ delta }) => (
  <div className="flex items-center justify-between gap-4 border-t border-[#2f334d] py-2 first:border-t-0 first:pt-0 last:pb-0">
    <div>
      <div className="text-sm font-medium text-gray-200">{delta.label}</div>
      <div className="text-xs text-gray-500">
        {formatNumber(delta.before)} to {formatNumber(delta.after)}
      </div>
    </div>
    <div className={`text-right text-sm font-semibold ${directionTone(delta.direction)}`}>
      {formatDelta(delta)}
    </div>
  </div>
);

const CurrentSnapshot: React.FC<{ snapshot: EraComparisonWorldSnapshot }> = ({ snapshot }) => (
  <div>
    <h3 className="mb-3 text-sm font-semibold text-gray-200">Current World Snapshot</h3>
    <div className="grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
      <SnapshotMetric label="Regions" value={snapshot.regions.count} tone="text-sky-300" />
      <SnapshotMetric label="Population" value={snapshot.settlements.population} tone="text-emerald-300" />
      <SnapshotMetric label="Living Heroes" value={snapshot.heroes.living} tone="text-violet-300" />
      <SnapshotMetric label="Avg Magic" value={snapshot.regions.avgMagic} tone="text-amber-300" />
      <SnapshotMetric label="Active Bets" value={snapshot.bets.active} tone="text-fuchsia-300" />
    </div>

    <div className="mt-4 grid grid-cols-1 gap-2 text-xs md:grid-cols-5">
      {snapshot.signals.map(signal => (
        <div key={`${signal.label}-${signal.value}`} className="border-t border-[#2f334d] pt-2">
          <span className="text-gray-500">{signal.label}: </span>
          <span className="text-gray-300">{signal.value}</span>
        </div>
      ))}
    </div>
  </div>
);

const DashboardEraComparisonPanel: React.FC<DashboardEraComparisonPanelProps> = ({
  eraComparison,
}) => {
  if (!eraComparison) {
    return null;
  }

  const latest = eraComparison.latestComparison;

  return (
    <section className="mb-8 rounded-lg border border-[#2f334d] bg-[#1a1b26] p-6">
      <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">Era Comparison</h2>
          <div className="mt-1 text-sm text-gray-400">
            Era {eraComparison.currentEra} - Year {eraComparison.currentYear} - {eraComparison.historyCount}{' '}
            completed comparison{eraComparison.historyCount === 1 ? '' : 's'}
          </div>
        </div>
        {latest?.eventId && (
          <Link to={`/events/${latest.eventId}`} className="text-sm text-blue-300 hover:text-blue-100">
            Latest Era Event
          </Link>
        )}
      </div>

      <p className="mt-4 text-sm text-gray-300">{eraComparison.summary}</p>

      <div className="mt-5">
        <CurrentSnapshot snapshot={eraComparison.currentSnapshot} />
      </div>

      <div className="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Latest Era Delta</h3>
          {latest ? (
            <>
              <div className="mb-3 text-xs text-gray-500">
                Era {latest.completedEra} to Era {latest.nextEra}, year {latest.previousYear} to{' '}
                {latest.currentYear}
              </div>
              <div className="text-xs text-gray-400">{latest.summary}</div>
              <div className="mt-3">
                {latest.transitionDelta.slice(0, 6).map(delta => (
                  <DeltaRow key={delta.key} delta={delta} />
                ))}
              </div>
            </>
          ) : (
            <div className="text-xs text-gray-500">
              No completed era comparison has been recorded.
            </div>
          )}
        </div>

        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Since Last Rollover</h3>
          {latest ? (
            <div>
              {latest.currentDelta.slice(0, 6).map(delta => (
                <DeltaRow key={delta.key} delta={delta} />
              ))}
            </div>
          ) : (
            <div className="text-xs text-gray-500">
              Current metrics will become the baseline after the next era begins.
            </div>
          )}
        </div>
      </div>
    </section>
  );
};

export default DashboardEraComparisonPanel;

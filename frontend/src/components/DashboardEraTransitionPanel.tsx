import React from 'react';
import { Link } from 'react-router-dom';
import type { EraTransitionHistoryEntry, EraTransitionSummary } from '../api/apiService';

interface DashboardEraTransitionPanelProps {
  eraTransition?: EraTransitionSummary | null;
}

const statusTone = (eligible?: boolean): string =>
  eligible
    ? 'text-amber-200 bg-amber-950/30 border-amber-500/60'
    : 'text-sky-200 bg-sky-950/30 border-sky-500/50';

const listText = (items: string[]): string => {
  if (items.length === 0) return 'None';
  return items.slice(0, 3).join(', ');
};

const generatedNames = (entry: EraTransitionHistoryEntry): string[] => [
  ...(entry.generated?.settlements ?? []).map(item => item.name),
  ...(entry.generated?.heroes ?? []).map(item => item.name),
  ...(entry.generated?.landmarks ?? []).map(item => item.name),
  ...(entry.generated?.resources ?? []).map(item => item.name),
];

const HistoryRow: React.FC<{ entry: EraTransitionHistoryEntry }> = ({ entry }) => {
  const generated = generatedNames(entry);

  return (
    <div className="border-t border-[#2f334d] py-3 first:border-t-0 first:pt-0 last:pb-0">
      <div className="flex items-start justify-between gap-3">
        <div>
          <div className="text-sm font-semibold text-gray-100">
            Era {entry.completedEra} to Era {entry.nextEra}
          </div>
          <div className="mt-1 text-xs text-gray-500">
            Year {entry.previousYear} to {entry.currentYear} - pressure {entry.pressureScore}/100 -
            continuity {entry.continuityScore}/100
          </div>
        </div>
        {entry.eventId && (
          <Link to={`/events/${entry.eventId}`} className="text-xs text-blue-300 hover:text-blue-100">
            Event
          </Link>
        )}
      </div>
      <div className="mt-2 text-xs text-gray-400">{entry.summary}</div>
      {generated.length > 0 && (
        <div className="mt-2 text-xs text-gray-500">
          New foundations: <span className="text-gray-300">{listText(generated)}</span>
        </div>
      )}
    </div>
  );
};

const DashboardEraTransitionPanel: React.FC<DashboardEraTransitionPanelProps> = ({
  eraTransition,
}) => {
  if (!eraTransition) {
    return null;
  }

  const preview = eraTransition.preview;
  const history = eraTransition.history ?? [];

  return (
    <section className="mb-8 bg-[#1a1b26] p-6 rounded-lg border border-[#2f334d]">
      <div className="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">Era Rollover</h2>
          <div className="mt-1 text-sm text-gray-400">
            Era {eraTransition.transitionEra} closes at year {eraTransition.eraEndYear}; Era{' '}
            {eraTransition.nextEra} begins year {eraTransition.nextEraYear}
          </div>
        </div>
        <div className={`min-w-48 rounded border px-4 py-3 ${statusTone(eraTransition.eligible)}`}>
          <div className="text-xs uppercase text-gray-400">Rollover</div>
          <div className="text-2xl font-bold">{eraTransition.eligible ? 'Ready' : 'Watching'}</div>
          <div className="text-sm">
            Last closed era {eraTransition.lastCompletedEra || 'none'}
          </div>
        </div>
      </div>

      <p className="mt-4 text-sm text-gray-300">{eraTransition.summary}</p>
      <div className="mt-2 text-xs text-gray-500">{eraTransition.triggerReason}</div>

      <div className="mt-5 grid grid-cols-2 gap-3 text-sm md:grid-cols-5">
        <div>
          <div className="text-gray-500">Regions</div>
          <div className="text-lg font-bold text-sky-300">{preview.counts.regions}</div>
        </div>
        <div>
          <div className="text-gray-500">Settlements</div>
          <div className="text-lg font-bold text-emerald-300">{preview.counts.settlements}</div>
        </div>
        <div>
          <div className="text-gray-500">Heroes</div>
          <div className="text-lg font-bold text-violet-300">{preview.counts.heroes}</div>
        </div>
        <div>
          <div className="text-gray-500">Myths</div>
          <div className="text-lg font-bold text-amber-300">{preview.counts.carriedMyths}</div>
        </div>
        <div>
          <div className="text-gray-500">Bets</div>
          <div className="text-lg font-bold text-fuchsia-300">{preview.counts.eraSpanningBets}</div>
        </div>
      </div>

      <div className="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Carry Forward</h3>
          <div className="space-y-2 text-xs">
            <div>
              <span className="text-gray-500">Heroes: </span>
              <span className="text-gray-300">{listText(preview.carryForward.heroes)}</span>
            </div>
            <div>
              <span className="text-gray-500">Landmarks: </span>
              <span className="text-gray-300">{listText(preview.carryForward.landmarks)}</span>
            </div>
            <div>
              <span className="text-gray-500">Scars: </span>
              <span className="text-gray-300">{listText(preview.carryForward.scars)}</span>
            </div>
            <div>
              <span className="text-gray-500">Myths: </span>
              <span className="text-gray-300">{listText(preview.carryForward.myths)}</span>
            </div>
          </div>
        </div>

        <div>
          <h3 className="mb-3 text-sm font-semibold text-gray-200">Era History</h3>
          {history.length > 0 ? (
            history.slice(0, 3).map(entry => <HistoryRow key={entry.id} entry={entry} />)
          ) : (
            <div className="text-xs text-gray-500">No completed era transition has been recorded.</div>
          )}
        </div>
      </div>
    </section>
  );
};

export default DashboardEraTransitionPanel;

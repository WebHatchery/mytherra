import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import PageLayout from '../components/PageLayout';
import {
  EraComparisonMetricDelta,
  EraGeneratedContent,
  EraGeneratedEntity,
  EraTransitionHistoryEntry,
  GameStatus,
  getGameStatus,
} from '../api/apiService';

type EraView = 'chronicle' | 'legacy' | 'pressure';

const formatNumber = (value: number): string =>
  Number.isInteger(value) ? value.toLocaleString() : value.toLocaleString(undefined, { maximumFractionDigits: 1 });

const deltaTone = (direction: EraComparisonMetricDelta['direction']): string => {
  if (direction === 'up') {
    return 'text-emerald-300';
  }
  if (direction === 'down') {
    return 'text-rose-300';
  }

  return 'text-gray-400';
};

const formatDelta = (delta: EraComparisonMetricDelta): string => {
  if (delta.delta === 0) {
    return '0';
  }

  return `${delta.delta > 0 ? '+' : '-'}${formatNumber(Math.abs(delta.delta))}`;
};

const MetricDeltaList: React.FC<{ deltas?: EraComparisonMetricDelta[] }> = ({ deltas }) => {
  if (!deltas || deltas.length === 0) {
    return <div className="text-xs text-gray-500">No comparison metrics were recorded.</div>;
  }

  return (
    <div className="mt-3 space-y-2">
      {deltas.slice(0, 6).map(delta => (
        <div
          key={delta.key}
          className="flex items-center justify-between gap-4 border-t border-[#2f334d] pt-2 text-sm first:border-t-0 first:pt-0"
        >
          <span className="text-gray-300">{delta.label}</span>
          <span className={`font-semibold ${deltaTone(delta.direction)}`}>
            {formatDelta(delta)} {delta.unit}
          </span>
        </div>
      ))}
    </div>
  );
};

const GeneratedItemList: React.FC<{ label: string; items: EraGeneratedEntity[] }> = ({
  label,
  items,
}) => {
  if (items.length === 0) {
    return null;
  }

  return (
    <div>
      <div className="mb-2 text-xs font-semibold uppercase text-gray-500">{label}</div>
      {items.slice(0, 4).map(item => (
        <div key={item.id} className="border-t border-[#2f334d] py-2 first:border-t-0 first:pt-0">
          <div className="text-sm text-gray-200">{item.name}</div>
          <div className="mt-1 text-xs text-gray-500">{item.summary}</div>
        </div>
      ))}
    </div>
  );
};

const GeneratedContent: React.FC<{ generated?: EraGeneratedContent }> = ({ generated }) => {
  if (!generated) {
    return null;
  }

  const total =
    generated.settlements.length +
    generated.heroes.length +
    generated.landmarks.length +
    generated.resources.length;

  if (total === 0) {
    return null;
  }

  return (
    <div className="mt-4">
      <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
        <div>
          <h4 className="text-sm font-semibold text-gray-200">New Era Foundations</h4>
          {generated.summary && <div className="mt-1 text-xs text-gray-500">{generated.summary}</div>}
        </div>
        {generated.eventId && (
          <Link to={`/events/${generated.eventId}`} className="text-xs text-blue-300 hover:text-blue-100">
            Generation Event
          </Link>
        )}
      </div>
      <div className="mt-3 grid grid-cols-1 gap-4 md:grid-cols-2">
        <GeneratedItemList label="Settlements" items={generated.settlements} />
        <GeneratedItemList label="Heroes" items={generated.heroes} />
        <GeneratedItemList label="Landmarks" items={generated.landmarks} />
        <GeneratedItemList label="Resources" items={generated.resources} />
      </div>
    </div>
  );
};

const ChronicleEntry: React.FC<{ entry: EraTransitionHistoryEntry }> = ({ entry }) => (
  <div className="border-t border-[#2f334d] py-4 first:border-t-0 first:pt-0 last:pb-0">
    <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
      <div>
        <h3 className="text-base font-semibold text-gray-100">
          Era {entry.completedEra} to Era {entry.nextEra}
        </h3>
        <div className="mt-1 text-xs text-gray-500">
          Year {entry.previousYear} to {entry.currentYear} - pressure {entry.pressureScore}/100 -
          continuity {entry.continuityScore}/100
        </div>
      </div>
      <div className="flex flex-wrap gap-3 text-sm">
        {entry.eventId && (
          <Link to={`/events/${entry.eventId}`} className="text-blue-300 hover:text-blue-100">
            Event Detail
          </Link>
        )}
        <Link to={`/events?era=${entry.completedEra}`} className="text-blue-300 hover:text-blue-100">
          Era Timeline
        </Link>
      </div>
    </div>
    <p className="mt-3 text-sm text-gray-300">{entry.comparisonSummary ?? entry.summary}</p>
    <GeneratedContent generated={entry.generated} />
    <MetricDeltaList deltas={entry.transitionDelta} />
  </div>
);

const StatLine: React.FC<{ label: string; value: string | number }> = ({ label, value }) => (
  <div className="border-t border-[#2f334d] py-2 first:border-t-0 first:pt-0">
    <div className="text-xs uppercase text-gray-500">{label}</div>
    <div className="mt-1 text-sm font-semibold text-gray-200">{value}</div>
  </div>
);

const ChronicleView: React.FC<{ gameStatus: GameStatus }> = ({ gameStatus }) => {
  const history = gameStatus.eraTransition?.history ?? [];
  const snapshot = gameStatus.eraComparison?.currentSnapshot;

  return (
    <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-6">
      <h2 className="text-xl font-bold text-white">Era Chronicle</h2>
      <p className="mt-2 text-sm text-gray-300">{gameStatus.eraComparison?.summary}</p>

      {history.length > 0 ? (
        <div className="mt-5">
          {history.map(entry => (
            <ChronicleEntry key={entry.id} entry={entry} />
          ))}
        </div>
      ) : (
        <div className="mt-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
          <div className="text-sm text-gray-500">No completed era transition has been recorded.</div>
          {snapshot && (
            <div>
              <h3 className="mb-3 text-sm font-semibold text-gray-200">Current Baseline</h3>
              <StatLine label="Population" value={formatNumber(snapshot.settlements.population)} />
              <StatLine label="Living Heroes" value={`${snapshot.heroes.living} of ${snapshot.heroes.count}`} />
              <StatLine label="Regional Magic" value={snapshot.regions.avgMagic} />
              <StatLine label="Active Bets" value={snapshot.bets.active} />
            </div>
          )}
        </div>
      )}
    </section>
  );
};

const NamedList: React.FC<{ label: string; items: Array<{ id: string; text: string; meta?: string }> }> = ({
  label,
  items,
}) => (
  <div>
    <h3 className="mb-3 text-sm font-semibold text-gray-200">{label}</h3>
    {items.length > 0 ? (
      <div>
        {items.slice(0, 8).map(item => (
          <div key={item.id} className="border-t border-[#2f334d] py-2 first:border-t-0 first:pt-0">
            <div className="text-sm text-gray-200">{item.text}</div>
            {item.meta && <div className="mt-1 text-xs text-gray-500">{item.meta}</div>}
          </div>
        ))}
      </div>
    ) : (
      <div className="text-xs text-gray-500">No candidates.</div>
    )}
  </div>
);

const LegacyView: React.FC<{ gameStatus: GameStatus }> = ({ gameStatus }) => {
  const legacy = gameStatus.eraLegacy;

  if (!legacy) {
    return null;
  }

  return (
    <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-6">
      <h2 className="text-xl font-bold text-white">Continuity Seeds</h2>
      <p className="mt-2 text-sm text-gray-300">{legacy.summary}</p>

      <div className="mt-5 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <NamedList
          label="Heroes"
          items={legacy.heroLegacies.map(hero => ({
            id: hero.heroId,
            text: hero.name,
            meta: `${hero.legacyType} - ${hero.reason}`,
          }))}
        />
        <NamedList
          label="Landmarks"
          items={legacy.landmarkLegacies.map(landmark => ({
            id: landmark.landmarkId,
            text: landmark.name,
            meta: `${landmark.legacyType} - ${landmark.reason}`,
          }))}
        />
        <NamedList
          label="World Scars"
          items={legacy.worldScars.map(scar => ({
            id: scar.id,
            text: scar.targetName,
            meta: `${scar.severity} ${scar.scarType} - ${scar.reason}`,
          }))}
        />
        <NamedList
          label="Era-Spanning Bets"
          items={legacy.eraSpanningBets.map(bet => ({
            id: bet.betId,
            text: bet.description,
            meta: bet.reason,
          }))}
        />
      </div>
    </section>
  );
};

const PressureView: React.FC<{ gameStatus: GameStatus }> = ({ gameStatus }) => {
  const pressure = gameStatus.eraPressure;

  if (!pressure) {
    return null;
  }

  return (
    <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-6">
      <h2 className="text-xl font-bold text-white">Era Pressure</h2>
      <p className="mt-2 text-sm text-gray-300">{pressure.summary}</p>

      <div className="mt-5">
        {pressure.triggers.map(trigger => (
          <div key={trigger.code} className="border-t border-[#2f334d] py-4 first:border-t-0 first:pt-0">
            <div className="flex items-center justify-between gap-3">
              <div className="text-sm font-semibold text-gray-100">{trigger.label}</div>
              <div className="text-sm font-bold text-amber-300">{trigger.score}/100</div>
            </div>
            <div className="mt-2 text-xs text-gray-400">{trigger.summary}</div>
            <div className="mt-3 grid grid-cols-1 gap-2 text-xs md:grid-cols-4">
              {trigger.signals.map(signal => (
                <div key={`${trigger.code}-${signal.label}`} className="border-t border-[#2f334d] pt-2">
                  <span className="text-gray-500">{signal.label}: </span>
                  <span className="text-gray-300">{signal.value}</span>
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </section>
  );
};

const ErasPage: React.FC = () => {
  const [gameStatus, setGameStatus] = useState<GameStatus | null>(null);
  const [selectedView, setSelectedView] = useState<EraView>('chronicle');
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchStatus = async () => {
      try {
        setGameStatus(await getGameStatus());
      } catch (caught) {
        setError(caught instanceof Error ? caught.message : 'Failed to load era status');
      } finally {
        setLoading(false);
      }
    };

    fetchStatus();
  }, []);

  if (loading || error || !gameStatus) {
    return (
      <PageLayout
        gameStatus={gameStatus}
        isLoading={loading}
        error={error}
        loadingMessage="Loading era chronicle..."
      >
        <div />
      </PageLayout>
    );
  }

  const transition = gameStatus.eraTransition;
  const legacy = gameStatus.eraLegacy;
  const pressure = gameStatus.eraPressure;

  return (
    <PageLayout gameStatus={gameStatus}>
      <div className="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
          <h1 className="text-2xl font-bold text-white">Era Chronicle</h1>
          <div className="mt-1 text-sm text-gray-400">
            Era {transition?.currentEra ?? gameStatus.currentYear} - Year {gameStatus.currentYear}
          </div>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          <Link
            to={`/events?era=${transition?.currentEra ?? 1}`}
            className="rounded-md border border-blue-500/30 px-3 py-2 text-sm font-medium text-blue-200 hover:bg-blue-500/10 hover:text-blue-100"
          >
            Current Era Timeline
          </Link>
          <div className="inline-flex rounded-lg border border-[#2f334d] bg-[#1a1b26] p-1">
            {[
              ['chronicle', 'Chronicle'],
              ['legacy', 'Legacy'],
              ['pressure', 'Pressure'],
            ].map(([view, label]) => (
              <button
                key={view}
                type="button"
                onClick={() => setSelectedView(view as EraView)}
                className={`rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                  selectedView === view
                    ? 'bg-yellow-500 text-gray-900'
                    : 'text-gray-300 hover:bg-gray-700 hover:text-white'
                }`}
              >
                {label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
        <div className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
          <div className="text-xs text-gray-500">Next Era Year</div>
          <div className="mt-1 text-xl font-bold text-sky-300">{transition?.nextEraYear ?? 'Unknown'}</div>
        </div>
        <div className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
          <div className="text-xs text-gray-500">Pressure</div>
          <div className="mt-1 text-xl font-bold text-amber-300">{pressure?.pressureScore ?? 0}/100</div>
        </div>
        <div className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
          <div className="text-xs text-gray-500">Continuity</div>
          <div className="mt-1 text-xl font-bold text-emerald-300">{legacy?.continuityScore ?? 0}/100</div>
        </div>
        <div className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
          <div className="text-xs text-gray-500">Completed Eras</div>
          <div className="mt-1 text-xl font-bold text-violet-300">{transition?.history.length ?? 0}</div>
        </div>
      </div>

      {selectedView === 'chronicle' && <ChronicleView gameStatus={gameStatus} />}
      {selectedView === 'legacy' && <LegacyView gameStatus={gameStatus} />}
      {selectedView === 'pressure' && <PressureView gameStatus={gameStatus} />}
    </PageLayout>
  );
};

export default ErasPage;

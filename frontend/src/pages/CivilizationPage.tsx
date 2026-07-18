import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { advanceCivilization, getCivilization } from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import type { AdvanceCivilizationPayload } from '../api/apiService';
import type {
  CivilizationDecision,
  CivilizationDiplomacy,
  CivilizationRegionAgenda,
  CivilizationStatusResponse,
} from '../entities/civilization';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Civilization action failed';
};

const labelize = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ');
};

const tierTone = (tier?: string): string => {
  if (tier === 'urgent') return 'border-red-500/60 bg-red-950/30 text-red-100';
  if (tier === 'active') return 'border-amber-500/60 bg-amber-950/30 text-amber-100';
  if (tier === 'watch') return 'border-cyan-500/50 bg-cyan-950/25 text-cyan-100';
  return 'border-[#2f334d] bg-gray-900/50 text-gray-300';
};

const behaviorTone = (behavior?: string): string => {
  switch (behavior) {
    case 'defense':
      return 'text-sky-200';
    case 'trade':
      return 'text-emerald-200';
    case 'rivalry':
      return 'text-red-200';
    case 'research':
      return 'text-violet-200';
    case 'recovery':
      return 'text-amber-200';
    default:
      return 'text-cyan-200';
  }
};

const scoreWidth = (score: number): string => `${Math.max(0, Math.min(100, score))}%`;

interface AgendaCardProps {
  agenda: CivilizationRegionAgenda;
  isAdvancing: boolean;
  onAdvance: (payload: AdvanceCivilizationPayload) => void;
}

const AgendaCard: React.FC<AgendaCardProps> = ({ agenda, isAdvancing, onAdvance }) => {
  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">{agenda.regionName}</h2>
            <span className={`rounded border px-2 py-0.5 text-xs font-semibold ${tierTone(agenda.priorityTier)}`}>
              {labelize(agenda.priorityTier)}
            </span>
            <span className={`text-sm font-semibold ${behaviorTone(agenda.dominantBehavior)}`}>
              {agenda.dominantBehaviorLabel}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{agenda.summary}</p>
        </div>
        <div className="text-right">
          <div className="text-xs uppercase text-gray-500">Pressure</div>
          <div className="text-2xl font-bold text-yellow-300">{agenda.score}</div>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Population</div>
          <div className="mt-1 text-sm text-gray-200">{agenda.stats.population.toLocaleString()}</div>
        </div>
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Defense</div>
          <div className="mt-1 text-sm text-gray-200">{agenda.stats.avgSettlementDefense}</div>
        </div>
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Resources</div>
          <div className="mt-1 text-sm text-gray-200">
            {agenda.stats.productiveResources}/{agenda.stats.resources}
          </div>
        </div>
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Culture</div>
          <div className="mt-1 text-sm capitalize text-gray-200">{labelize(agenda.stats.culture)}</div>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
        {agenda.signals.slice(0, 3).map(signal => (
          <div key={`${agenda.regionId}-${signal.label}`} className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">{signal.label}</div>
            <div className="mt-1 text-sm font-semibold text-gray-200">{signal.value}</div>
            <div className="mt-1 text-xs text-gray-400">{signal.summary}</div>
          </div>
        ))}
      </div>

      <div className="mt-4 space-y-2">
        {agenda.scores.map(score => (
          <div key={`${agenda.regionId}-${score.key}`}>
            <div className="mb-1 flex items-center justify-between text-xs">
              <span className="text-gray-400">{score.label}</span>
              <span className="font-semibold text-gray-200">{score.score}</span>
            </div>
            <div className="h-2 rounded bg-gray-900">
              <div className="h-2 rounded bg-cyan-400" style={{ width: scoreWidth(score.score) }} />
            </div>
          </div>
        ))}
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3">
        <button
          type="button"
          disabled={isAdvancing}
          onClick={() => onAdvance({ regionId: agenda.regionId })}
          className={`rounded bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-cyan-500 ${
            isAdvancing ? 'cursor-not-allowed opacity-50' : ''
          }`}
        >
          Advance Civic Agenda
        </button>
        <Link
          to={`/events?regionId=${encodeURIComponent(agenda.regionId)}`}
          className="text-sm text-blue-300 hover:text-blue-100"
        >
          Timeline
        </Link>
      </div>
    </article>
  );
};

const DecisionCard: React.FC<{ decision: CivilizationDecision }> = ({ decision }) => {
  const changeCount =
    decision.changes.regions.length +
    decision.changes.settlements.length +
    decision.changes.resources.length +
    decision.changes.heroes.length +
    decision.changes.landmarks.length;

  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">{decision.regionName}</h2>
            <span className={`text-sm font-semibold ${behaviorTone(decision.behavior)}`}>
              {decision.behaviorLabel}
            </span>
            <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
              {labelize(decision.source)}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{decision.effectSummary}</p>
        </div>
        <div className="text-sm text-gray-400">Year {decision.year}</div>
      </div>

      <div className="mt-4 rounded bg-gray-800 px-3 py-2 text-sm text-gray-300">
        {decision.signalSummary}
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3 text-sm">
        {decision.eventId && (
          <Link to={`/events/${decision.eventId}`} className="text-blue-300 hover:text-blue-100">
            Event
          </Link>
        )}
        <Link
          to={`/events?regionId=${encodeURIComponent(decision.regionId)}`}
          className="text-blue-300 hover:text-blue-100"
        >
          Timeline
        </Link>
        <span className="text-gray-500">{changeCount} changed entities</span>
      </div>
    </article>
  );
};

const DiplomacyCard: React.FC<{ diplomacy: CivilizationDiplomacy }> = ({ diplomacy }) => {
  const changeCount =
    diplomacy.changes.regions.length +
    diplomacy.changes.settlements.length +
    diplomacy.changes.resources.length +
    diplomacy.changes.heroes.length +
    diplomacy.changes.landmarks.length;
  const eventIds =
    diplomacy.eventIds.length > 0
      ? diplomacy.eventIds
      : diplomacy.eventId
        ? [diplomacy.eventId]
        : [];

  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">
              {diplomacy.sourceRegionName} and {diplomacy.targetRegionName}
            </h2>
            <span
              className={`text-sm font-semibold ${
                diplomacy.kind === 'rivalry_front' ? 'text-red-200' : 'text-emerald-200'
              }`}
            >
              {diplomacy.kindLabel}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">
            {diplomacy.effectSummary || diplomacy.summary}
          </p>
        </div>
        <div className="text-right text-sm text-gray-400">
          <div>Step {diplomacy.stepCount}</div>
          <div>Year {diplomacy.lastAdvancedYear || diplomacy.startedYear}</div>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Pressure</div>
          <div className="mt-1 text-sm font-semibold text-yellow-300">{diplomacy.score}</div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Started</div>
          <div className="mt-1 text-sm text-gray-200">Year {diplomacy.startedYear}</div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Changed</div>
          <div className="mt-1 text-sm text-gray-200">{changeCount} entities</div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Regions</div>
          <div className="mt-1 text-sm text-gray-200">{diplomacy.relatedRegionIds.length}</div>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-3 text-sm">
        {eventIds.slice(0, 3).map(eventId => (
          <Link
            key={eventId}
            to={`/events/${eventId}`}
            className="text-blue-300 hover:text-blue-100"
          >
            Event
          </Link>
        ))}
        <Link
          to={`/events?regionId=${encodeURIComponent(diplomacy.sourceRegionId)}`}
          className="text-blue-300 hover:text-blue-100"
        >
          Source Timeline
        </Link>
        <Link
          to={`/events?regionId=${encodeURIComponent(diplomacy.targetRegionId)}`}
          className="text-blue-300 hover:text-blue-100"
        >
          Target Timeline
        </Link>
      </div>
    </article>
  );
};

const CivilizationPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const [civilization, setCivilization] = useState<CivilizationStatusResponse | null>(null);
  const [civilizationError, setCivilizationError] = useState<string | null>(null);
  const [isLoadingCivilization, setIsLoadingCivilization] = useState(true);
  const [advancingRegionId, setAdvancingRegionId] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchCivilization = useCallback(async () => {
    try {
      setIsLoadingCivilization(true);
      setCivilization(await getCivilization());
      setCivilizationError(null);
    } catch (error: unknown) {
      setCivilizationError(getErrorMessage(error));
    } finally {
      setIsLoadingCivilization(false);
    }
  }, []);

  useEffect(() => {
    void fetchCivilization();
  }, [fetchCivilization]);

  const runAdvance = async (payload: AdvanceCivilizationPayload = {}) => {
    const targetRegionId = payload.regionId ?? civilization?.topAgenda?.regionId ?? 'top';
    setAdvancingRegionId(targetRegionId);
    setActionMessage(null);

    try {
      const response = await advanceCivilization(payload);

      if (response.success === false) {
        setActionMessage(`Failed: ${response.message || 'Civilization advance failed'}`);
        return;
      }

      setActionMessage(response.message || 'Civilization advanced.');
      if (response.status) {
        setCivilization(response.status);
      } else {
        await fetchCivilization();
      }
      await refetchStatus();
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setAdvancingRegionId(null);
    }
  };

  const statusCivilization = civilization ?? gameStatus?.civilization ?? null;
  const topAgenda = statusCivilization?.topAgenda ?? null;
  const agendas = statusCivilization?.regionAgendas ?? [];
  const recentDecisions = statusCivilization?.recentDecisions ?? [];
  const diplomacy = statusCivilization?.diplomacy ?? [];
  const isLoading = isLoadingStatus || isLoadingCivilization;
  const error = statusError || civilizationError;

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading civilization..."
      errorPrefix="Error loading civilization"
      showEventLog={true}
      selectedRegionId={topAgenda?.regionId ?? undefined}
    >
      <PageHeader
        title="Civilization"
        subtitle={statusCivilization?.summary ?? 'Regional civic agendas and decisions'}
        icon="◇"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Civic Agenda</h2>
            <p className="mt-1 text-sm text-gray-400">
              {topAgenda
                ? `${topAgenda.regionName}: ${topAgenda.dominantBehaviorLabel} pressure ${topAgenda.score}/100`
                : 'No civic agenda is visible yet.'}
            </p>
          </div>
          <button
            type="button"
            disabled={!topAgenda || advancingRegionId !== null}
            onClick={() => {
              void runAdvance();
            }}
            className={`rounded bg-cyan-600 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-cyan-500 ${
              topAgenda && advancingRegionId === null ? '' : 'cursor-not-allowed opacity-50'
            }`}
          >
            Advance Civic Agenda
          </button>
        </div>

        {actionMessage && (
          <div
            className={`mt-4 rounded px-3 py-2 text-sm ${
              actionMessage.startsWith('Failed')
                ? 'bg-red-800 text-red-100'
                : 'bg-emerald-800 text-emerald-100'
            }`}
          >
            {actionMessage}
          </div>
        )}

        <div className="mt-4 grid grid-cols-2 gap-3 md:grid-cols-6">
          {Object.entries(statusCivilization?.behaviorCounts ?? {}).map(([behavior, count]) => (
            <div key={behavior} className="rounded bg-gray-800 px-3 py-2">
              <div className="text-xs uppercase text-gray-500">{labelize(behavior)}</div>
              <div className={`mt-1 text-sm font-semibold ${behaviorTone(behavior)}`}>{count}</div>
            </div>
          ))}
        </div>
      </section>

      <section className="space-y-4">
        <div>
          <h2 className="text-xl font-bold text-white">Civic Diplomacy</h2>
          <p className="mt-1 text-sm text-gray-400">
            {statusCivilization?.diplomacySummary ?? 'No active civic diplomacy has formed yet.'}
          </p>
        </div>
        {diplomacy.length > 0 ? (
          diplomacy.map(pact => <DiplomacyCard key={pact.id || pact.pactKey} diplomacy={pact} />)
        ) : (
          <EmptyState
            title="No Civic Diplomacy"
            message="Trade compacts and rivalry fronts form automatically when regional pressure connects two regions."
            icon="◇"
          />
        )}
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-bold text-white">Regional Agendas</h2>
        {agendas.length > 0 ? (
          agendas.map(agenda => (
            <AgendaCard
              key={agenda.regionId}
              agenda={agenda}
              isAdvancing={advancingRegionId === agenda.regionId || advancingRegionId === 'top'}
              onAdvance={payload => {
                void runAdvance(payload);
              }}
            />
          ))
        ) : (
          <EmptyState
            title="No Regional Agendas"
            message="Regions, settlements, heroes, resources, and landmarks will populate this panel."
            icon="◇"
          />
        )}
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-bold text-white">Recent Decisions</h2>
        {recentDecisions.length > 0 ? (
          recentDecisions.map(decision => <DecisionCard key={decision.id} decision={decision} />)
        ) : (
          <EmptyState
            title="No Recent Decisions"
            message="Civilization agendas will record events here after they advance."
            icon="◇"
          />
        )}
      </section>
    </PageLayout>
  );
};

export default CivilizationPage;

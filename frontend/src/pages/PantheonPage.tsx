import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { counterplayPantheon, getPantheon } from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import type {
  PantheonBettingHook,
  PantheonChanges,
  PantheonCounterplayAction,
  PantheonCounterplayMode,
  PantheonDeity,
  PantheonIntervention,
  PantheonPoliticalEscalation,
  PantheonRelationship,
  PantheonRelationshipArc,
  PantheonStatusResponse,
} from '../entities/pantheon';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Pantheon status failed';
};

const labelize = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ');
};

const tierTone = (tier?: string): string => {
  if (tier === 'dominant') return 'border-red-500/60 bg-red-950/30 text-red-100';
  if (tier === 'active') return 'border-amber-500/60 bg-amber-950/30 text-amber-100';
  if (tier === 'watch') return 'border-cyan-500/50 bg-cyan-950/25 text-cyan-100';
  return 'border-[#2f334d] bg-gray-900/50 text-gray-300';
};

const domainTone = (domain?: string): string => {
  switch (domain) {
    case 'prosperity':
      return 'text-emerald-200';
    case 'strife':
      return 'text-red-200';
    case 'secrets':
      return 'text-violet-200';
    case 'entropy':
      return 'text-amber-200';
    default:
      return 'text-cyan-200';
  }
};

const scoreWidth = (score: number): string => `${Math.max(0, Math.min(100, score))}%`;

const changeCount = (record: { changes: PantheonChanges }): number =>
  record.changes.regions.length +
  record.changes.settlements.length +
  record.changes.resources.length +
  record.changes.heroes.length +
  record.changes.landmarks.length;

const DeityCard: React.FC<{
  deity: PantheonDeity;
  costs?: Partial<Record<PantheonCounterplayMode, number>>;
  activeAction: string | null;
  onCounterplay: (deityId: string, mode: PantheonCounterplayMode) => void;
}> = ({ deity, costs, activeAction, onCounterplay }) => (
  <article className={`rounded-lg border bg-[#1a1b26] p-5 ${tierTone(deity.pressureTier)}`}>
    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-bold text-white">{deity.name}</h2>
          <span className={`text-sm font-semibold capitalize ${domainTone(deity.domain)}`}>
            {labelize(deity.domain)}
          </span>
          <span className="rounded bg-gray-800 px-2 py-0.5 text-xs capitalize text-gray-300">
            {labelize(deity.alignment)}
          </span>
        </div>
        <p className="mt-1 text-sm text-gray-300">{deity.goal}</p>
      </div>
      <div className="text-right">
        <div className="text-xs uppercase text-gray-500">Pressure</div>
        <div className="text-2xl font-bold text-yellow-300">{deity.pressureScore}</div>
      </div>
    </div>

    <div className="mt-4 h-2 rounded bg-gray-900">
      <div className="h-2 rounded bg-yellow-400" style={{ width: scoreWidth(deity.pressureScore) }} />
    </div>

    <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
      <div className="rounded bg-gray-900/70 px-3 py-2">
        <div className="text-xs uppercase text-gray-500">Target</div>
        <div className="mt-1 text-sm text-gray-200">{deity.targetRegionName ?? 'No region'}</div>
      </div>
      <div className="rounded bg-gray-900/70 px-3 py-2">
        <div className="text-xs uppercase text-gray-500">Interventions</div>
        <div className="mt-1 text-sm text-gray-200">{deity.interventionCount}</div>
      </div>
      <div className="rounded bg-gray-900/70 px-3 py-2">
        <div className="text-xs uppercase text-gray-500">Tier</div>
        <div className="mt-1 text-sm capitalize text-gray-200">{labelize(deity.pressureTier)}</div>
      </div>
    </div>

    <div className="mt-4 rounded bg-gray-800 px-3 py-2 text-sm text-gray-300">
      {deity.strategy}
    </div>

    <div className="mt-4 rounded border border-[#2f334d] bg-gray-950/50 p-3">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <div className="text-xs uppercase text-gray-500">Player Counterplay</div>
          <div className="mt-1 text-sm text-gray-300">
            {deity.counterplay?.summary ?? 'No active appeasement or challenge.'}
          </div>
        </div>
        <div className="flex flex-wrap gap-2">
          {(['appease', 'challenge'] as const).map(mode => {
            const actionKey = `${deity.id}:${mode}`;
            const isActing = activeAction === actionKey;
            const label = mode === 'appease' ? 'Appease' : 'Challenge';
            const cost = costs?.[mode] ?? (mode === 'appease' ? 12 : 18);

            return (
              <button
                key={mode}
                type="button"
                disabled={activeAction !== null}
                onClick={() => onCounterplay(deity.id, mode)}
                className={`rounded px-3 py-2 text-sm font-semibold transition-colors disabled:cursor-not-allowed disabled:opacity-60 ${
                  mode === 'appease'
                    ? 'bg-emerald-600 text-white hover:bg-emerald-500'
                    : 'bg-red-700 text-white hover:bg-red-600'
                }`}
              >
                {isActing ? 'Working...' : `${label} (${cost})`}
              </button>
            );
          })}
        </div>
      </div>
      <div className="mt-3 grid grid-cols-3 gap-2 text-xs text-gray-400">
        <div>
          <span className="text-gray-500">Appeasement: </span>
          {deity.counterplay?.appeasement ?? 0}
        </div>
        <div>
          <span className="text-gray-500">Defiance: </span>
          {deity.counterplay?.defiance ?? 0}
        </div>
        <div>
          <span className="text-gray-500">Pressure: </span>
          -{deity.counterplay?.pressureReduction ?? 0}
        </div>
      </div>
    </div>
  </article>
);

const RelationshipRow: React.FC<{ relationship: PantheonRelationship }> = ({ relationship }) => (
  <div className="rounded border border-[#2f334d] bg-[#16161e] px-3 py-2 text-sm">
    <div className="flex flex-wrap items-center justify-between gap-2">
      <span className="font-semibold text-gray-100">
        {relationship.sourceName} to {relationship.targetName}
      </span>
      <span
        className={
          relationship.stance === 'ally'
            ? 'font-semibold text-emerald-300'
            : 'font-semibold text-red-300'
        }
      >
        {labelize(relationship.stance)}
      </span>
    </div>
    <div className="mt-1 text-xs text-gray-500">{relationship.summary}</div>
    {typeof relationship.tension === 'number' && (
      <div className="mt-2 text-xs text-gray-400">Tension {relationship.tension}/100</div>
    )}
  </div>
);

const PoliticalEscalationCard: React.FC<{ escalation: PantheonPoliticalEscalation }> = ({
  escalation,
}) => (
  <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="text-base font-bold text-white">{escalation.sourceName}</h3>
          <span
            className={
              escalation.stance === 'ally'
                ? 'text-sm font-semibold text-emerald-300'
                : 'text-sm font-semibold text-red-300'
            }
          >
            {labelize(escalation.stance)}
          </span>
          <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
            {labelize(escalation.stage)}
          </span>
        </div>
        <p className="mt-1 text-sm text-gray-300">{escalation.summary}</p>
      </div>
      <div className="text-right text-sm text-gray-400">
        <div>{escalation.pressureScore}/100 pressure</div>
        <div>{escalation.tension}/100 tension</div>
      </div>
    </div>
    <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
      {escalation.targetRegionId && (
        <Link
          to={`/events?regionId=${encodeURIComponent(escalation.targetRegionId)}`}
          className="text-blue-300 hover:text-blue-100"
        >
          Region Timeline
        </Link>
      )}
      <span className="text-gray-500">
        {escalation.interventionCount} recent intervention
        {escalation.interventionCount === 1 ? '' : 's'}
      </span>
    </div>
  </article>
);

const RelationshipArcCard: React.FC<{ arc: PantheonRelationshipArc }> = ({ arc }) => {
  const eventIds = arc.eventIds.length > 0 ? arc.eventIds : arc.eventId ? [arc.eventId] : [];

  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h3 className="text-base font-bold text-white">{arc.title}</h3>
            <span
              className={
                arc.stance === 'ally'
                  ? 'text-sm font-semibold text-emerald-300'
                  : 'text-sm font-semibold text-red-300'
              }
            >
              {labelize(arc.stance)}
            </span>
            <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
              {labelize(arc.stage)}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{arc.summary}</p>
        </div>
        <div className="text-right text-sm text-gray-400">
          <div>Step {arc.stepCount}</div>
          <div>{arc.momentum}/100 momentum</div>
        </div>
      </div>

      <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
        <span className="text-gray-500">
          {arc.sourceName} to {arc.targetName}
        </span>
        <span className="text-gray-500">Year {arc.lastAdvancedYear}</span>
        <span className="text-gray-500">{changeCount(arc)} changed entities</span>
        {arc.targetRegionId && (
          <Link
            to={`/events?regionId=${encodeURIComponent(arc.targetRegionId)}`}
            className="text-blue-300 hover:text-blue-100"
          >
            {arc.targetRegionName ?? 'Region Timeline'}
          </Link>
        )}
        {eventIds.slice(0, 3).map(eventId => (
          <Link key={eventId} to={`/events/${eventId}`} className="text-blue-300 hover:text-blue-100">
            Event
          </Link>
        ))}
      </div>
      {arc.nextPressure && <div className="mt-2 text-xs text-gray-500">{arc.nextPressure}</div>}
    </article>
  );
};

const BettingHookCard: React.FC<{ hook: PantheonBettingHook }> = ({ hook }) => (
  <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="text-base font-bold text-white">{hook.title}</h3>
          <span className={`text-sm font-semibold capitalize ${domainTone(hook.domain)}`}>
            {labelize(hook.domain)}
          </span>
          <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
            {hook.regionName ?? 'No region'}
          </span>
        </div>
        <p className="mt-1 text-sm text-gray-300">{hook.summary}</p>
      </div>
      <div className="text-right text-sm text-gray-400">
        <div>{hook.pressureScore}/100 pressure</div>
        <div className="capitalize">{labelize(hook.confidence)}</div>
      </div>
    </div>
    <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
      <Link to="/betting" className="text-blue-300 hover:text-blue-100">
        Betting
      </Link>
      <span className="text-gray-500">
        {hook.minimumYears}-{hook.maximumYears} year window
      </span>
      {hook.riskSummary && <span className="text-gray-500">{hook.riskSummary}</span>}
    </div>
  </article>
);

const InterventionCard: React.FC<{ intervention: PantheonIntervention }> = ({ intervention }) => (
  <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <h2 className="text-lg font-bold text-white">{intervention.deityName}</h2>
          <span className={`text-sm font-semibold capitalize ${domainTone(intervention.domain)}`}>
            {labelize(intervention.domain)}
          </span>
          <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
            {intervention.targetRegionName ?? 'No region'}
          </span>
        </div>
        <p className="mt-1 text-sm text-gray-300">{intervention.summary}</p>
      </div>
      <div className="text-sm text-gray-400">Year {intervention.year}</div>
    </div>

    <div className="mt-4 flex flex-wrap items-center gap-3 text-sm">
      {intervention.eventId && (
        <Link to={`/events/${intervention.eventId}`} className="text-blue-300 hover:text-blue-100">
          Event
        </Link>
      )}
      {intervention.targetRegionId && (
        <Link
          to={`/events?regionId=${encodeURIComponent(intervention.targetRegionId)}`}
          className="text-blue-300 hover:text-blue-100"
        >
          Timeline
        </Link>
      )}
      <span className="text-gray-500">{changeCount(intervention)} changed entities</span>
    </div>
  </article>
);

const CounterplayCard: React.FC<{ action: PantheonCounterplayAction }> = ({ action }) => (
  <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-4">
    <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
      <div>
        <div className="flex flex-wrap items-center gap-2">
          <h3 className="text-base font-bold text-white">{action.deityName}</h3>
          <span className={`text-sm font-semibold capitalize ${domainTone(action.domain)}`}>
            {labelize(action.mode)}
          </span>
          <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
            {action.targetRegionName ?? 'No region'}
          </span>
        </div>
        <p className="mt-1 text-sm text-gray-300">{action.summary}</p>
      </div>
      <div className="text-sm text-gray-400">Year {action.year}</div>
    </div>

    <div className="mt-3 flex flex-wrap items-center gap-3 text-sm">
      {action.eventId && (
        <Link to={`/events/${action.eventId}`} className="text-blue-300 hover:text-blue-100">
          Event
        </Link>
      )}
      {action.targetRegionId && (
        <Link
          to={`/events?regionId=${encodeURIComponent(action.targetRegionId)}`}
          className="text-blue-300 hover:text-blue-100"
        >
          Timeline
        </Link>
      )}
      <span className="text-gray-500">{action.cost} favor spent</span>
    </div>
  </article>
);

const PantheonPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const [pantheon, setPantheon] = useState<PantheonStatusResponse | null>(null);
  const [pantheonError, setPantheonError] = useState<string | null>(null);
  const [isLoadingPantheon, setIsLoadingPantheon] = useState(true);
  const [activeAction, setActiveAction] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchPantheon = useCallback(async () => {
    try {
      setIsLoadingPantheon(true);
      setPantheon(await getPantheon());
      setPantheonError(null);
    } catch (error: unknown) {
      setPantheonError(getErrorMessage(error));
    } finally {
      setIsLoadingPantheon(false);
    }
  }, []);

  useEffect(() => {
    void fetchPantheon();
  }, [fetchPantheon]);

  const handleCounterplay = useCallback(async (deityId: string, mode: PantheonCounterplayMode) => {
    const actionKey = `${deityId}:${mode}`;
    try {
      setActiveAction(actionKey);
      setPantheonError(null);
      setActionMessage(null);
      const result = await counterplayPantheon(deityId, { mode });
      if (result.status) {
        setPantheon(result.status);
      }
      if (result.success === false) {
        setPantheonError(result.message ?? 'Pantheon counterplay failed');
      } else {
        setActionMessage(result.message ?? 'Pantheon counterplay recorded.');
      }
      await refetchStatus();
    } catch (error: unknown) {
      setPantheonError(getErrorMessage(error));
    } finally {
      setActiveAction(null);
    }
  }, [refetchStatus]);

  const statusPantheon = pantheon ?? gameStatus?.pantheon ?? null;
  const topActor = statusPantheon?.topActor ?? null;
  const deities = statusPantheon?.deities ?? [];
  const recentInterventions = statusPantheon?.recentInterventions ?? [];
  const relationships = statusPantheon?.relationships ?? [];
  const politics = statusPantheon?.politics ?? null;
  const escalations = politics?.escalations ?? [];
  const relationshipArcs = statusPantheon?.relationshipArcs ?? [];
  const bettingHooks = statusPantheon?.bettingHooks ?? [];
  const counterplay = statusPantheon?.counterplay ?? null;
  const isLoading = isLoadingStatus || isLoadingPantheon;
  const error = statusError || pantheonError;

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading pantheon..."
      errorPrefix="Error loading pantheon"
      showEventLog={true}
      selectedRegionId={topActor?.targetRegionId ?? undefined}
    >
      <PageHeader
        title="AI Pantheon"
        subtitle={statusPantheon?.summary ?? 'Non-player divine pressure and interventions'}
        icon="♛"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Pantheon Pressure</h2>
            <p className="mt-1 text-sm text-gray-400">
              {topActor
                ? `${topActor.name} leads with ${labelize(topActor.domain)} pressure at ${topActor.pressureScore}/100 over ${topActor.targetRegionName ?? 'no region'}.`
                : 'No pantheon pressure is visible yet.'}
            </p>
          </div>
          <div className="text-right">
            <div className="text-xs uppercase text-gray-500">Current Year</div>
            <div className="text-2xl font-bold text-yellow-300">
              {statusPantheon?.currentYear ?? gameStatus?.currentYear ?? 0}
            </div>
          </div>
        </div>

        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-4">
          {(statusPantheon?.pressure ?? []).map(pressure => (
            <div key={pressure.deityId} className="rounded bg-gray-800 px-3 py-2">
              <div className="text-xs uppercase text-gray-500">{pressure.deityName}</div>
              <div className={`mt-1 text-sm font-semibold ${domainTone(pressure.domain)}`}>
                {pressure.pressureScore}/100
              </div>
              <div className="mt-1 text-xs text-gray-500">{pressure.targetRegionName ?? 'No region'}</div>
            </div>
          ))}
        </div>
      </section>

      {(counterplay || actionMessage) && (
        <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
          <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
            <div>
              <h2 className="text-xl font-bold text-white">Player Counterplay</h2>
              <p className="mt-1 text-sm text-gray-400">
                {actionMessage ?? counterplay?.summary ?? 'Appease or challenge AI deities to suppress near-term pressure.'}
              </p>
            </div>
            <div className="text-sm text-gray-500">
              Appease {counterplay?.costs.appease ?? 12} favor / Challenge{' '}
              {counterplay?.costs.challenge ?? 18} favor
            </div>
          </div>
          {counterplay?.recentActions && counterplay.recentActions.length > 0 && (
            <div className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-2">
              {counterplay.recentActions.slice(0, 4).map(action => (
                <CounterplayCard key={action.id} action={action} />
              ))}
            </div>
          )}
        </section>
      )}

      <section className="space-y-4">
        <div>
          <h2 className="text-xl font-bold text-white">Pantheon Betting Hooks</h2>
          {bettingHooks.length > 0 && (
            <p className="mt-1 text-sm text-gray-400">
              {bettingHooks.length} intervention wager
              {bettingHooks.length === 1 ? '' : 's'} available from current pressure.
            </p>
          )}
        </div>
        {bettingHooks.length > 0 ? (
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {bettingHooks.map(hook => (
              <BettingHookCard key={hook.id} hook={hook} />
            ))}
          </div>
        ) : (
          <EmptyState
            title="No Pantheon Wagers"
            message="Pantheon intervention wagers appear when divine pressure is high enough."
            icon="♛"
          />
        )}
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-bold text-white">Divine Actors</h2>
        {deities.length > 0 ? (
          <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
            {deities.map(deity => (
              <DeityCard
                key={deity.id}
                deity={deity}
                costs={counterplay?.costs}
                activeAction={activeAction}
                onCounterplay={handleCounterplay}
              />
            ))}
          </div>
        ) : (
          <EmptyState
            title="No Pantheon Actors"
            message="Pantheon actors will appear after backend status is available."
            icon="♛"
          />
        )}
      </section>

      <section className="space-y-4">
        <div>
          <h2 className="text-xl font-bold text-white">Pantheon Politics</h2>
          {politics?.summary && <p className="mt-1 text-sm text-gray-400">{politics.summary}</p>}
        </div>
        {escalations.length > 0 && (
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {escalations.slice(0, 4).map(escalation => (
              <PoliticalEscalationCard
                key={`${escalation.sourceId}-${escalation.targetId}-${escalation.stance}`}
                escalation={escalation}
              />
            ))}
          </div>
        )}
        {relationships.length > 0 ? (
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {relationships.map(relationship => (
              <RelationshipRow
                key={`${relationship.sourceId}-${relationship.targetId}-${relationship.stance}`}
                relationship={relationship}
              />
            ))}
          </div>
        ) : (
          <EmptyState
            title="No Relationships"
            message="Pantheon alliances and rivalries will appear here."
            icon="◇"
          />
        )}
      </section>

      <section className="space-y-4">
        <div>
          <h2 className="text-xl font-bold text-white">Alliance and Rival Arcs</h2>
          {relationshipArcs.length > 0 && (
            <p className="mt-1 text-sm text-gray-400">
              {relationshipArcs.length} persistent pantheon political arc
              {relationshipArcs.length === 1 ? '' : 's'} recorded from recent ticks.
            </p>
          )}
        </div>
        {relationshipArcs.length > 0 ? (
          <div className="grid grid-cols-1 gap-3 lg:grid-cols-2">
            {relationshipArcs.slice(0, 4).map(arc => (
              <RelationshipArcCard key={arc.arcKey} arc={arc} />
            ))}
          </div>
        ) : (
          <EmptyState
            title="No Political Arcs"
            message="Escalated alliances and rivalries will persist here after pantheon ticks advance."
            icon="◇"
          />
        )}
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-bold text-white">Recent Interventions</h2>
        {recentInterventions.length > 0 ? (
          recentInterventions.map(intervention => (
            <InterventionCard key={intervention.id} intervention={intervention} />
          ))
        ) : (
          <EmptyState
            title="No Interventions"
            message="AI deities will record autonomous world changes here after ticks run."
            icon="♛"
          />
        )}
      </section>
    </PageLayout>
  );
};

export default PantheonPage;

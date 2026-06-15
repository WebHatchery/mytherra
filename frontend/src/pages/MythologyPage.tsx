import React, { useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  PromoteMythResponse,
  getMythology,
  promoteMyth,
} from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import type {
  MythCandidate,
  MythologyStatusResponse,
  PromotedMyth,
} from '../entities/mythology';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Mythology action failed';
};

const labelize = (value?: string | null): string => {
  if (!value) return 'Unknown';
  return value.replace(/_/g, ' ');
};

const strengthTone = (strength?: string): string => {
  if (strength === 'mythic') return 'bg-fuchsia-900/70 text-fuchsia-100';
  if (strength === 'strong') return 'bg-sky-900/70 text-sky-100';
  if (strength === 'forming') return 'bg-amber-900/70 text-amber-100';
  return 'bg-gray-800 text-gray-300';
};

interface MythCardProps {
  myth: PromotedMyth;
}

const MythCard: React.FC<MythCardProps> = ({ myth }) => {
  const affectedCount =
    myth.effects.regions.length + myth.effects.heroes.length + myth.effects.landmarks.length;

  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">{myth.title}</h2>
            <span className={`rounded px-2 py-0.5 text-xs font-semibold ${strengthTone(myth.strength)}`}>
              {labelize(myth.strength)}
            </span>
            <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
              {myth.mythTypeLabel}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{myth.summary}</p>
        </div>
        <div className="text-sm text-gray-400">
          Year {myth.sourceYear ?? '?'} to {myth.promotedYear}
        </div>
      </div>

      <div className="mt-4 rounded bg-gray-800 px-3 py-2 text-sm text-gray-300">
        {myth.influenceSummary}
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Regions</div>
          <div className="mt-1 text-sm text-gray-200">{myth.effects.regions.length}</div>
        </div>
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Heroes</div>
          <div className="mt-1 text-sm text-gray-200">{myth.effects.heroes.length}</div>
        </div>
        <div className="rounded bg-gray-900/70 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Landmarks</div>
          <div className="mt-1 text-sm text-gray-200">{myth.effects.landmarks.length}</div>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-3 text-sm">
        <Link to={`/events/${myth.sourceEventId}`} className="text-blue-300 hover:text-blue-100">
          Source Event
        </Link>
        <Link to={`/events/${myth.promotionEventId}`} className="text-blue-300 hover:text-blue-100">
          Promotion Event
        </Link>
        {affectedCount === 0 && <span className="text-gray-500">No direct entity effects recorded.</span>}
      </div>
    </article>
  );
};

interface CandidateCardProps {
  candidate: MythCandidate;
  canPromote: boolean;
  isPromoting: boolean;
  promotionCost: number;
  onPromote: (candidate: MythCandidate) => void;
}

const CandidateCard: React.FC<CandidateCardProps> = ({
  candidate,
  canPromote,
  isPromoting,
  promotionCost,
  onPromote,
}) => {
  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">{candidate.title}</h2>
            <span className={`rounded px-2 py-0.5 text-xs font-semibold ${strengthTone(candidate.strength)}`}>
              {candidate.score}/100
            </span>
            <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
              {candidate.mythTypeLabel}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{candidate.summary}</p>
        </div>
        <Link
          to={`/events/${candidate.eventId}`}
          className="text-sm text-blue-300 hover:text-blue-100"
        >
          Event
        </Link>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div className="rounded bg-gray-800 px-3 py-2 text-sm text-gray-300">
          {candidate.reason}
        </div>
        <div className="rounded bg-gray-800 px-3 py-2 text-sm text-gray-300">
          {candidate.influencePreview}
        </div>
      </div>

      <button
        type="button"
        disabled={!canPromote || isPromoting}
        onClick={() => onPromote(candidate)}
        className={`mt-4 rounded bg-fuchsia-500 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-fuchsia-400 ${
          canPromote && !isPromoting ? '' : 'cursor-not-allowed opacity-50'
        }`}
      >
        Promote Myth
        <span className="block text-xs font-normal">Cost: {promotionCost}</span>
      </button>
    </article>
  );
};

const MythologyPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const [mythology, setMythology] = useState<MythologyStatusResponse | null>(null);
  const [mythologyError, setMythologyError] = useState<string | null>(null);
  const [isLoadingMythology, setIsLoadingMythology] = useState(true);
  const [promotingEventId, setPromotingEventId] = useState<string | null>(null);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchMythology = useCallback(async () => {
    try {
      setIsLoadingMythology(true);
      setMythology(await getMythology());
      setMythologyError(null);
    } catch (error: unknown) {
      setMythologyError(getErrorMessage(error));
    } finally {
      setIsLoadingMythology(false);
    }
  }, []);

  useEffect(() => {
    void fetchMythology();
  }, [fetchMythology]);

  const currentDivineFavor = gameStatus?.divineFavor ?? 0;
  const promotionCost = mythology?.promotionCost ?? gameStatus?.mythology?.promotionCost ?? 22;
  const promotedMyths = mythology?.myths ?? [];
  const candidates = mythology?.candidates ?? [];
  const isLoading = isLoadingStatus || isLoadingMythology;
  const error = statusError || mythologyError;
  const strongestCandidate = candidates[0] ?? null;

  const runPromotion = async (candidate: MythCandidate) => {
    setPromotingEventId(candidate.eventId);
    setActionMessage(null);

    try {
      const response: PromoteMythResponse = await promoteMyth({ eventId: candidate.eventId });

      if (response.success === false) {
        setActionMessage(`Failed: ${response.message || 'Myth promotion failed'}`);
        return;
      }

      setActionMessage(response.message || 'Myth promoted.');
      if (response.status) {
        setMythology(response.status);
      } else {
        await fetchMythology();
      }
      await refetchStatus();
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setPromotingEventId(null);
    }
  };

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading mythology..."
      errorPrefix="Error loading mythology"
      showEventLog={true}
      selectedRegionId={strongestCandidate?.regionId ?? undefined}
    >
      <PageHeader
        title="Mythology"
        subtitle={mythology?.summary ?? 'Promote major events into durable world myths'}
        icon="✧"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Living Myth Index</h2>
            <p className="mt-1 text-sm text-gray-400">
              Promote major feats, discoveries, disasters, and interventions into identity-shaping myths.
            </p>
          </div>
          <div className="text-sm font-semibold text-yellow-300">Favor {currentDivineFavor}</div>
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

        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Promoted</div>
            <div className="mt-1 text-sm font-semibold text-gray-200">{promotedMyths.length}</div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Candidates</div>
            <div className="mt-1 text-sm font-semibold text-gray-200">{candidates.length}</div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Influence</div>
            <div className="mt-1 text-sm text-gray-200">
              {mythology?.influenceSummary ?? 'No mythic influence yet.'}
            </div>
          </div>
        </div>
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-bold text-white">Promoted Myths</h2>
        {promotedMyths.length > 0 ? (
          promotedMyths.map(myth => <MythCard key={myth.id} myth={myth} />)
        ) : (
          <EmptyState
            title="No Promoted Myths"
            message="Promote a candidate event to make it part of the world's mythology."
            icon="✧"
          />
        )}
      </section>

      <section className="space-y-4">
        <h2 className="text-xl font-bold text-white">Candidate Legends</h2>
        {candidates.length > 0 ? (
          candidates.map(candidate => (
            <CandidateCard
              key={candidate.eventId}
              candidate={candidate}
              canPromote={currentDivineFavor >= promotionCost}
              isPromoting={promotingEventId === candidate.eventId}
              promotionCost={promotionCost}
              onPromote={candidateToPromote => {
                void runPromotion(candidateToPromote);
              }}
            />
          ))
        ) : (
          <EmptyState
            title="No Candidate Legends"
            message="Major events, discoveries, divine interventions, and hero milestones will appear here."
            icon="✧"
          />
        )}
      </section>
    </PageLayout>
  );
};

export default MythologyPage;

import React, { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  TemporalOmenActionResponse,
  getTemporalOmens,
  readTemporalOmen,
} from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import { useHeroes } from '../hooks/useHeroes';
import { useRegions } from '../hooks/useRegions';
import type {
  TemporalOmenEntry,
  TemporalOmenHorizon,
  TemporalOmenRiskBand,
  TemporalOmenStatusResponse,
  TemporalOmenTargetType,
  TemporalOmenTone,
} from '../entities/temporalOmen';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Temporal omen failed';
};

const riskTone = (riskBand: TemporalOmenRiskBand): string => {
  if (riskBand === 'dire') return 'text-red-300';
  if (riskBand === 'dangerous') return 'text-orange-300';
  if (riskBand === 'uncertain') return 'text-amber-300';
  return 'text-emerald-300';
};

const signalTone = (tone: TemporalOmenTone): string => {
  if (tone === 'bad') return 'bg-red-900/70 text-red-100';
  if (tone === 'warn') return 'bg-amber-900/70 text-amber-100';
  if (tone === 'good') return 'bg-emerald-900/70 text-emerald-100';
  return 'bg-gray-800 text-gray-200';
};

interface OmenCardProps {
  omen: TemporalOmenEntry;
}

const OmenCard: React.FC<OmenCardProps> = ({ omen }) => {
  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">
              {omen.horizonLabel} | {omen.targetName}
            </h2>
            <span className="rounded bg-indigo-900 px-2 py-0.5 text-xs font-semibold text-indigo-100">
              Year {omen.createdYear} to {omen.targetYear}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{omen.summary}</p>
          {omen.eventId && (
            <Link
              to={`/events/${omen.eventId}`}
              className="mt-2 inline-block text-sm text-blue-300 hover:text-blue-100"
            >
              View omen event
            </Link>
          )}
        </div>
        <div className="text-sm">
          <div className={`font-semibold ${riskTone(omen.riskBand)}`}>{omen.riskBand}</div>
          <div className="text-gray-500">Confidence {omen.confidence}</div>
        </div>
      </div>

      <div className="mt-4 flex flex-wrap gap-2">
        {omen.signals.map(signal => (
          <span
            key={`${omen.id}-${signal.label}`}
            className={`rounded px-2 py-1 text-xs ${signalTone(signal.tone)}`}
          >
            {signal.label}: {signal.value}
          </span>
        ))}
      </div>

      <div className="mt-5 space-y-3">
        {omen.predictions.map(prediction => (
          <div key={`${omen.id}-${prediction.type}`} className="rounded bg-gray-800/70 p-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div className="font-semibold text-gray-100">{prediction.title}</div>
                <div className="mt-1 text-sm text-gray-400">{prediction.summary}</div>
              </div>
              <div className="shrink-0 text-sm text-gray-300">
                <span className={riskTone(prediction.riskBand)}>{prediction.riskScore}/100</span>
                <span className="text-gray-500"> | {prediction.confidence}</span>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="mt-4 rounded bg-gray-900/70 px-3 py-2 text-xs text-gray-400">
        {omen.consistencyNote}
      </div>
    </article>
  );
};

const TemporalOmensPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const { regions, isLoading: isLoadingRegions, error: regionsError } = useRegions();
  const { heroes, isLoading: isLoadingHeroes, error: heroesError } = useHeroes();
  const [omenStatus, setOmenStatus] = useState<TemporalOmenStatusResponse | null>(null);
  const [omenError, setOmenError] = useState<string | null>(null);
  const [isLoadingOmens, setIsLoadingOmens] = useState(true);
  const [targetType, setTargetType] = useState<TemporalOmenTargetType>('world');
  const [targetId, setTargetId] = useState<string>('');
  const [horizon, setHorizon] = useState<TemporalOmenHorizon>('near');
  const [isReading, setIsReading] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchOmens = useCallback(async () => {
    try {
      setIsLoadingOmens(true);
      setOmenStatus(await getTemporalOmens());
      setOmenError(null);
    } catch (error: unknown) {
      setOmenError(getErrorMessage(error));
    } finally {
      setIsLoadingOmens(false);
    }
  }, []);

  useEffect(() => {
    void fetchOmens();
  }, [fetchOmens]);

  useEffect(() => {
    if (targetType === 'region' && !targetId && regions.length > 0) {
      setTargetId(regions[0].id);
    }
    if (targetType === 'hero' && !targetId && heroes.length > 0) {
      setTargetId(heroes[0].id);
    }
    if (targetType === 'world' && targetId) {
      setTargetId('');
    }
  }, [heroes, regions, targetId, targetType]);

  const targetOptions = omenStatus?.targetOptions ?? [];
  const horizonOptions = omenStatus?.horizonOptions ?? [];
  const selectedTargetOption = targetOptions.find(option => option.key === targetType);
  const selectedHorizon = horizonOptions.find(option => option.key === horizon);
  const currentDivineFavor = gameStatus?.divineFavor ?? 0;
  const selectedRegion = regions.find(region => region.id === targetId) ?? null;
  const estimatedCost = useMemo(() => {
    const targetCost = targetType === 'world' ? 8 : targetType === 'region' ? 3 : 0;
    const resonance = targetType === 'region' ? (selectedRegion?.divineResonance ?? 50) : 50;
    const modifier = Math.max(0.85, Math.min(1.15, 1 - (resonance - 50) * 0.003));
    return Math.max(1, Math.round(((selectedHorizon?.baseCost ?? 12) + targetCost) * modifier));
  }, [selectedHorizon?.baseCost, selectedRegion?.divineResonance, targetType]);
  const missingTarget =
    (targetType === 'region' && !targetId) || (targetType === 'hero' && !targetId);
  const canRead = !missingTarget && !isReading && currentDivineFavor >= estimatedCost;
  const latestOmen = omenStatus?.currentOmen ?? null;
  const recentOmens = omenStatus?.recentOmens ?? [];
  const isLoading = isLoadingStatus || isLoadingRegions || isLoadingHeroes || isLoadingOmens;
  const error = statusError || regionsError || heroesError || omenError;

  const handleReadOmen = async (event: FormEvent) => {
    event.preventDefault();
    if (missingTarget) return;

    setIsReading(true);
    setActionMessage(null);

    try {
      const response: TemporalOmenActionResponse = await readTemporalOmen({
        targetType,
        targetId: targetType === 'world' ? null : targetId,
        horizon,
      });

      if (response.success === false) {
        setActionMessage(`Failed: ${response.message || 'Temporal omen failed'}`);
        return;
      }

      setActionMessage(response.message || 'Temporal omen recorded.');
      if (response.status) {
        setOmenStatus(response.status);
      } else {
        await fetchOmens();
      }
      await refetchStatus();
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setIsReading(false);
    }
  };

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading omens..."
      errorPrefix="Error loading omens"
      showEventLog={true}
      selectedRegionId={targetType === 'region' ? targetId : undefined}
      selectedHeroId={targetType === 'hero' ? targetId : undefined}
    >
      <PageHeader
        title="Temporal Omens"
        subtitle={omenStatus?.summary ?? 'Preview future pressure without rewriting history'}
        icon="◷"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Read Omen</h2>
            <p className="mt-1 text-sm text-gray-400">
              Read current trajectories as forecasts, delayed signs, and risk signals.
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

        <form
          onSubmit={handleReadOmen}
          className="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[180px_1fr_220px_auto]"
        >
          <select
            value={targetType}
            onChange={event => {
              setTargetType(event.target.value as TemporalOmenTargetType);
              setTargetId('');
            }}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {targetOptions.map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>

          {targetType === 'world' && (
            <div className="rounded border border-gray-700 bg-gray-800 px-3 py-2 text-gray-300">
              The World
            </div>
          )}
          {targetType === 'region' && (
            <select
              value={targetId}
              onChange={event => setTargetId(event.target.value)}
              className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
            >
              {regions.map(region => (
                <option key={region.id} value={region.id}>
                  {region.name} | {region.status}
                </option>
              ))}
            </select>
          )}
          {targetType === 'hero' && (
            <select
              value={targetId}
              onChange={event => setTargetId(event.target.value)}
              className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
            >
              {heroes.map(hero => (
                <option key={hero.id} value={hero.id}>
                  {hero.name} | {hero.status}
                </option>
              ))}
            </select>
          )}

          <select
            value={horizon}
            onChange={event => setHorizon(event.target.value as TemporalOmenHorizon)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {horizonOptions.map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>

          <button
            type="submit"
            disabled={!canRead}
            className={`rounded bg-indigo-500 px-5 py-2 text-sm font-bold text-white transition-colors hover:bg-indigo-400 ${
              canRead ? '' : 'cursor-not-allowed opacity-50'
            }`}
          >
            Read Omen
            <span className="block text-xs font-normal">Cost: {estimatedCost}</span>
          </button>
        </form>

        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Target</div>
            <div className="mt-1 text-sm text-gray-200">
              {selectedTargetOption?.summary ?? 'Choose an omen target.'}
            </div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Horizon</div>
            <div className="mt-1 text-sm text-gray-200">
              {selectedHorizon?.summary ?? 'Choose a time horizon.'}
            </div>
          </div>
        </div>
      </section>

      {latestOmen ? (
        <OmenCard omen={latestOmen} />
      ) : (
        <EmptyState
          title="No Temporal Omens"
          message="No future thread has been read yet."
          icon="◷"
        />
      )}

      {recentOmens.length > 1 && (
        <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
          <h2 className="text-xl font-bold text-white">Recent Omen History</h2>
          <div className="mt-4 space-y-3">
            {recentOmens.slice(1).map(omen => (
              <div
                key={omen.id}
                className="flex flex-col gap-2 rounded bg-gray-800 px-3 py-3 md:flex-row md:items-center md:justify-between"
              >
                <div>
                  <div className="font-semibold text-gray-100">
                    {omen.horizonLabel} for {omen.targetName}
                  </div>
                  <div className="text-sm text-gray-400">{omen.summary}</div>
                </div>
                {omen.eventId && (
                  <Link
                    to={`/events/${omen.eventId}`}
                    className="text-sm text-blue-300 hover:text-blue-100"
                  >
                    Event
                  </Link>
                )}
              </div>
            ))}
          </div>
        </section>
      )}
    </PageLayout>
  );
};

export default TemporalOmensPage;

import React, { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  MagicResearchResponse,
  getMagicDiscovery,
  researchMagic,
} from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import { useHeroes } from '../hooks/useHeroes';
import { useRegions } from '../hooks/useRegions';
import type {
  MagicDiscoveryBettingHook,
  MagicDiscoveryPath,
  MagicDiscoveryPathStatus,
  MagicDiscoveryStatusResponse,
  MagicDiscoverySuggestedTarget,
  MagicDiscoveryTargetType,
} from '../entities/magicDiscovery';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Magic research failed';
};

const statusTone = (status: MagicDiscoveryPathStatus): string => {
  if (status === 'known') return 'bg-emerald-900/70 text-emerald-100';
  if (status === 'emerging') return 'bg-amber-900/70 text-amber-100';
  return 'bg-gray-800 text-gray-300';
};

const labelFromKey = (value: string): string => {
  return value
    .split('_')
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');
};

interface MagicPathCardProps {
  path: MagicDiscoveryPath;
}

const MagicPathCard: React.FC<MagicPathCardProps> = ({ path }) => {
  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">{path.label}</h2>
            <span className={`rounded px-2 py-0.5 text-xs font-semibold ${statusTone(path.status)}`}>
              {labelFromKey(path.status)}
            </span>
            <span className="rounded bg-gray-800 px-2 py-0.5 text-xs text-gray-300">
              {path.domain}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{path.visibilitySummary}</p>
        </div>
        <div className="text-right text-sm">
          <div className="font-semibold text-cyan-200">{path.progress}% progress</div>
          <div className="text-gray-500">Evidence {path.evidenceScore}</div>
        </div>
      </div>

      <progress
        className="mt-4 h-2 w-full overflow-hidden rounded bg-gray-800 accent-cyan-400"
        max={100}
        value={Math.min(100, Math.max(0, path.progress))}
      />

      {path.lastTarget && (
        <div className="mt-4 rounded bg-gray-800 px-3 py-2 text-sm text-gray-300">
          Last researched through {path.lastTarget.name}
          {path.lastResearchedYear ? ` in year ${path.lastResearchedYear}` : ''}.
        </div>
      )}

      <div className="mt-4 flex flex-wrap gap-2">
        {path.signals.length === 0 ? (
          <span className="text-sm text-gray-500">No evidence signals recorded yet.</span>
        ) : (
          path.signals.map(signal => (
            <span
              key={`${path.key}-${signal.label}-${signal.value}`}
              className="rounded bg-gray-800 px-2 py-1 text-xs text-gray-200"
              title={signal.summary}
            >
              {signal.label}: {signal.value}
            </span>
          ))
        )}
      </div>

      {path.history.length > 0 && (
        <div className="mt-5 space-y-2">
          <div className="text-sm font-semibold uppercase text-gray-400">Research History</div>
          {path.history.slice(0, 3).map(entry => (
            <div
              key={`${path.key}-${entry.eventId}`}
              className="flex flex-col gap-2 rounded bg-gray-800/70 px-3 py-3 sm:flex-row sm:items-center sm:justify-between"
            >
              <div>
                <div className="font-semibold text-gray-100">{entry.title}</div>
                <div className="text-sm text-gray-400">{entry.summary}</div>
              </div>
              <Link
                to={`/events/${entry.eventId}`}
                className="text-sm text-blue-300 hover:text-blue-100"
              >
                Event
              </Link>
            </div>
          ))}
        </div>
      )}
    </article>
  );
};

interface SuggestedTargetListProps {
  targets: MagicDiscoverySuggestedTarget[];
  onSelect: (target: MagicDiscoverySuggestedTarget) => void;
}

const SuggestedTargetList: React.FC<SuggestedTargetListProps> = ({ targets, onSelect }) => {
  if (targets.length === 0) {
    return (
      <EmptyState
        title="No Suggestions"
        message="No high-signal magic research targets are visible yet."
        icon="✦"
      />
    );
  }

  return (
    <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <h2 className="text-xl font-bold text-white">Suggested Targets</h2>
      <div className="mt-4 space-y-3">
        {targets.map(target => (
          <button
            key={`${target.type}-${target.id}-${target.bestPath}`}
            type="button"
            onClick={() => onSelect(target)}
            className="w-full rounded border border-gray-700 bg-gray-800 px-3 py-3 text-left transition-colors hover:border-cyan-500 hover:bg-gray-700"
          >
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div className="font-semibold text-gray-100">{target.name}</div>
                <div className="text-sm text-gray-400">{target.reason}</div>
              </div>
              <div className="text-sm text-cyan-200">{labelFromKey(target.bestPath)}</div>
            </div>
          </button>
        ))}
      </div>
    </section>
  );
};

interface BettingHooksProps {
  hooks: MagicDiscoveryBettingHook[];
}

const BettingHooks: React.FC<BettingHooksProps> = ({ hooks }) => {
  return (
    <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <h2 className="text-xl font-bold text-white">Betting Hooks</h2>
          <p className="mt-1 text-sm text-gray-400">
            Emerging and known paths seed speculation using existing betting markets.
          </p>
        </div>
        <Link to="/betting" className="text-sm font-semibold text-blue-300 hover:text-blue-100">
          Open Betting
        </Link>
      </div>

      {hooks.length === 0 ? (
        <div className="mt-4 text-sm text-gray-500">
          Research a path until it emerges before magic speculation appears.
        </div>
      ) : (
        <div className="mt-4 space-y-3">
          {hooks.map(hook => (
            <div key={hook.id} className="rounded bg-gray-800 px-3 py-3">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                <div>
                  <div className="font-semibold text-gray-100">{hook.title}</div>
                  <div className="text-sm text-gray-400">{hook.summary}</div>
                </div>
                <div className="text-sm text-amber-200">{labelFromKey(hook.betType)}</div>
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
};

const MagicDiscoveryPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const {
    regions,
    landmarks,
    isLoading: isLoadingRegions,
    error: regionsError,
    refetch: refetchRegions,
  } = useRegions();
  const {
    heroes,
    isLoading: isLoadingHeroes,
    error: heroesError,
    refetch: refetchHeroes,
  } = useHeroes();
  const [magicStatus, setMagicStatus] = useState<MagicDiscoveryStatusResponse | null>(null);
  const [magicError, setMagicError] = useState<string | null>(null);
  const [isLoadingMagic, setIsLoadingMagic] = useState(true);
  const [targetType, setTargetType] = useState<MagicDiscoveryTargetType>('region');
  const [targetId, setTargetId] = useState('');
  const [path, setPath] = useState('auto');
  const [isResearching, setIsResearching] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchMagic = useCallback(async () => {
    try {
      setIsLoadingMagic(true);
      setMagicStatus(await getMagicDiscovery());
      setMagicError(null);
    } catch (error: unknown) {
      setMagicError(getErrorMessage(error));
    } finally {
      setIsLoadingMagic(false);
    }
  }, []);

  useEffect(() => {
    void fetchMagic();
  }, [fetchMagic]);

  const selectableTargets = useMemo(() => {
    if (targetType === 'hero') {
      return heroes.map(hero => ({
        id: hero.id,
        name: hero.name,
        detail: `${hero.role}, level ${hero.level ?? 0}`,
      }));
    }

    if (targetType === 'landmark') {
      return landmarks.map(landmark => ({
        id: landmark.id,
        name: landmark.name,
        detail: `${landmark.type}, magic ${landmark.magicLevel}`,
      }));
    }

    return regions.map(region => ({
      id: region.id,
      name: region.name,
      detail: `magic ${region.magicAffinity}, ${region.status}`,
    }));
  }, [heroes, landmarks, regions, targetType]);

  useEffect(() => {
    if (!selectableTargets.some(target => target.id === targetId)) {
      setTargetId(selectableTargets[0]?.id ?? '');
    }
  }, [selectableTargets, targetId]);

  const selectedTarget = selectableTargets.find(target => target.id === targetId) ?? null;
  const currentDivineFavor = gameStatus?.divineFavor ?? 0;
  const researchCost = magicStatus?.researchCost ?? gameStatus?.magicDiscovery?.researchCost ?? 18;
  const canResearch = Boolean(targetId) && !isResearching && currentDivineFavor >= researchCost;
  const pathOptions = magicStatus?.pathOptions ?? [];
  const paths = magicStatus?.paths ?? [];
  const bettingHooks = magicStatus?.bettingHooks ?? [];
  const suggestedTargets = magicStatus?.suggestedTargets ?? [];
  const isLoading = isLoadingStatus || isLoadingRegions || isLoadingHeroes || isLoadingMagic;
  const error = statusError || regionsError || heroesError || magicError;

  const selectedRegionId =
    targetType === 'region'
      ? targetId
      : targetType === 'hero'
        ? heroes.find(hero => hero.id === targetId)?.regionId
        : landmarks.find(landmark => landmark.id === targetId)?.regionId;

  const runMagicResearch = async (event: FormEvent) => {
    event.preventDefault();
    if (!targetId) return;

    setIsResearching(true);
    setActionMessage(null);

    try {
      const response: MagicResearchResponse = await researchMagic({
        targetType,
        targetId,
        path,
      });

      if (response.success === false) {
        setActionMessage(`Failed: ${response.message || 'Magic research failed'}`);
        return;
      }

      setActionMessage(response.message || 'Magic research advanced.');
      if (response.status) {
        setMagicStatus(response.status);
      } else {
        await fetchMagic();
      }
      await Promise.all([refetchStatus(), refetchRegions(), refetchHeroes()]);
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setIsResearching(false);
    }
  };

  const selectSuggestedTarget = (target: MagicDiscoverySuggestedTarget) => {
    setTargetType(target.type);
    setTargetId(target.id);
    setPath(target.bestPath || 'auto');
  };

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading magic discovery..."
      errorPrefix="Error loading magic discovery"
      showEventLog={true}
      selectedRegionId={selectedRegionId}
      selectedHeroId={targetType === 'hero' ? targetId : undefined}
    >
      <PageHeader
        title="Magic Discovery"
        subtitle={magicStatus?.summary ?? 'Track hidden, emerging, and known magical systems'}
        icon="✦"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Research Magic</h2>
            <p className="mt-1 text-sm text-gray-400">
              Spend favor to turn live world evidence into magical paths, events, traits, and speculation.
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
          onSubmit={runMagicResearch}
          className="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[180px_1fr_240px_auto]"
        >
          <select
            value={targetType}
            onChange={event => {
              setTargetType(event.target.value as MagicDiscoveryTargetType);
              setTargetId('');
            }}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {(magicStatus?.targetOptions ?? [
              { key: 'region', label: 'Region' },
              { key: 'hero', label: 'Hero' },
              { key: 'landmark', label: 'Landmark' },
            ]).map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>

          <select
            value={targetId}
            onChange={event => setTargetId(event.target.value)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {selectableTargets.map(target => (
              <option key={target.id} value={target.id}>
                {target.name} | {target.detail}
              </option>
            ))}
          </select>

          <select
            value={path}
            onChange={event => setPath(event.target.value)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            <option value="auto">Auto Match</option>
            {pathOptions.map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>

          <button
            type="submit"
            disabled={!canResearch}
            className={`rounded bg-cyan-500 px-5 py-2 text-sm font-bold text-gray-950 transition-colors hover:bg-cyan-400 ${
              canResearch ? '' : 'cursor-not-allowed opacity-50'
            }`}
          >
            Research Magic
            <span className="block text-xs font-normal">Cost: {researchCost}</span>
          </button>
        </form>

        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Target</div>
            <div className="mt-1 text-sm text-gray-200">
              {selectedTarget ? `${selectedTarget.name}: ${selectedTarget.detail}` : 'No target available.'}
            </div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Path</div>
            <div className="mt-1 text-sm text-gray-200">
              {path === 'auto'
                ? 'Use the strongest evidence match.'
                : (pathOptions.find(option => option.key === path)?.summary ?? labelFromKey(path))}
            </div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Year</div>
            <div className="mt-1 text-sm text-gray-200">
              {magicStatus?.currentYear ?? gameStatus?.currentYear ?? 1}
            </div>
          </div>
        </div>
      </section>

      {paths.length > 0 ? (
        <div className="space-y-4">
          {paths.map(pathEntry => (
            <MagicPathCard key={pathEntry.key} path={pathEntry} />
          ))}
        </div>
      ) : (
        <EmptyState
          title="No Magic Paths"
          message="Magic discovery paths have not been initialized yet."
          icon="✦"
        />
      )}

      <BettingHooks hooks={bettingHooks} />

      <SuggestedTargetList targets={suggestedTargets} onSelect={selectSuggestedTarget} />
    </PageLayout>
  );
};

export default MagicDiscoveryPage;

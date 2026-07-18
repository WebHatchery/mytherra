import React, { FormEvent, useCallback, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArtifactActionResponse,
  createArtifact,
  empowerArtifact,
  getArtifacts,
  stabilizeArtifact,
  transferArtifact,
} from '../api/apiService';
import type { ArtifactFocus, ArtifactStatusResponse, DivineArtifact } from '../entities/artifact';
import type { Hero } from '../entities/hero';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import { useHeroes } from '../hooks/useHeroes';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Artifact action failed';
};

const actionMessageFrom = (response: ArtifactActionResponse): string => {
  if (response.success === false) {
    return `Failed: ${response.message || 'Artifact action failed'}`;
  }

  const riskSummary =
    response.riskOutcome && response.riskOutcome.type !== 'none'
      ? ` ${response.riskOutcome.summary}`
      : '';

  return `${response.message || 'Artifact action completed.'}${riskSummary}`;
};

const riskTone = (riskTier: DivineArtifact['riskTier']): string => {
  if (riskTier === 'critical') return 'text-red-300';
  if (riskTier === 'high') return 'text-orange-300';
  if (riskTier === 'rising') return 'text-amber-300';
  return 'text-emerald-300';
};

const statusTone = (status: DivineArtifact['status']): string => {
  if (status === 'corrupted') return 'bg-purple-800 text-purple-100';
  if (status === 'lost') return 'bg-red-800 text-red-100';
  return 'bg-emerald-800 text-emerald-100';
};

interface ArtifactCardProps {
  artifact: DivineArtifact;
  heroes: Hero[];
  transferTargetId: string;
  currentDivineFavor: number;
  loadingAction: Record<string, boolean>;
  onTransferTargetChange: (artifactId: string, heroId: string) => void;
  onEmpower: (artifact: DivineArtifact) => void;
  onStabilize: (artifact: DivineArtifact) => void;
  onTransferToHero: (artifact: DivineArtifact) => void;
  onUnbind: (artifact: DivineArtifact) => void;
}

const ArtifactCard: React.FC<ArtifactCardProps> = ({
  artifact,
  heroes,
  transferTargetId,
  currentDivineFavor,
  loadingAction,
  onTransferTargetChange,
  onEmpower,
  onStabilize,
  onTransferToHero,
  onUnbind,
}) => {
  const empowerKey = `empower-${artifact.id}`;
  const stabilizeKey = `stabilize-${artifact.id}`;
  const transferKey = `transfer-${artifact.id}`;
  const unbindKey = `unbind-${artifact.id}`;
  const selectedHeroId = transferTargetId || heroes[0]?.id || '';
  const canEmpower = currentDivineFavor >= artifact.empowerCost && !loadingAction[empowerKey];
  const canStabilize = currentDivineFavor >= artifact.stabilizeCost && !loadingAction[stabilizeKey];
  const canTransfer =
    selectedHeroId.length > 0 &&
    currentDivineFavor >= 8 &&
    !loadingAction[transferKey] &&
    heroes.length > 0;
  const canUnbind = currentDivineFavor >= 8 && !loadingAction[unbindKey];

  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">{artifact.name}</h2>
            <span
              className={`rounded px-2 py-0.5 text-xs font-semibold ${statusTone(artifact.status)}`}
            >
              {artifact.status}
            </span>
            {artifact.seeded && (
              <span className="rounded bg-violet-900 px-2 py-0.5 text-xs font-semibold text-violet-100">
                starter relic
              </span>
            )}
          </div>
          <p className="mt-1 text-sm text-gray-300">{artifact.summary}</p>
          {artifact.originSummary && (
            <p className="mt-1 text-xs text-gray-500">{artifact.originSummary}</p>
          )}
        </div>
        <div className="text-sm font-semibold text-cyan-300">Power {artifact.powerLevel}</div>
      </div>

      <div className="mt-4 grid grid-cols-2 gap-3 text-sm md:grid-cols-4">
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Focus</div>
          <div className="mt-1 font-semibold text-gray-100">{artifact.focusLabel}</div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Owner</div>
          <div className="mt-1 font-semibold text-gray-100">{artifact.ownerName}</div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Instability</div>
          <div className={`mt-1 font-semibold ${riskTone(artifact.riskTier)}`}>
            {artifact.instability}
          </div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Corruption</div>
          <div className={`mt-1 font-semibold ${riskTone(artifact.riskTier)}`}>
            {artifact.corruption}
          </div>
        </div>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
        <button
          type="button"
          onClick={() => onEmpower(artifact)}
          disabled={!canEmpower}
          className={`rounded bg-yellow-500 px-4 py-2 text-sm font-bold text-gray-900 transition-colors hover:bg-yellow-400 ${
            canEmpower ? '' : 'cursor-not-allowed opacity-50'
          }`}
        >
          Empower
          <span className="block text-xs font-normal">Cost: {artifact.empowerCost}</span>
        </button>
        <button
          type="button"
          onClick={() => onStabilize(artifact)}
          disabled={!canStabilize}
          className={`rounded bg-cyan-600 px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-cyan-500 ${
            canStabilize ? '' : 'cursor-not-allowed opacity-50'
          }`}
        >
          Stabilize
          <span className="block text-xs font-normal">Cost: {artifact.stabilizeCost}</span>
        </button>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_auto]">
        <select
          value={selectedHeroId}
          onChange={event => onTransferTargetChange(artifact.id, event.target.value)}
          className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-sm text-gray-100"
        >
          {heroes.length === 0 && <option value="">No heroes available</option>}
          {heroes.map(hero => (
            <option key={hero.id} value={hero.id}>
              {hero.name}
            </option>
          ))}
        </select>
        <button
          type="button"
          onClick={() => onTransferToHero(artifact)}
          disabled={!canTransfer}
          className={`rounded bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500 ${
            canTransfer ? '' : 'cursor-not-allowed opacity-50'
          }`}
        >
          Transfer
        </button>
        <button
          type="button"
          onClick={() => onUnbind(artifact)}
          disabled={!canUnbind}
          className={`rounded bg-gray-700 px-4 py-2 text-sm font-semibold text-gray-100 hover:bg-gray-600 ${
            canUnbind ? '' : 'cursor-not-allowed opacity-50'
          }`}
        >
          Unbind
        </button>
      </div>

      <div className="mt-5 border-t border-[#2f334d] pt-4">
        <div className="mb-2 text-xs font-semibold uppercase text-gray-500">Artifact History</div>
        {artifact.history.length > 0 ? (
          <div className="space-y-2">
            {artifact.history.slice(0, 4).map(entry => (
              <div key={`${artifact.id}-${entry.eventId}`} className="text-sm">
                <Link to={`/events/${entry.eventId}`} className="text-blue-300 hover:text-blue-100">
                  {entry.title}
                </Link>
                <span className="text-gray-500"> | Year {entry.year ?? 'unknown'}</span>
                <div className="mt-1 text-xs text-gray-400">{entry.summary}</div>
              </div>
            ))}
          </div>
        ) : (
          <div className="text-xs text-gray-500">No artifact events recorded.</div>
        )}
      </div>
    </article>
  );
};

const ArtifactsPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const { heroes, isLoading: isLoadingHeroes, error: heroesError } = useHeroes();
  const [artifactStatus, setArtifactStatus] = useState<ArtifactStatusResponse | null>(null);
  const [artifactError, setArtifactError] = useState<string | null>(null);
  const [isLoadingArtifacts, setIsLoadingArtifacts] = useState(true);
  const [artifactName, setArtifactName] = useState('');
  const [artifactFocus, setArtifactFocus] = useState<ArtifactFocus>('protection');
  const [transferTargets, setTransferTargets] = useState<Record<string, string>>({});
  const [loadingAction, setLoadingAction] = useState<Record<string, boolean>>({});
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchArtifacts = useCallback(async () => {
    try {
      setIsLoadingArtifacts(true);
      setArtifactStatus(await getArtifacts());
      setArtifactError(null);
    } catch (error: unknown) {
      setArtifactError(getErrorMessage(error));
    } finally {
      setIsLoadingArtifacts(false);
    }
  }, []);

  useEffect(() => {
    void fetchArtifacts();
  }, [fetchArtifacts]);

  const runArtifactAction = async (key: string, action: () => Promise<ArtifactActionResponse>) => {
    setLoadingAction(prev => ({ ...prev, [key]: true }));
    setActionMessage(null);

    try {
      const response = await action();
      setActionMessage(actionMessageFrom(response));
      if (response.success !== false) {
        if (response.status) {
          setArtifactStatus(response.status);
        } else {
          await fetchArtifacts();
        }
        await refetchStatus();
      }
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setLoadingAction(prev => ({ ...prev, [key]: false }));
    }
  };

  const handleCreate = (event: FormEvent) => {
    event.preventDefault();
    void runArtifactAction('create', () =>
      createArtifact({
        name: artifactName.trim(),
        focus: artifactFocus,
        targetType: 'unbound',
        targetId: null,
      })
    ).then(() => {
      setArtifactName('');
    });
  };

  const handleTransferTargetChange = (artifactId: string, heroId: string) => {
    setTransferTargets(prev => ({ ...prev, [artifactId]: heroId }));
  };

  const handleTransferToHero = (artifact: DivineArtifact) => {
    const heroId = transferTargets[artifact.id] || heroes[0]?.id || '';
    if (!heroId) return;

    void runArtifactAction(`transfer-${artifact.id}`, () =>
      transferArtifact(artifact.id, { targetType: 'hero', targetId: heroId })
    );
  };

  const handleUnbind = (artifact: DivineArtifact) => {
    void runArtifactAction(`unbind-${artifact.id}`, () =>
      transferArtifact(artifact.id, { targetType: 'unbound', targetId: null })
    );
  };

  const isLoading = isLoadingStatus || isLoadingArtifacts || isLoadingHeroes;
  const error = statusError || artifactError || heroesError;
  const currentDivineFavor = gameStatus?.divineFavor ?? 0;
  const focusOptions = artifactStatus?.focusOptions ?? [];
  const artifacts = artifactStatus?.artifacts ?? [];
  const canCreate =
    artifactName.trim().length >= 2 &&
    currentDivineFavor >= (artifactStatus?.creationCost ?? 40) &&
    artifacts.length < (artifactStatus?.artifactLimit ?? 9) &&
    !loadingAction.create;

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading artifacts..."
      errorPrefix="Error loading artifacts"
      showEventLog={true}
    >
      <PageHeader
        title="Divine Artifacts"
        subtitle={`${artifacts.length}/${artifactStatus?.artifactLimit ?? 9} artifacts shaped`}
        icon="◆"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Forge Artifact</h2>
            {artifactStatus?.summary && (
              <p className="mt-1 text-sm text-gray-400">{artifactStatus.summary}</p>
            )}
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
          onSubmit={handleCreate}
          className="mt-4 grid grid-cols-1 gap-3 lg:grid-cols-[1fr_220px_auto]"
        >
          <input
            type="text"
            value={artifactName}
            onChange={event => setArtifactName(event.target.value)}
            placeholder="Artifact name"
            maxLength={60}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100 placeholder:text-gray-500"
          />
          <select
            value={artifactFocus}
            onChange={event => setArtifactFocus(event.target.value as ArtifactFocus)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {focusOptions.map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>
          <button
            type="submit"
            disabled={!canCreate}
            className={`rounded bg-yellow-500 px-5 py-2 text-sm font-bold text-gray-900 transition-colors hover:bg-yellow-400 ${
              canCreate ? '' : 'cursor-not-allowed opacity-50'
            }`}
          >
            Create
            <span className="block text-xs font-normal">
              Cost: {artifactStatus?.creationCost ?? 40}
            </span>
          </button>
        </form>
      </section>

      {artifacts.length === 0 ? (
        <EmptyState
          title="No Artifacts"
          message="No divine artifacts have been shaped yet."
          icon="◆"
        />
      ) : (
        <div className="grid grid-cols-1 gap-5">
          {artifacts.map(artifact => (
            <ArtifactCard
              key={artifact.id}
              artifact={artifact}
              heroes={heroes}
              transferTargetId={transferTargets[artifact.id] ?? ''}
              currentDivineFavor={currentDivineFavor}
              loadingAction={loadingAction}
              onTransferTargetChange={handleTransferTargetChange}
              onEmpower={item =>
                void runArtifactAction(`empower-${item.id}`, () => empowerArtifact(item.id))
              }
              onStabilize={item =>
                void runArtifactAction(`stabilize-${item.id}`, () => stabilizeArtifact(item.id))
              }
              onTransferToHero={handleTransferToHero}
              onUnbind={handleUnbind}
            />
          ))}
        </div>
      )}
    </PageLayout>
  );
};

export default ArtifactsPage;

import React, { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  WeatherActionResponse,
  getWeatherStatus,
  nudgeWeather,
} from '../api/apiService';
import EmptyState from '../components/EmptyState';
import PageHeader from '../components/PageHeader';
import PageLayout from '../components/PageLayout';
import { useGameStatus } from '../hooks/useGameStatus';
import { useRegions } from '../hooks/useRegions';
import type {
  WeatherChangeDelta,
  WeatherEntityChange,
  WeatherInfluenceEntry,
  WeatherIntensityKey,
  WeatherPatternKey,
  WeatherStatusResponse,
} from '../entities/weather';

const getErrorMessage = (error: unknown): string => {
  if (error instanceof Error) return error.message;
  if (typeof error === 'string') return error;
  return 'Weather action failed';
};

const formatSigned = (value: number): string => {
  return value > 0 ? `+${value}` : String(value);
};

const formatChange = ([key, delta]: [string, WeatherChangeDelta]): string => {
  if (typeof delta.delta === 'number') {
    return `${key}: ${formatSigned(delta.delta)}`;
  }

  return `${key}: ${String(delta.before)} -> ${String(delta.after)}`;
};

const renderChangeList = (change: Record<string, WeatherChangeDelta>) => {
  const entries = Object.entries(change).filter(([, delta]) => delta.before !== delta.after);
  if (entries.length === 0) {
    return <span className="text-gray-500">No visible stat shift</span>;
  }

  return entries.map(entry => (
    <span key={entry[0]} className="rounded bg-gray-800 px-2 py-1 text-xs text-gray-300">
      {formatChange(entry)}
    </span>
  ));
};

interface EntityChangesProps {
  title: string;
  items: WeatherEntityChange[];
  empty: string;
}

const EntityChanges: React.FC<EntityChangesProps> = ({ title, items, empty }) => {
  return (
    <div className="rounded border border-[#2f334d] bg-gray-900/50 p-4">
      <div className="mb-3 text-sm font-semibold uppercase text-gray-400">{title}</div>
      {items.length === 0 ? (
        <div className="text-sm text-gray-500">{empty}</div>
      ) : (
        <div className="space-y-3">
          {items.map(item => (
            <div key={item.id} className="rounded bg-gray-800/70 p-3">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <div className="font-semibold text-gray-100">{item.name}</div>
                  <div className="text-xs text-gray-500">{item.status}</div>
                </div>
                <div className="flex flex-wrap gap-2">{renderChangeList(item.change)}</div>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

interface WeatherInfluenceCardProps {
  influence: WeatherInfluenceEntry;
}

const WeatherInfluenceCard: React.FC<WeatherInfluenceCardProps> = ({ influence }) => {
  const regionChanges = renderChangeList(influence.regionChange);

  return (
    <article className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
        <div>
          <div className="flex flex-wrap items-center gap-2">
            <h2 className="text-lg font-bold text-white">
              {influence.intensityLabel} {influence.patternLabel}
            </h2>
            <span className="rounded bg-cyan-900 px-2 py-0.5 text-xs font-semibold text-cyan-100">
              Year {influence.year}
            </span>
          </div>
          <p className="mt-1 text-sm text-gray-300">{influence.summary}</p>
          {influence.eventId && (
            <Link
              to={`/events/${influence.eventId}`}
              className="mt-2 inline-block text-sm text-blue-300 hover:text-blue-100"
            >
              View weather event
            </Link>
          )}
        </div>
        <div className="text-sm font-semibold text-yellow-300">Cost {influence.cost}</div>
      </div>

      <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-2">
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Travel</div>
          <div className="mt-1 text-sm text-gray-200">{influence.travelEffect}</div>
        </div>
        <div className="rounded bg-gray-800 px-3 py-2">
          <div className="text-xs uppercase text-gray-500">Conflict</div>
          <div className="mt-1 text-sm text-gray-200">{influence.conflictEffect}</div>
        </div>
      </div>

      <div className="mt-4 rounded border border-[#2f334d] bg-gray-900/50 p-4">
        <div className="mb-3 text-sm font-semibold uppercase text-gray-400">
          {influence.regionName}
        </div>
        <div className="flex flex-wrap gap-2">{regionChanges}</div>
        {influence.riskOutcome.type !== 'none' && (
          <div className="mt-3 rounded bg-amber-900/70 px-3 py-2 text-sm text-amber-100">
            {influence.riskOutcome.summary}
          </div>
        )}
      </div>

      {(influence.chains ?? []).length > 0 && (
        <div className="mt-4 rounded border border-[#2f334d] bg-gray-900/50 p-4">
          <div className="mb-3 text-sm font-semibold uppercase text-gray-400">
            Consequence Chains
          </div>
          <div className="space-y-3">
            {(influence.chains ?? []).slice(0, 3).map(chain => (
              <div key={chain.id} className="text-sm">
                <div className="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                  <span className="font-semibold text-gray-200">{chain.title}</span>
                  <span className="text-xs text-amber-300">
                    {chain.status} {chain.step}/{chain.maxSteps}
                    {chain.nextYear ? ` • year ${chain.nextYear}` : ''}
                  </span>
                </div>
                <div className="mt-1 text-gray-500">{chain.latestSummary}</div>
                <div className="mt-1">
                  {chain.eventIds.slice(-2).map(eventId => (
                    <Link
                      key={eventId}
                      to={`/events/${eventId}`}
                      className="mr-2 text-xs text-blue-300 hover:text-blue-100"
                    >
                      Event {eventId}
                    </Link>
                  ))}
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      <div className="mt-4 grid grid-cols-1 gap-4 xl:grid-cols-2">
        <EntityChanges
          title="Settlement Effects"
          items={influence.settlementChanges}
          empty="No settlements were directly shifted by this nudge."
        />
        <EntityChanges
          title="Resource Effects"
          items={influence.resourceChanges}
          empty="No resource nodes were directly shifted by this nudge."
        />
      </div>
    </article>
  );
};

const WeatherPage: React.FC = () => {
  const {
    gameStatus,
    isLoading: isLoadingStatus,
    error: statusError,
    refetch: refetchStatus,
  } = useGameStatus();
  const {
    regions,
    isLoading: isLoadingRegions,
    error: regionsError,
    refetch: refetchRegions,
  } = useRegions();
  const [weatherStatus, setWeatherStatus] = useState<WeatherStatusResponse | null>(null);
  const [weatherError, setWeatherError] = useState<string | null>(null);
  const [isLoadingWeather, setIsLoadingWeather] = useState(true);
  const [selectedRegionId, setSelectedRegionId] = useState('');
  const [pattern, setPattern] = useState<WeatherPatternKey>('gentle_rains');
  const [intensity, setIntensity] = useState<WeatherIntensityKey>('minor');
  const [isNudging, setIsNudging] = useState(false);
  const [actionMessage, setActionMessage] = useState<string | null>(null);

  const fetchWeather = useCallback(async () => {
    try {
      setIsLoadingWeather(true);
      const response = await getWeatherStatus();
      setWeatherStatus(response);
      setWeatherError(null);
    } catch (error: unknown) {
      setWeatherError(getErrorMessage(error));
    } finally {
      setIsLoadingWeather(false);
    }
  }, []);

  useEffect(() => {
    void fetchWeather();
  }, [fetchWeather]);

  useEffect(() => {
    if (!selectedRegionId && regions.length > 0) {
      setSelectedRegionId(regions[0].id);
    }
  }, [regions, selectedRegionId]);

  const selectedRegion = useMemo(
    () => regions.find(region => region.id === selectedRegionId) ?? null,
    [regions, selectedRegionId]
  );
  const patternOptions = weatherStatus?.patternOptions ?? [];
  const intensityOptions = weatherStatus?.intensityOptions ?? [];
  const selectedPattern = patternOptions.find(option => option.key === pattern);
  const selectedIntensity = intensityOptions.find(option => option.key === intensity);
  const currentDivineFavor = gameStatus?.divineFavor ?? 0;
  const estimatedCost = useMemo(() => {
    const resonance = selectedRegion?.divineResonance ?? 50;
    const resonanceModifier = Math.max(0.82, Math.min(1.18, 1 - (resonance - 50) * 0.0035));
    return Math.max(
      1,
      Math.round(
        (selectedPattern?.baseCost ?? 14) *
          (selectedIntensity?.costMultiplier ?? 1) *
          resonanceModifier
      )
    );
  }, [selectedIntensity?.costMultiplier, selectedPattern?.baseCost, selectedRegion?.divineResonance]);
  const canNudge = Boolean(selectedRegionId) && !isNudging && currentDivineFavor >= estimatedCost;
  const latestInfluence = weatherStatus?.currentInfluence ?? null;
  const recentInfluences = weatherStatus?.recentInfluences ?? [];
  const isLoading = isLoadingStatus || isLoadingRegions || isLoadingWeather;
  const error = statusError || regionsError || weatherError;

  const runWeatherNudge = async (event: FormEvent) => {
    event.preventDefault();
    if (!selectedRegionId) return;

    setIsNudging(true);
    setActionMessage(null);

    try {
      const response: WeatherActionResponse = await nudgeWeather({
        regionId: selectedRegionId,
        pattern,
        intensity,
      });

      if (response.success === false) {
        setActionMessage(`Failed: ${response.message || 'Weather action failed'}`);
        return;
      }

      setActionMessage(response.message || 'Weather influence recorded.');
      if (response.status) {
        setWeatherStatus(response.status);
      } else {
        await fetchWeather();
      }
      await Promise.all([refetchStatus(), refetchRegions()]);
    } catch (error: unknown) {
      setActionMessage(`Failed: ${getErrorMessage(error)}`);
    } finally {
      setIsNudging(false);
    }
  };

  return (
    <PageLayout
      gameStatus={gameStatus}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading weather..."
      errorPrefix="Error loading weather"
      showEventLog={true}
      selectedRegionId={selectedRegionId}
    >
      <PageHeader
        title="Divine Weather"
        subtitle={weatherStatus?.summary ?? 'Shape climate pressure through event-driven nudges'}
        icon="☁"
      />

      <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
        <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
          <div>
            <h2 className="text-xl font-bold text-white">Weather Pattern</h2>
            <p className="mt-1 text-sm text-gray-400">
              Nudge climate pressure, settlement survival, resource output, travel, and conflict.
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
          onSubmit={runWeatherNudge}
          className="mt-4 grid grid-cols-1 gap-3 xl:grid-cols-[1fr_220px_180px_auto]"
        >
          <select
            value={selectedRegionId}
            onChange={event => setSelectedRegionId(event.target.value)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {regions.map(region => (
              <option key={region.id} value={region.id}>
                {region.name} | {region.status}
              </option>
            ))}
          </select>
          <select
            value={pattern}
            onChange={event => setPattern(event.target.value as WeatherPatternKey)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {patternOptions.map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>
          <select
            value={intensity}
            onChange={event => setIntensity(event.target.value as WeatherIntensityKey)}
            className="rounded border border-gray-600 bg-gray-800 px-3 py-2 text-gray-100"
          >
            {intensityOptions.map(option => (
              <option key={option.key} value={option.key}>
                {option.label}
              </option>
            ))}
          </select>
          <button
            type="submit"
            disabled={!canNudge}
            className={`rounded bg-cyan-500 px-5 py-2 text-sm font-bold text-gray-950 transition-colors hover:bg-cyan-400 ${
              canNudge ? '' : 'cursor-not-allowed opacity-50'
            }`}
          >
            Nudge
            <span className="block text-xs font-normal">Cost: {estimatedCost}</span>
          </button>
        </form>

        <div className="mt-4 grid grid-cols-1 gap-3 md:grid-cols-3">
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Pattern</div>
            <div className="mt-1 text-sm text-gray-200">
              {selectedPattern?.summary ?? 'Select a pattern.'}
            </div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Travel</div>
            <div className="mt-1 text-sm text-gray-200">
              {selectedPattern?.travelEffect ?? 'Travel effects appear here.'}
            </div>
          </div>
          <div className="rounded bg-gray-800 px-3 py-2">
            <div className="text-xs uppercase text-gray-500">Conflict</div>
            <div className="mt-1 text-sm text-gray-200">
              {selectedPattern?.conflictEffect ?? 'Conflict effects appear here.'}
            </div>
          </div>
        </div>
      </section>

      {latestInfluence ? (
        <WeatherInfluenceCard influence={latestInfluence} />
      ) : (
        <EmptyState
          title="No Weather Influences"
          message="No divine weather nudges have been invoked yet."
          icon="☁"
        />
      )}

      {recentInfluences.length > 1 && (
        <section className="rounded-lg border border-[#2f334d] bg-[#1a1b26] p-5">
          <h2 className="text-xl font-bold text-white">Recent Weather History</h2>
          <div className="mt-4 space-y-3">
            {recentInfluences.slice(1).map(influence => (
              <div
                key={influence.id}
                className="flex flex-col gap-2 rounded bg-gray-800 px-3 py-3 md:flex-row md:items-center md:justify-between"
              >
                <div>
                  <div className="font-semibold text-gray-100">
                    {influence.patternLabel} over {influence.regionName}
                  </div>
                  <div className="text-sm text-gray-400">{influence.summary}</div>
                </div>
                {influence.eventId && (
                  <Link
                    to={`/events/${influence.eventId}`}
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

export default WeatherPage;

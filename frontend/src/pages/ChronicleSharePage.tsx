import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import BaseLayout from '../components/BaseLayout';
import {
  getPublicChronicleShare,
  type ChronicleSharePackage,
  type ChronicleShareTimelineEntry,
  type PublishedChronicleShareResponse,
} from '../api/apiService';

const formatLabel = (value: string): string =>
  value
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const formatDate = (value?: string | null): string => {
  if (!value) return 'Unknown';
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

const stringValue = (value: unknown): string => {
  if (value === null || value === undefined || value === '') return '';
  if (typeof value === 'number') return value.toLocaleString();
  return String(value);
};

const SpotlightList: React.FC<{
  title: string;
  records: Array<Record<string, unknown>>;
}> = ({ title, records }) => {
  if (records.length === 0) return null;

  return (
    <section className="rounded border border-[#2f334d] bg-[#1a1b26] p-4">
      <h2 className="text-lg font-bold text-white">{title}</h2>
      <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
        {records.map((record, index) => {
          const name = stringValue(record.name) || stringValue(record.id) || `${title} ${index + 1}`;
          const details = Object.entries(record)
            .filter(([key, value]) => key !== 'name' && key !== 'id' && value !== null && value !== undefined)
            .slice(0, 4);

          return (
            <div key={`${title}-${name}-${index}`} className="rounded bg-[#151722] p-3">
              <div className="font-semibold text-yellow-200">{name}</div>
              <div className="mt-2 space-y-1 text-xs text-gray-400">
                {details.map(([key, value]) => (
                  <div key={key} className="flex justify-between gap-3">
                    <span>{formatLabel(key)}</span>
                    <span className="text-gray-200">{stringValue(value)}</span>
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </section>
  );
};

const TimelineCard: React.FC<{
  event: ChronicleShareTimelineEntry;
  compact?: boolean;
}> = ({ event, compact = false }) => (
  <article className="rounded border border-[#2f334d] bg-[#151722] p-4">
    <div className="text-xs uppercase text-gray-500">
      Year {event.year ?? 'Unknown'} - {formatLabel(event.type)} - {formatLabel(event.status)}
    </div>
    <Link to={`/events/${event.id}`} className="mt-1 block font-semibold text-yellow-200 hover:text-yellow-100">
      {event.title}
    </Link>
    <p className="mt-2 text-sm text-gray-300">{event.description}</p>
    {!compact && (
      <div className="mt-3 flex flex-wrap gap-2 text-xs text-gray-400">
        {Object.entries(event.relatedIds).flatMap(([key, ids]) =>
          ids.slice(0, 4).map(id => (
            <span key={`${event.id}-${key}-${id}`} className="rounded bg-[#202335] px-2 py-1">
              {formatLabel(key)} {id}
            </span>
          ))
        )}
      </div>
    )}
  </article>
);

const ChronicleSharePage: React.FC = () => {
  const { shareId } = useParams<{ shareId: string }>();
  const [share, setShare] = useState<PublishedChronicleShareResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [activeReplayIndex, setActiveReplayIndex] = useState(0);

  useEffect(() => {
    let isCurrent = true;

    const loadShare = async () => {
      if (!shareId) {
        setError('Chronicle share not found.');
        setIsLoading(false);
        return;
      }

      try {
        setIsLoading(true);
        const response = await getPublicChronicleShare(shareId);
        if (isCurrent) {
          setShare(response);
          setActiveReplayIndex(0);
          setError(null);
        }
      } catch (loadError) {
        if (isCurrent) {
          setError(loadError instanceof Error ? loadError.message : 'Failed to load chronicle share.');
        }
      } finally {
        if (isCurrent) {
          setIsLoading(false);
        }
      }
    };

    void loadShare();

    return () => {
      isCurrent = false;
    };
  }, [shareId]);

  const chronicle: ChronicleSharePackage | null = share?.package ?? null;
  const topEventTypes = useMemo(
    () => Object.entries(chronicle?.summary.topEventTypes ?? {}).slice(0, 6),
    [chronicle]
  );
  const worldCounts = useMemo(
    () => Object.entries(chronicle?.summary.worldCounts ?? {}),
    [chronicle]
  );
  const spotlightEntries = useMemo(
    () => Object.entries(chronicle?.entitySpotlight ?? {}),
    [chronicle]
  );
  const replayFrames = useMemo(() => {
    const timeline = [...(chronicle?.timeline ?? [])].reverse();
    const runningTypes: Record<string, number> = {};
    const entitySets: Record<'regions' | 'heroes' | 'settlements' | 'landmarks' | 'resources', Set<string>> = {
      regions: new Set<string>(),
      heroes: new Set<string>(),
      settlements: new Set<string>(),
      landmarks: new Set<string>(),
      resources: new Set<string>(),
    };

    return timeline.map((event, index) => {
      runningTypes[event.type] = (runningTypes[event.type] ?? 0) + 1;
      Object.entries(event.relatedIds).forEach(([key, ids]) => {
        if (key in entitySets) {
          ids.forEach(id => entitySets[key as keyof typeof entitySets].add(id));
        }
      });

      const dominantEventType = Object.entries(runningTypes)
        .sort((left, right) => right[1] - left[1])[0]?.[0] ?? 'none';

      return {
        event,
        index,
        frame: index + 1,
        totalFrames: timeline.length,
        dominantEventType,
        entityCounts: {
          regions: entitySets.regions.size,
          heroes: entitySets.heroes.size,
          settlements: entitySets.settlements.size,
          landmarks: entitySets.landmarks.size,
          resources: entitySets.resources.size,
        },
      };
    });
  }, [chronicle]);
  const activeReplayFrame = replayFrames[Math.min(activeReplayIndex, Math.max(0, replayFrames.length - 1))] ?? null;

  return (
    <BaseLayout
      gameStatus={null}
      isLoading={isLoading}
      error={error}
      loadingMessage="Loading chronicle..."
      errorPrefix="Chronicle share"
    >
      {share && chronicle && (
        <div className="space-y-6">
          <section className="rounded border border-[#2f334d] bg-[#1a1b26] p-6">
            <div className="text-sm uppercase text-gray-500">Shared Chronicle</div>
            <h1 className="mt-2 text-3xl font-bold text-white">{chronicle.headline}</h1>
            <p className="mt-3 max-w-4xl text-gray-300">{chronicle.shareText}</p>
            <div className="mt-4 text-sm text-gray-500">
              Share {share.shareId} - Created {formatDate(share.createdAt)} - Exported{' '}
              {formatDate(chronicle.exportedAt)}
            </div>
            <div className="mt-2 text-sm text-gray-500">
              Share Policy: {share.governance.policySummary} Expires {formatDate(share.expiresAt)}.
            </div>
          </section>

          <section className="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-6">
            <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
              <div className="text-xs uppercase text-gray-500">Era</div>
              <div className="mt-1 text-xl font-bold text-blue-300">{chronicle.summary.currentEra}</div>
            </div>
            <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
              <div className="text-xs uppercase text-gray-500">Year</div>
              <div className="mt-1 text-xl font-bold text-blue-300">{chronicle.summary.currentYear}</div>
            </div>
            <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
              <div className="text-xs uppercase text-gray-500">Events</div>
              <div className="mt-1 text-xl font-bold text-blue-300">{chronicle.summary.eventCount}</div>
            </div>
            <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
              <div className="text-xs uppercase text-gray-500">Highlights</div>
              <div className="mt-1 text-xl font-bold text-blue-300">{chronicle.summary.highlightCount}</div>
            </div>
            <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
              <div className="text-xs uppercase text-gray-500">From</div>
              <div className="mt-1 text-xl font-bold text-blue-300">{chronicle.summary.yearRange.from ?? '?'}</div>
            </div>
            <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
              <div className="text-xs uppercase text-gray-500">To</div>
              <div className="mt-1 text-xl font-bold text-blue-300">{chronicle.summary.yearRange.to ?? '?'}</div>
            </div>
          </section>

          {activeReplayFrame && (
            <section className="rounded border border-[#2f334d] bg-[#1a1b26] p-4">
              <div className="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                <div>
                  <h2 className="text-lg font-bold text-white">Public Chronicle Replay</h2>
                  <div className="text-sm text-gray-400">
                    {replayFrames.length} frames from year {chronicle.summary.yearRange.from ?? '?'} to{' '}
                    {chronicle.summary.yearRange.to ?? '?'}
                  </div>
                </div>
                <div className="flex gap-2">
                  <button
                    type="button"
                    onClick={() => setActiveReplayIndex(index => Math.max(0, index - 1))}
                    disabled={activeReplayFrame.index <= 0}
                    className="rounded border border-[#2f334d] bg-[#151722] px-3 py-2 text-sm text-gray-200 disabled:border-gray-800 disabled:text-gray-600"
                  >
                    Previous
                  </button>
                  <button
                    type="button"
                    onClick={() => setActiveReplayIndex(index => Math.min(replayFrames.length - 1, index + 1))}
                    disabled={activeReplayFrame.index >= replayFrames.length - 1}
                    className="rounded border border-[#2f334d] bg-[#151722] px-3 py-2 text-sm text-gray-200 disabled:border-gray-800 disabled:text-gray-600"
                  >
                    Next
                  </button>
                </div>
              </div>

              <div className="mt-4 flex flex-wrap gap-2">
                {replayFrames.map(frame => (
                  <button
                    key={frame.event.id}
                    type="button"
                    onClick={() => setActiveReplayIndex(frame.index)}
                    className={`h-9 min-w-9 rounded border px-3 text-sm ${
                      frame.index === activeReplayFrame.index
                        ? 'border-blue-300 bg-blue-500/20 text-blue-100'
                        : 'border-[#2f334d] bg-[#151722] text-gray-300 hover:border-blue-500'
                    }`}
                    title={frame.event.title}
                  >
                    {frame.frame}
                  </button>
                ))}
              </div>

              <div className="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-[1fr_300px]">
                <div className="rounded border border-[#2f334d] bg-[#151722] p-4">
                  <div className="text-xs uppercase text-gray-500">
                    Frame {activeReplayFrame.frame} of {activeReplayFrame.totalFrames} - Year{' '}
                    {activeReplayFrame.event.year ?? '?'} - {formatLabel(activeReplayFrame.event.type)}
                  </div>
                  <Link
                    to={`/events/${activeReplayFrame.event.id}`}
                    className="mt-1 block text-lg font-semibold text-yellow-200 hover:text-yellow-100"
                  >
                    {activeReplayFrame.event.title}
                  </Link>
                  <p className="mt-2 text-sm text-gray-300">{activeReplayFrame.event.description}</p>
                  <div className="mt-3 text-xs text-gray-500">
                    Era {activeReplayFrame.event.era ?? '?'} - Recorded {formatDate(activeReplayFrame.event.createdAt)}
                  </div>
                </div>

                <aside className="rounded border border-[#2f334d] bg-[#151722] p-4">
                  <h3 className="text-sm font-semibold uppercase text-gray-500">Running Context</h3>
                  <div className="mt-3 space-y-2 text-sm">
                    <div className="flex justify-between gap-3">
                      <span className="text-gray-500">Dominant type</span>
                      <span className="text-gray-200">{formatLabel(activeReplayFrame.dominantEventType)}</span>
                    </div>
                    {Object.entries(activeReplayFrame.entityCounts).map(([key, value]) => (
                      <div key={key} className="flex justify-between gap-3">
                        <span className="text-gray-500">{formatLabel(key)}</span>
                        <span className="text-gray-200">{value}</span>
                      </div>
                    ))}
                  </div>
                  {topEventTypes.length > 0 && (
                    <div className="mt-4 border-t border-[#2f334d] pt-3">
                      <div className="text-xs font-semibold uppercase text-gray-500">Replay Themes</div>
                      <div className="mt-2 flex flex-wrap gap-2">
                        {topEventTypes.slice(0, 4).map(([type, count]) => (
                          <span key={type} className="rounded bg-[#202335] px-2 py-1 text-xs text-gray-300">
                            {formatLabel(type)} {count}
                          </span>
                        ))}
                      </div>
                    </div>
                  )}
                </aside>
              </div>
            </section>
          )}

          <section className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_320px]">
            <div className="rounded border border-[#2f334d] bg-[#1a1b26] p-4">
              <h2 className="text-lg font-bold text-white">Highlights</h2>
              <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                {chronicle.highlights.map(event => (
                  <TimelineCard key={event.id} event={event} compact />
                ))}
              </div>
            </div>

            <aside className="rounded border border-[#2f334d] bg-[#1a1b26] p-4">
              <h2 className="text-lg font-bold text-white">Context</h2>
              <div className="mt-3 space-y-3 text-sm">
                <div className="rounded bg-[#151722] p-3">
                  <div className="text-xs uppercase text-gray-500">Era Pressure</div>
                  <div className="mt-1 font-semibold text-gray-100">
                    {chronicle.eraContext.tierLabel ?? chronicle.eraContext.tier ?? 'Unknown'}
                  </div>
                  <div className="mt-1 text-gray-400">{chronicle.eraContext.topTrigger ?? 'No dominant trigger'}</div>
                </div>
                {topEventTypes.length > 0 && (
                  <div>
                    <div className="text-xs uppercase text-gray-500">Themes</div>
                    <div className="mt-2 flex flex-wrap gap-2">
                      {topEventTypes.map(([type, count]) => (
                        <span key={type} className="rounded bg-[#202335] px-2 py-1 text-xs text-gray-300">
                          {formatLabel(type)} {count}
                        </span>
                      ))}
                    </div>
                  </div>
                )}
                {worldCounts.length > 0 && (
                  <div>
                    <div className="text-xs uppercase text-gray-500">World Counts</div>
                    <div className="mt-2 space-y-1">
                      {worldCounts.map(([key, value]) => (
                        <div key={key} className="flex justify-between gap-3">
                          <span className="text-gray-500">{formatLabel(key)}</span>
                          <span className="text-gray-200">{value}</span>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </aside>
          </section>

          {spotlightEntries.map(([key, records]) => (
            <SpotlightList
              key={key}
              title={formatLabel(key)}
              records={Array.isArray(records) ? records : []}
            />
          ))}

          {chronicle.bettingHighlights.length > 0 && (
            <section className="rounded border border-[#2f334d] bg-[#1a1b26] p-4">
              <h2 className="text-lg font-bold text-white">Betting Highlights</h2>
              <div className="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                {chronicle.bettingHighlights.map((bet, index) => (
                  <div key={stringValue(bet.id) || `bet-${index}`} className="rounded bg-[#151722] p-3">
                    <div className="text-xs uppercase text-gray-500">
                      {formatLabel(stringValue(bet.type) || 'bet')} - {formatLabel(stringValue(bet.status) || 'open')}
                    </div>
                    <div className="mt-1 font-semibold text-yellow-200">{stringValue(bet.description)}</div>
                    <div className="mt-2 text-xs text-gray-400">
                      Stake {stringValue(bet.stake)} - Payout {stringValue(bet.potentialPayout)}
                    </div>
                  </div>
                ))}
              </div>
            </section>
          )}

          <section className="rounded border border-[#2f334d] bg-[#1a1b26] p-4">
            <h2 className="text-lg font-bold text-white">Timeline</h2>
            <div className="mt-3 grid grid-cols-1 gap-3">
              {chronicle.timeline.map(event => (
                <TimelineCard key={event.id} event={event} />
              ))}
            </div>
          </section>
        </div>
      )}
    </BaseLayout>
  );
};

export default ChronicleSharePage;

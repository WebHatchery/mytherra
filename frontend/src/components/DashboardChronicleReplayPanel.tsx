import React, { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import type { ChronicleReplayResponse } from '../api/apiService';

interface DashboardChronicleReplayPanelProps {
  replay: ChronicleReplayResponse | null;
}

const formatLabel = (value: string): string =>
  value
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const DashboardChronicleReplayPanel: React.FC<DashboardChronicleReplayPanelProps> = ({
  replay,
}) => {
  const [activeIndex, setActiveIndex] = useState(0);
  const frames = replay?.frames ?? [];
  const activeFrame = frames[Math.min(activeIndex, Math.max(0, frames.length - 1))] ?? null;
  const topEventTypes = useMemo(
    () => Object.entries(replay?.summary.topEventTypes ?? {}).slice(0, 4),
    [replay]
  );

  if (!replay || frames.length === 0 || !activeFrame) {
    return (
      <section className="mb-8">
        <h2 className="text-xl font-bold text-white mb-3">Chronicle Replay</h2>
        <div className="text-sm text-gray-400">No replay frames are available yet.</div>
      </section>
    );
  }

  const currentIndex = activeFrame.index - 1;
  const previousDisabled = currentIndex <= 0;
  const nextDisabled = currentIndex >= frames.length - 1;

  return (
    <section className="mb-8">
      <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between mb-4">
        <div>
          <h2 className="text-xl font-bold text-white">Chronicle Replay</h2>
          <div className="text-sm text-gray-400">
            {replay.summary.frameCount} frames
            {replay.summary.yearRange.from && replay.summary.yearRange.to
              ? ` from year ${replay.summary.yearRange.from} to ${replay.summary.yearRange.to}`
              : ''}
          </div>
        </div>
        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setActiveIndex(index => Math.max(0, index - 1))}
            disabled={previousDisabled}
            className="px-3 py-2 text-sm rounded border border-[#2f334d] bg-[#1a1b26] text-gray-200 disabled:text-gray-600 disabled:border-[#242638]"
          >
            Prev
          </button>
          <button
            type="button"
            onClick={() => setActiveIndex(index => Math.min(frames.length - 1, index + 1))}
            disabled={nextDisabled}
            className="px-3 py-2 text-sm rounded border border-[#2f334d] bg-[#1a1b26] text-gray-200 disabled:text-gray-600 disabled:border-[#242638]"
          >
            Next
          </button>
        </div>
      </div>

      <div className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d]">
        <div className="flex flex-wrap gap-2 mb-4">
          {frames.map(frame => (
            <button
              key={frame.eventId}
              type="button"
              onClick={() => setActiveIndex(frame.index - 1)}
              className={`h-9 min-w-9 px-3 rounded border text-sm ${
                frame.index === activeFrame.index
                  ? 'border-blue-300 bg-blue-500/20 text-blue-100'
                  : 'border-[#2f334d] bg-[#151722] text-gray-300 hover:border-blue-500'
              }`}
              title={frame.title}
            >
              {frame.index}
            </button>
          ))}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-[1fr_280px] gap-4">
          <div>
            <div className="text-xs text-gray-500">
              Frame {activeFrame.index} of {frames.length} • Year {activeFrame.year ?? '?'} •{' '}
              {formatLabel(activeFrame.type)}
            </div>
            <h3 className="mt-1 text-lg font-semibold text-white">{activeFrame.title}</h3>
            <p className="mt-2 text-sm text-gray-300">{activeFrame.description}</p>
            <div className="mt-3 text-xs text-gray-500">{activeFrame.beatSummary}</div>
            <Link
              to={`/events/${activeFrame.eventId}`}
              className="mt-3 inline-block text-sm text-blue-300 hover:text-blue-100"
            >
              Open event
            </Link>
          </div>

          <div className="rounded border border-[#2f334d] bg-[#151722] p-3">
            <div className="text-xs font-semibold uppercase text-gray-500">
              Running Context
            </div>
            <div className="mt-2 space-y-2 text-sm text-gray-300">
              <div className="flex justify-between gap-3">
                <span className="text-gray-500">Dominant type</span>
                <span>{formatLabel(activeFrame.runningContext.dominantEventType ?? 'none')}</span>
              </div>
              {Object.entries(activeFrame.runningContext.entityCounts).map(([key, value]) => (
                <div key={key} className="flex justify-between gap-3">
                  <span className="text-gray-500">{formatLabel(key)}</span>
                  <span>{value}</span>
                </div>
              ))}
            </div>
            {topEventTypes.length > 0 && (
              <div className="mt-4 border-t border-[#2f334d] pt-3">
                <div className="text-xs font-semibold uppercase text-gray-500">
                  Replay Themes
                </div>
                <div className="mt-2 flex flex-wrap gap-2">
                  {topEventTypes.map(([type, count]) => (
                    <span key={type} className="rounded bg-[#202335] px-2 py-1 text-xs text-gray-300">
                      {formatLabel(type)} {count}
                    </span>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </section>
  );
};

export default DashboardChronicleReplayPanel;

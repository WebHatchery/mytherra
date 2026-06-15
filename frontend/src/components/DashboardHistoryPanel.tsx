import React from 'react';
import { Link } from 'react-router-dom';
import type {
  BetHistoryItem,
  EntityHistoryItem,
  EntityHistorySummaryResponse,
} from '../api/apiService';

interface DashboardHistoryPanelProps {
  summary: EntityHistorySummaryResponse | null;
}

interface HistorySection {
  label: string;
  items: EntityHistoryItem[];
  accentClass: string;
}

const formatStatus = (value: string): string =>
  value
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const historyTone = (status: EntityHistoryItem['historyStatus']): string => {
  switch (status) {
    case 'direct':
      return 'text-green-300';
    case 'direct_with_region_context':
      return 'text-cyan-300';
    case 'region_context':
      return 'text-yellow-300';
    default:
      return 'text-gray-500';
  }
};

const BetRow: React.FC<{ bet: BetHistoryItem }> = ({ bet }) => (
  <div className="py-2 border-t border-[#2f334d] first:border-t-0">
    <div className="flex items-start justify-between gap-3">
      <div>
        <div className="text-sm text-gray-200">{bet.description}</div>
        <div className="text-xs text-gray-500">
          {bet.targetName ?? bet.targetId} • {formatStatus(bet.betType)} • Year {bet.placedYear}
          {bet.resolvedYear ? ` to ${bet.resolvedYear}` : ''}
        </div>
      </div>
      <div
        className={
          bet.status === 'won'
            ? 'text-sm text-green-300'
            : bet.status === 'lost'
              ? 'text-sm text-red-300'
              : 'text-sm text-gray-300'
        }
      >
        {formatStatus(bet.status)}
      </div>
    </div>
    {bet.resolutionNotes && <div className="mt-1 text-xs text-gray-500">{bet.resolutionNotes}</div>}
  </div>
);

const DashboardHistoryPanel: React.FC<DashboardHistoryPanelProps> = ({ summary }) => {
  if (!summary) {
    return (
      <section className="mb-8">
        <h2 className="text-xl font-bold text-white mb-3">Chronicles</h2>
        <div className="text-sm text-gray-400">History summaries are not available yet.</div>
      </section>
    );
  }

  const sections: HistorySection[] = [
    { label: 'Regions', items: summary.entities.regions, accentClass: 'text-cyan-300' },
    { label: 'Settlements', items: summary.entities.settlements, accentClass: 'text-emerald-300' },
    { label: 'Landmarks', items: summary.entities.landmarks, accentClass: 'text-violet-300' },
    { label: 'Resources', items: summary.entities.resources, accentClass: 'text-amber-300' },
    { label: 'Heroes', items: summary.entities.heroes, accentClass: 'text-blue-300' },
  ];

  return (
    <section className="mb-8">
      <div className="flex flex-col gap-2 md:flex-row md:items-end md:justify-between mb-4">
        <div>
          <h2 className="text-xl font-bold text-white">Chronicles</h2>
          <div className="text-sm text-gray-400">
            Entity history generated {new Date(summary.generatedAt).toLocaleString()}
          </div>
        </div>
        <div className="text-sm text-gray-400">
          {summary.limits.entitiesPerType} per type • {summary.limits.eventsPerEntity} events each
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
        {sections.map(section => (
          <div key={section.label} className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d]">
            <div className="flex items-center justify-between gap-3 mb-3">
              <h3 className="text-sm font-semibold text-gray-200">{section.label}</h3>
              <span className={`text-sm font-bold ${section.accentClass}`}>
                {section.items.length}
              </span>
            </div>
            <div>
              {section.items.slice(0, 4).map(item => (
                <div key={item.id} className="py-3 border-t border-[#2f334d] first:border-t-0">
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <div className="text-sm text-gray-100">{item.name}</div>
                      <div className="text-xs text-gray-500">{item.currentState.summary}</div>
                    </div>
                    <div className={`text-xs whitespace-nowrap ${historyTone(item.historyStatus)}`}>
                      {formatStatus(item.historyStatus)}
                    </div>
                  </div>
                  {item.lastEventTitle ? (
                    <div className="mt-2 text-xs text-gray-400">
                      Year {item.lastEventYear ?? '?'} •{' '}
                      {item.recentEvents[0] ? (
                        <Link
                          to={`/events/${item.recentEvents[0].id}`}
                          className="text-blue-300 hover:text-blue-100"
                        >
                          {item.lastEventTitle}
                        </Link>
                      ) : (
                        item.lastEventTitle
                      )}
                    </div>
                  ) : (
                    <div className="mt-2 text-xs text-gray-500">{item.historyNote}</div>
                  )}
                </div>
              ))}
              {section.items.length === 0 && (
                <div className="text-xs text-gray-500">No entities found.</div>
              )}
            </div>
          </div>
        ))}

        <div className="bg-[#1a1b26] p-4 rounded-lg border border-[#2f334d] xl:col-span-2">
          <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between mb-3">
            <div>
              <h3 className="text-sm font-semibold text-gray-200">Bet History</h3>
              <div className="text-xs text-gray-500">
                {summary.bets.total} total • {summary.bets.active} active • {summary.bets.won} won •{' '}
                {summary.bets.lost} lost • {summary.bets.expired} expired
              </div>
            </div>
          </div>
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div>
              <h4 className="text-xs font-semibold text-gray-400 mb-1">Active</h4>
              {summary.bets.recentActive.slice(0, 4).map(bet => (
                <BetRow key={bet.id} bet={bet} />
              ))}
              {summary.bets.recentActive.length === 0 && (
                <div className="text-xs text-gray-500">No active bets.</div>
              )}
            </div>
            <div>
              <h4 className="text-xs font-semibold text-gray-400 mb-1">Resolved</h4>
              {summary.bets.recentResolved.slice(0, 4).map(bet => (
                <BetRow key={bet.id} bet={bet} />
              ))}
              {summary.bets.recentResolved.length === 0 && (
                <div className="text-xs text-gray-500">No resolved bets.</div>
              )}
            </div>
          </div>
        </div>
      </div>
    </section>
  );
};

export default DashboardHistoryPanel;

import React from 'react';
import { Link } from 'react-router-dom';
import type {
  EntityHistoryItem,
  EntityHistorySummaryResponse,
} from '../../api/apiService';
import type { Region } from '../../entities/region';

interface RegionHistoryTabProps {
  region: Region;
  summary: EntityHistorySummaryResponse | null;
  loading: boolean;
}

interface HistoryGroup {
  label: string;
  items: EntityHistoryItem[];
  accentClass: string;
}

const formatLabel = (value: string): string =>
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

const timelineFilter = (item: EntityHistoryItem): string => {
  switch (item.entityType) {
    case 'region':
      return `regionId=${encodeURIComponent(item.id)}`;
    case 'settlement':
      return `settlementId=${encodeURIComponent(item.id)}`;
    case 'landmark':
      return `landmarkId=${encodeURIComponent(item.id)}`;
    case 'resource':
      return `resourceId=${encodeURIComponent(item.id)}`;
    case 'hero':
      return `heroId=${encodeURIComponent(item.id)}`;
    default:
      return `regionId=${encodeURIComponent(item.regionId ?? item.id)}`;
  }
};

const HistoryItemRow: React.FC<{ item: EntityHistoryItem }> = ({ item }) => (
  <div className="py-3 border-t border-gray-700 first:border-t-0">
    <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
      <div>
        <div className="text-sm font-medium text-white">{item.name}</div>
        <div className="text-xs text-gray-400">{item.currentState.summary}</div>
      </div>
      <div className={`text-xs whitespace-nowrap ${historyTone(item.historyStatus)}`}>
        {formatLabel(item.historyStatus)}
      </div>
    </div>

    <div className="mt-2 flex flex-wrap gap-2 text-xs">
      <Link to={`/?${timelineFilter(item)}`} className="text-blue-300 hover:text-blue-100">
        Timeline
      </Link>
      <span className="text-gray-500">
        {item.directEventCount} direct, {item.shownEventCount} shown
      </span>
    </div>

    {item.recentEvents.length > 0 ? (
      <div className="mt-2 space-y-1">
        {item.recentEvents.slice(0, 3).map(event => (
          <div key={event.id} className="text-xs text-gray-400">
            Year {event.year ?? '?'}{' '}
            <Link to={`/events/${event.id}`} className="text-blue-300 hover:text-blue-100">
              {event.title}
            </Link>
            {event.matchType === 'region_context' && (
              <span className="text-gray-500"> via region</span>
            )}
          </div>
        ))}
      </div>
    ) : (
      <div className="mt-2 text-xs text-gray-500">{item.historyNote}</div>
    )}
  </div>
);

const RegionHistoryTab: React.FC<RegionHistoryTabProps> = ({ region, summary, loading }) => {
  if (loading) {
    return <div className="text-gray-400 py-6">Loading history...</div>;
  }

  if (!summary) {
    return <div className="text-gray-400 py-6">History is not available.</div>;
  }

  const groups: HistoryGroup[] = [
    {
      label: 'Region',
      items: summary.entities.regions.filter(item => item.id === region.id),
      accentClass: 'text-cyan-300',
    },
    {
      label: 'Settlements',
      items: summary.entities.settlements.filter(item => item.regionId === region.id),
      accentClass: 'text-emerald-300',
    },
    {
      label: 'Resources',
      items: summary.entities.resources.filter(item => item.regionId === region.id),
      accentClass: 'text-amber-300',
    },
    {
      label: 'Landmarks',
      items: summary.entities.landmarks.filter(item => item.regionId === region.id),
      accentClass: 'text-violet-300',
    },
    {
      label: 'Heroes',
      items: summary.entities.heroes.filter(item => item.regionId === region.id),
      accentClass: 'text-blue-300',
    },
  ];

  return (
    <div className="space-y-4">
      <div className="grid grid-cols-1 xl:grid-cols-2 gap-4">
        {groups.map(group => (
          <section key={group.label} className="bg-gray-700 rounded p-4">
            <div className="flex items-center justify-between gap-3 mb-1">
              <h4 className="text-sm font-semibold text-gray-200">{group.label}</h4>
              <span className={`text-sm font-bold ${group.accentClass}`}>
                {group.items.length}
              </span>
            </div>
            {group.items.length > 0 ? (
              group.items.map(item => <HistoryItemRow key={item.id} item={item} />)
            ) : (
              <div className="py-3 text-xs text-gray-500">No matching history.</div>
            )}
          </section>
        ))}
      </div>
    </div>
  );
};

export default RegionHistoryTab;

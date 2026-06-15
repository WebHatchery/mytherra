import React, { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import BaseLayout from '../components/BaseLayout';
import PageHeader from '../components/PageHeader';
import { getGameEventById } from '../api/apiService';
import type { GameEvent } from '../entities/event';
import { useGameStatus } from '../hooks/useGameStatus';
import { useRegions } from '../contexts/useRegionContext';

const formatLabel = (value: string): string =>
  value
    .split(/[-_]/)
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1))
    .join(' ');

const formatTimestamp = (value?: string): string => {
  if (!value) {
    return 'Unknown';
  }

  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? value : date.toLocaleString();
};

const timelineUrl = (key: string, value: string): string =>
  `/?${new URLSearchParams({ [key]: value }).toString()}`;

const EventDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { getRegionName } = useRegions();
  const {
    gameStatus,
    isLoading: isLoadingGameStatus,
    error: gameStatusError,
  } = useGameStatus({
    autoRefresh: true,
    refreshInterval: 10000,
  });
  const [event, setEvent] = useState<GameEvent | null>(null);
  const [isLoadingEvent, setIsLoadingEvent] = useState(true);
  const [eventError, setEventError] = useState<string | null>(null);

  useEffect(() => {
    let isCurrent = true;

    const loadEvent = async () => {
      if (!id) {
        setEventError('No event ID was provided.');
        setIsLoadingEvent(false);
        return;
      }

      try {
        setIsLoadingEvent(true);
        const loadedEvent = await getGameEventById(id);
        if (isCurrent) {
          setEvent(loadedEvent);
          setEventError(null);
        }
      } catch (error) {
        if (isCurrent) {
          setEventError(error instanceof Error ? error.message : 'Failed to load event.');
        }
      } finally {
        if (isCurrent) {
          setIsLoadingEvent(false);
        }
      }
    };

    loadEvent();

    return () => {
      isCurrent = false;
    };
  }, [id]);

  const relatedRegionIds = useMemo(() => {
    if (!event) {
      return [];
    }

    return Array.from(
      new Set([event.regionId, ...(event.relatedRegionIds ?? [])].filter(Boolean) as string[])
    );
  }, [event]);

  const relatedHeroIds = event?.relatedHeroIds ?? [];
  const relatedSettlementIds = event?.relatedSettlementIds ?? [];
  const relatedLandmarkIds = event?.relatedLandmarkIds ?? [];
  const relatedResourceIds = event?.relatedResourceIds ?? [];
  const error = gameStatusError || eventError;

  return (
    <BaseLayout
      gameStatus={gameStatus}
      isLoading={isLoadingGameStatus || isLoadingEvent}
      error={error}
      loadingMessage="Loading event..."
      errorPrefix="Error loading event"
    >
      {event && (
        <>
          <div className="mb-4">
            <Link to="/" className="text-sm text-blue-300 hover:text-blue-100">
              Back to Chronicles
            </Link>
          </div>

          <PageHeader
            title={event.title || 'World Event'}
            subtitle={event.year ? `Year ${event.year}` : 'Undated event'}
            description={event.description}
            icon="📜"
          />

          <div className="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section className="lg:col-span-2 bg-gray-800 rounded-lg p-6 border border-gray-700">
              <h2 className="text-xl font-bold text-white mb-4">Event Record</h2>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                <div>
                  <div className="text-gray-400">Type</div>
                  <div className="text-gray-100">{formatLabel(event.type)}</div>
                </div>
                <div>
                  <div className="text-gray-400">Status</div>
                  <div className="text-gray-100">{formatLabel(event.status)}</div>
                </div>
                <div>
                  <div className="text-gray-400">Recorded</div>
                  <div className="text-gray-100">{formatTimestamp(event.timestamp)}</div>
                </div>
                <div>
                  <div className="text-gray-400">Event ID</div>
                  <div className="break-all text-gray-100">{event.id}</div>
                </div>
              </div>
            </section>

            <aside className="bg-gray-800 rounded-lg p-6 border border-gray-700">
              <h2 className="text-xl font-bold text-white mb-4">Timeline Filters</h2>
              <div className="space-y-3 text-sm">
                <Link
                  to={timelineUrl('type', event.type)}
                  className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                >
                  {formatLabel(event.type)} Events
                </Link>
                <Link
                  to={timelineUrl('status', event.status)}
                  className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                >
                  {formatLabel(event.status)} Events
                </Link>
                {relatedRegionIds.map(regionId => (
                  <Link
                    key={regionId}
                    to={timelineUrl('regionId', regionId)}
                    className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                  >
                    {getRegionName(regionId)}
                  </Link>
                ))}
                {relatedHeroIds.map(heroId => (
                  <Link
                    key={heroId}
                    to={timelineUrl('heroId', heroId)}
                    className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                  >
                    Hero {heroId}
                  </Link>
                ))}
                {relatedSettlementIds.map(settlementId => (
                  <Link
                    key={settlementId}
                    to={timelineUrl('settlementId', settlementId)}
                    className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                  >
                    Settlement {settlementId}
                  </Link>
                ))}
                {relatedLandmarkIds.map(landmarkId => (
                  <Link
                    key={landmarkId}
                    to={timelineUrl('landmarkId', landmarkId)}
                    className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                  >
                    Landmark {landmarkId}
                  </Link>
                ))}
                {relatedResourceIds.map(resourceId => (
                  <Link
                    key={resourceId}
                    to={timelineUrl('resourceId', resourceId)}
                    className="block rounded bg-gray-700 px-3 py-2 text-blue-200 hover:bg-gray-600"
                  >
                    Resource {resourceId}
                  </Link>
                ))}
              </div>
            </aside>
          </div>
        </>
      )}
    </BaseLayout>
  );
};

export default EventDetailPage;

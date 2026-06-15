// F:\WebDevelopment\Mytherra\frontend\src\components\EventLog.tsx
import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { GameEvent } from '../entities/event';
import { getGameEvents } from '../api/apiService';
import { useRegions } from '../contexts/useRegionContext';

interface EventLogProps {
  selectedRegionId?: string;
  selectedHeroId?: string;
}

const EventLog: React.FC<EventLogProps> = ({ selectedRegionId, selectedHeroId }) => {
  const [events, setEvents] = useState<GameEvent[]>([]);
  const [isLoading, setIsLoading] = useState<boolean>(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(1);
  const eventsPerPage = 10;
  const { getRegionName } = useRegions(); // Handle region selection changes

  useEffect(() => {
    // Reset to first page when region or hero selection changes
    setCurrentPage(1);
  }, [selectedRegionId, selectedHeroId]);
  // Handle loading events based on current page, region, and hero
  useEffect(() => {
    const loadEvents = async (page: number) => {
      try {
        setIsLoading(true);
        // Backend returns direct array instead of paginated object
        const data = await getGameEvents(page, eventsPerPage, {
          regionId: selectedRegionId,
          heroId: selectedHeroId,
        });

        // Handle the array response directly
        if (Array.isArray(data)) {
          setEvents(data);
        } else {
          // Fallback in case the response format changes
          console.warn('Unexpected response format from events API:', data);
          setEvents([]);
        }

        setError(null);
      } catch (err) {
        if (err instanceof Error) {
          setError(err.message);
        } else {
          setError('An unknown error occurred');
        }
        console.error('Failed to load events:', err);
      }
      setIsLoading(false);
    };

    loadEvents(currentPage);
  }, [currentPage, selectedRegionId, selectedHeroId, eventsPerPage]);

  if (isLoading) {
    return <div className="text-center p-4">Loading event log...</div>;
  }

  if (error) {
    return <div className="text-center p-4 text-red-500">Error loading events: {error}</div>;
  }
  if (!events || events.length === 0) {
    return <div className="text-center p-4">No events to display.</div>;
  }

  // Function to perform additional client-side filtering (belt and suspenders approach)
  const isEventRelatedToRegion = (event: GameEvent, regionId: string): boolean => {
    // Check if the event has this region in its relatedRegionIds
    const isRegionRelated = event.relatedRegionIds?.includes(regionId);
    if (isRegionRelated) return true;

    // For now, consider only direct region relationships
    // This is a fallback since the server should be handling hero relationships properly
    return false;
  };

  // Function to check if an event is related to a hero
  const isEventRelatedToHero = (event: GameEvent, heroId: string): boolean => {
    // Check if the event has this hero in its relatedHeroIds
    return Array.isArray(event.relatedHeroIds) && event.relatedHeroIds.includes(heroId);
  };

  // Apply additional filtering on the client side if a region or hero is selected
  const filteredEvents = events.filter(event => {
    if (selectedHeroId && !isEventRelatedToHero(event, selectedHeroId)) {
      return false;
    }

    if (selectedRegionId && !isEventRelatedToRegion(event, selectedRegionId)) {
      return false;
    }

    return true;
  });
  return (
    <div className="p-4 bg-gray-700 text-white rounded-lg shadow-xl mt-6">
      {' '}
      <div className="flex flex-col items-center mb-4">
        <h2 className="text-2xl font-bold text-center">
          {selectedHeroId
            ? `Hero Events`
            : selectedRegionId
              ? `${getRegionName(selectedRegionId)} Events`
              : 'World Event Log'}
        </h2>
        {selectedHeroId && (
          <>
            <div className="mt-1 text-sm text-gray-400">Showing events related to this hero</div>
            <Link
              to="/"
              className="mt-2 text-sm text-blue-300 hover:text-blue-100"
            >
              View All Events
            </Link>
          </>
        )}
        {selectedRegionId && !selectedHeroId && (
          <>
            <div className="mt-1 text-sm text-gray-400">
              Showing events in this region and those involving heroes from this region
            </div>
            <Link
              to="/"
              className="mt-2 text-sm text-blue-300 hover:text-blue-100"
            >
              View All Events
            </Link>
          </>
        )}
      </div>
      <ul className="space-y-3 max-h-96 overflow-y-auto pr-2">
        {filteredEvents.map(event => {
          // Replace region IDs with region names in description (global, safe)
          let processedDescription = event.description;
          if (event.relatedRegionIds && event.relatedRegionIds.length > 0) {
            event.relatedRegionIds.forEach(regionId => {
              const regionName = getRegionName(regionId);
              // Use word boundary to avoid partial replacements
              processedDescription = processedDescription.replace(
                new RegExp(`\\b${regionId}\\b`, 'g'),
                regionName
              );
            });
          }
          // Treat any event with relatedHeroIds as a hero action
          const isHeroAction =
            Array.isArray(event.relatedHeroIds) && event.relatedHeroIds.length > 0;
          const baseLiClasses = 'p-3 bg-gray-600 rounded-md shadow';
          const heroActionLiClasses = isHeroAction ? 'border-l-4 border-yellow-400' : '';
          return (
            <li
              key={event.id}
              className={[baseLiClasses, heroActionLiClasses].filter(Boolean).join(' ')}
            >
              <p className="font-semibold text-lg">
                <Link to={`/events/${event.id}`} className="text-yellow-200 hover:text-yellow-100">
                  {event.year ? `Year ${event.year}: ` : ''}
                  {event.title || processedDescription}
                </Link>
              </p>
              {event.title && event.title !== processedDescription && (
                <p className="mt-1 text-sm text-gray-300">{processedDescription}</p>
              )}
            </li>
          );
        })}
      </ul>{' '}
      {/* Simple pagination without total pages info */}
      <div className="mt-4 flex justify-center items-center space-x-2">
        <button
          onClick={() => setCurrentPage(Math.max(1, currentPage - 1))}
          disabled={currentPage <= 1 || events.length === 0}
          className="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded disabled:opacity-50"
        >
          Previous
        </button>
        <span className="text-gray-300">Page {currentPage}</span>
        <button
          onClick={() => setCurrentPage(currentPage + 1)}
          disabled={events.length < eventsPerPage}
          className="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded disabled:opacity-50"
        >
          Next
        </button>
      </div>
    </div>
  );
};

export default EventLog;

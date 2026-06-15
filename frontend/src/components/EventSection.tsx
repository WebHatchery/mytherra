import React from 'react';
import { Link } from 'react-router-dom';
import { GameEvent } from '../entities/event';

interface EventSectionProps {
  title: string;
  icon: string;
  events: GameEvent[];
  borderColor: 'yellow' | 'blue' | 'purple' | 'green' | 'red';
  titleColor: 'yellow' | 'blue' | 'purple' | 'green' | 'red';
}

const EventSection: React.FC<EventSectionProps> = ({
  title,
  icon,
  events,
  borderColor,
  titleColor,
}) => {
  if (events.length === 0) return null;

  const getBorderColorClass = (color: string) => {
    const colorMap = {
      yellow: 'border-yellow-400',
      blue: 'border-blue-400',
      purple: 'border-purple-400',
      green: 'border-green-400',
      red: 'border-red-400',
    };
    return colorMap[color as keyof typeof colorMap] || colorMap.blue;
  };

  const getTitleColorClass = (color: string) => {
    const colorMap = {
      yellow: 'text-yellow-300',
      blue: 'text-blue-300',
      purple: 'text-purple-300',
      green: 'text-green-300',
      red: 'text-red-300',
    };
    return colorMap[color as keyof typeof colorMap] || colorMap.blue;
  };

  const getEventBorderClass = (color: string) => {
    const colorMap = {
      yellow: 'border-yellow-400',
      blue: 'border-blue-400',
      purple: 'border-purple-400',
      green: 'border-green-400',
      red: 'border-red-400',
    };
    return colorMap[color as keyof typeof colorMap] || colorMap.blue;
  };

  const getEventTextClass = (color: string) => {
    const colorMap = {
      yellow: 'text-yellow-200',
      blue: 'text-blue-200',
      purple: 'text-purple-200',
      green: 'text-green-200',
      red: 'text-red-200',
    };
    return colorMap[color as keyof typeof colorMap] || colorMap.blue;
  };

  return (
    <div className={`bg-gray-800 rounded-lg p-6 border-l-4 ${getBorderColorClass(borderColor)}`}>
      <h2 className={`text-2xl font-bold mb-4 ${getTitleColorClass(titleColor)} flex items-center`}>
        <span className="mr-2">{icon}</span> {title}
      </h2>
      <div className="space-y-4">
        {events.map(event => (
          <Link
            key={event.id}
            to={`/events/${event.id}`}
            className={`bg-gray-700 p-4 rounded-md border-l-2 ${getEventBorderClass(borderColor)}`}
          >
            <div className="flex justify-between items-start mb-2">
              <span className={`text-sm font-medium ${getEventTextClass(titleColor)}`}>
                {event.year ? `Year ${event.year}` : 'Recent'}
              </span>
              <span className="text-xs text-gray-400">
                {new Date(event.timestamp).toLocaleDateString()}
              </span>
            </div>
            <p className="text-lg font-semibold text-white">{event.title || event.description}</p>
            {event.title && event.title !== event.description && (
              <p className="mt-1 text-sm text-gray-300">{event.description}</p>
            )}
          </Link>
        ))}
      </div>
    </div>
  );
};

export default EventSection;

import { Hero } from '../entities/hero';
import type { Settlement } from '../entities/settlement';

/**
 * Utility function to determine status color for various game entities
 */
export const getStatusColor = (status: string): string => {
  switch (status) {
    case 'peaceful':
      return 'text-green-400';
    case 'corrupt':
      return 'text-yellow-400';
    case 'warring':
      return 'text-red-400';
    case 'abandoned':
      return 'text-gray-400';
    default:
      return 'text-gray-300';
  }
};

/**
 * Utility function to get settlement counts by type
 */
export const getSettlementSummary = (
  settlements: Settlement[]
): Record<Settlement['type'], number> => {
  return settlements.reduce(
    (acc, settlement) => {
      acc[settlement.type] += 1;
      return acc;
    },
    {
      city: 0,
      town: 0,
      village: 0,
      hamlet: 0,
      metropolis: 0,
      outpost: 0,
      stronghold: 0,
    }
  );
};

/**
 * Utility function to calculate total population from settlements
 */
export const getTotalPopulation = (settlements: Settlement[]): number => {
  return settlements.reduce((sum, settlement) => sum + settlement.population, 0);
};

/**
 * Utility function to get icon for settlement types
 */
export const getSettlementIcon = (type: string): string => {
  switch (type) {
    case 'city':
      return '🏰';
    case 'town':
      return '🏘️';
    case 'village':
      return '🏡';
    default:
      return '🏠'; // hamlet or unknown type
  }
};

/**
 * Utility function to get icon for landmark types
 */
export const getLandmarkIcon = (type: string): string => {
  switch (type) {
    case 'temple':
      return '⛩️';
    case 'ruin':
      return '🏛️';
    case 'forest':
      return '🌲';
    case 'mountain':
      return '⛰️';
    case 'river':
      return '🏞️';
    default:
      return '📍';
  }
};

/**
 * Utility function to filter and get only living heroes
 */
export const getLivingHeroes = (heroes: Hero[]): Hero[] => {
  return heroes.filter(hero => hero.isAlive !== false);
};

/**
 * Utility function to count living heroes
 */
export const getLivingHeroesCount = (heroes: Hero[]): number => {
  return heroes.filter(hero => hero.isAlive !== false).length;
};

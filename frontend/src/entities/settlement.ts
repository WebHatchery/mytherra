// F:\WebDevelopment\Mytherra\frontend\src\entities\settlement.ts

/**
 * Represents a settlement within a region
 */
export interface Settlement {
  id: string;
  regionId: string;
  name: string;
  type: 'town' | 'village' | 'city' | 'hamlet' | 'metropolis' | 'outpost' | 'stronghold';
  population: number;
  prosperity: number; // 0-100
  defensibility: number; // 0-100
  status:
    | 'thriving'
    | 'prosperous'
    | 'stable'
    | 'declining'
    | 'struggling'
    | 'abandoned'
    | 'ruined';
  specializations: string[]; // ['trade', 'crafting', 'magic', 'military', 'agriculture']
  events: string[]; // Event IDs that occurred here
  foundedYear: number;
  lastEventYear?: number;
  traits: string[]; // ['fortified', 'river_crossing', 'mining_hub', 'pilgrimage_site']
  createdAt?: Date;
  updatedAt?: Date;
}

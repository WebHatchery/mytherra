// F:\WebDevelopment\Mytherra\frontend\src\entities\region.ts

/**
 * Represents a distinct area in the game world.
 */
export type RegionInfluenceActionKey = 'blessRegion' | 'corruptRegion' | 'guideResearch';

export interface RegionInfluenceEffectPreview {
  cost: number;
  effects: Partial<Record<'prosperity' | 'chaos' | 'dangerLevel' | 'magicAffinity', number>>;
  summary: string;
}

export interface RegionInfluenceEffectiveness {
  divineResonance: number;
  tier: 'very_low' | 'low' | 'moderate' | 'high' | 'very_high';
  label: string;
  costMultiplier: number;
  effectMultiplier: number;
  costAdjustmentPercent: number;
  effectAdjustmentPercent: number;
  summary: string;
  actions: Partial<Record<RegionInfluenceActionKey, RegionInfluenceEffectPreview>>;
}

export interface Region {
  id: string;
  name: string;
  color: string; // Hex color for map visualization
  prosperity: number; // Scale of 0-100
  chaos: number; // Scale of 0-100
  magicAffinity: number; // Scale of 0-100
  status:
    | 'peaceful'
    | 'corrupt'
    | 'abandoned'
    | 'warring'
    | 'flourishing'
    | 'prosperous'
    | 'stable'
    | 'turbulent'
    | 'declining'
    | 'war_torn'
    | 'mysterious'
    | 'blessed'
    | 'cursed';
  eventIds: string[]; // IDs of events that have occurred in this region
  influenceActionCosts?: {
    blessRegion?: number;
    corruptRegion?: number;
    guideResearch?: number;
  };
  influenceEffectiveness?: RegionInfluenceEffectiveness;
  // Enhanced region features
  populationTotal?: number; // Calculated from settlements
  regionalTraits?: string[]; // ['mountainous', 'coastal', 'forested', 'desert', 'magical_nexus']
  climateType?: 'temperate' | 'arctic' | 'tropical' | 'arid' | 'magical';
  tradeRoutes?: string[]; // IDs of connected regions
  culturalInfluence?: string; // 'scholarly', 'martial', 'mystical', 'mercantile', 'pastoral'
  divineResonance?: number; // How responsive the region is to divine influence (0-100)
  dangerLevel?: number;
  tags?: string[];
  createdAt?: Date;
  updatedAt?: Date;
}

// F:\WebDevelopment\Mytherra\frontend\src\entities\divineBet.ts

/**
 * Represents a divine bet placed by a player
 */
export interface DivineBet {
  id: string;
  playerId: string;
  betType:
    | 'settlement_growth'
    | 'landmark_discovery'
    | 'cultural_shift'
    | 'hero_settlement_bond'
    | 'hero_location_visit'
    | 'settlement_transformation'
    | 'corruption_spread';
  targetId: string; // ID of settlement, landmark, hero, or region
  description: string;
  timeframe: number; // Years within which bet must resolve
  confidence: 'long_shot' | 'possible' | 'likely' | 'near_certain';
  divineFavorStake: number;
  potentialPayout: number;
  currentOdds: number;
  status: 'active' | 'won' | 'lost' | 'expired';
  placedYear: number;
  resolvedYear?: number;
  resolutionNotes?: string;
  createdAt?: Date;
  updatedAt?: Date;
}

/**
 * Represents a speculation event that can be bet upon
 */
export interface SpeculationEvent {
  id: string;
  title: string;
  description: string;
  targetId?: string;
  regionId?: string;
  settlementId?: string;
  landmarkId?: string;
  heroId?: string;
  eventType: DivineBet['betType'];
  timeframe: {
    minimum: number; // Earliest possible resolution (years)
    maximum: number; // Latest possible resolution (years)
  };
  bettingOptions: BettingOption[];
  influenceOptions?: InfluenceOption[];
  createdAt?: Date;
  updatedAt?: Date;
}

/**
 * Represents a betting option for a speculation event
 */
export interface BettingOption {
  id: string;
  description: string;
  targetId?: string;
  currentOdds: number;
  minimumStake: number;
  potentialPayout: number;
}

/**
 * Represents an influence option for a speculation event
 */
export interface InfluenceOption {
  id: string;
  description: string;
  cost: number; // Divine favor cost
  effectStrength: 'subtle' | 'minor' | 'moderate';
  influenceType: 'environmental' | 'inspirational' | 'coincidental';
}

/**
 * Represents betting odds for various events
 */
export interface BettingOdds {
  eventId: string;
  odds: {
    [optionId: string]: number;
  };
  lastUpdated: Date;
}

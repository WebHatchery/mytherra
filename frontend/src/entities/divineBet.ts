// F:\WebDevelopment\Mytherra\frontend\src\entities\divineBet.ts

/**
 * Represents a divine bet placed by a player
 */
export interface DivineBet {
  id: string;
  playerId: string;
  betType: DivineBetType;
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
  payoutProfile?: BetPayoutProfile;
  createdAt?: Date;
  updatedAt?: Date;
}

export type DivineBetType =
  | 'settlement_growth'
  | 'landmark_discovery'
  | 'cultural_shift'
  | 'hero_settlement_bond'
  | 'hero_location_visit'
  | 'settlement_transformation'
  | 'corruption_spread'
  | 'hero_level_milestone'
  | 'hero_death'
  | 'region_danger_change'
  | 'war_outcome'
  | 'prosperity_threshold'
  | 'magic_discovery'
  | 'pantheon_intervention';

export interface BetTargetSignal {
  label: string;
  value: string;
}

export interface BetTargetState {
  type: 'settlement' | 'hero' | 'region' | 'resource' | 'landmark';
  name: string;
  summary: string;
  signals: BetTargetSignal[];
}

export interface OddsFactor {
  label: string;
  value: string;
  effect?: string;
}

export interface BetPayoutProfile {
  stake: number;
  odds: number;
  confidence: DivineBet['confidence'];
  rawMultiplier: number;
  grossMultiplier: number;
  maximumMultiplier: number;
  grossPayout: number;
  netProfit: number;
  probabilityPercent: number;
  riskBand: 'low' | 'medium' | 'high' | 'extreme';
  summary: string;
}

export interface BetTypeSummary {
  betType: DivineBetType;
  total: number;
  stake: number;
}

export interface DivineBetSummary {
  generatedAt: string;
  total: number;
  active: number;
  won: number;
  lost: number;
  expired: number;
  activeStake: number;
  activePotentialPayout: number;
  resolvedStake: number;
  wonPayout: number;
  netResolvedFavor: number;
  winRatePercent: number | null;
  averageOdds: number;
  topBetTypes: BetTypeSummary[];
  recentActive: DivineBet[];
  recentResolved: DivineBet[];
  summary: string;
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
  eventType: DivineBetType;
  targetState?: BetTargetState | null;
  oddsFactors?: OddsFactor[];
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
  betType?: DivineBetType;
  targetId?: string;
  currentOdds: number;
  minimumStake: number;
  potentialPayout: number;
  payoutProfile?: BetPayoutProfile;
  timeframe?: number;
  confidence?: DivineBet['confidence'];
  targetState?: BetTargetState | null;
  oddsFactors?: OddsFactor[];
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

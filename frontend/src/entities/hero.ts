// F:\WebDevelopment\Mytherra\frontend\src\entities\hero.ts

export interface HeroLifecycleSummary {
  levelCategory: string;
  ageCategory: string;
  statusLabel: string;
  summary: string;
  isMilestoneLevel: boolean;
  nextMilestoneLevel?: number | null;
  levelsToMilestone?: number | null;
  featCount: number;
  mortalityPressure: string;
  alignmentDescription?: string | null;
  deathReason?: string | null;
}

export interface HeroNearbySettlement {
  id: string;
  name: string;
  type: string;
  status: string;
  population: number;
}

export interface HeroSettlementInteractionSummary {
  id: string;
  targetType: 'settlement' | 'landmark';
  targetId?: string | null;
  targetName?: string | null;
  action: string;
  interactionType: string;
  startedYear?: number | null;
  duration?: number | null;
  success?: boolean | null;
  outcomeDescription?: string | null;
}

export interface HeroRelationshipContext {
  region?: {
    id: string;
    name: string;
    status?: string | null;
    prosperity?: number | null;
    chaos?: number | null;
    dangerLevel?: number | null;
  } | null;
  settlementCount: number;
  nearbySettlements: HeroNearbySettlement[];
  peerHeroCount: number;
  recentSettlementInteractions: HeroSettlementInteractionSummary[];
  relationshipSummary: string;
}

export interface HeroRecentHistoryEvent {
  id: string;
  title: string;
  description: string;
  type: string;
  status: string;
  regionId?: string | null;
  year?: number | null;
  timestamp?: string | null;
}

export interface HeroRecentHistory {
  eventCount: number;
  recentEvents: HeroRecentHistoryEvent[];
}

export type ChampionFocus = 'quest' | 'defense' | 'research' | 'rivalry';

export interface ChampionQuest {
  focus: ChampionFocus;
  title: string;
  summary: string;
  startedYear: number;
  progress: number;
}

export interface ChampionOutcome {
  id: string;
  eventId?: string | null;
  type: string;
  title: string;
  summary: string;
  effectSummary: string;
  result: string;
  focus: ChampionFocus;
  focusLabel: string;
  year: number;
  heroId: string;
  heroName: string;
  regionId?: string | null;
  regionName?: string | null;
  legacyType?: string | null;
  legacyReason?: string | null;
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
}

export interface ChampionBettingHook {
  id: string;
  title: string;
  summary: string;
  betType: string;
  targetId: string;
  targetType: 'hero' | 'region';
  regionId?: string | null;
  minimumYears: number;
  maximumYears: number;
  confidence: 'long_shot' | 'possible' | 'likely' | 'near_certain';
}

export interface ChampionLegacyHook {
  heroId?: string | null;
  name: string;
  legacyType: string;
  reason: string;
  eventId?: string | null;
}

export interface ChampionFocusOption {
  key: ChampionFocus;
  label: string;
  summary: string;
}

export interface ChampionProfile {
  heroId: string;
  name: string;
  role: string;
  regionId?: string | null;
  regionName?: string | null;
  designatedYear: number;
  rank: number;
  bond: number;
  focus: ChampionFocus;
  focusLabel: string;
  questsCompleted: number;
  rivalryPressure: number;
  eventIds: string[];
  currentQuest?: ChampionQuest | null;
  outcomes: ChampionOutcome[];
  latestOutcome?: ChampionOutcome | null;
  lastCultivatedYear?: number | null;
  lastOutcomeYear?: number | null;
  summary: string;
}

export interface HeroChampionStatus {
  isChampion: boolean;
  profile?: ChampionProfile | null;
  rosterLimit: number;
  activeChampionCount: number;
  designationCost: number;
  cultivationCosts: Record<ChampionFocus, number>;
  focusOptions: ChampionFocusOption[];
  eligible: boolean;
  eligibilityReason?: string | null;
}

export interface ChampionStatusResponse {
  rosterLimit: number;
  activeCount: number;
  designationCost: number;
  cultivationBaseCost: number;
  focusOptions: ChampionFocusOption[];
  champions: ChampionProfile[];
  recentOutcomes: ChampionOutcome[];
  bettingHooks: ChampionBettingHook[];
  legacyHooks: ChampionLegacyHook[];
}

/**
 * Represents a hero character in the game.
 */
export interface Hero {
  id: string;
  name: string;
  regionId: string; // The region this hero is currently associated with
  role: 'scholar' | 'warrior' | 'prophet' | 'agent of change' | 'undecided';
  description: string;
  feats: string[]; // Notable accomplishments
  level?: number; // Added level
  age?: number; // Added age, as it's often displayed with heroes
  isAlive?: boolean; // Added isAlive status
  deathReason?: string; // Added death reason
  status?: 'living' | 'deceased' | 'undead' | 'ascended'; // Status for special conditions beyond alive/dead
  personalityTraits?: string[]; // Personality traits like curious, vengeful, ambitious
  alignment?: {
    good: number; // 0-100 scale for good vs evil
    chaotic: number; // 0-100 scale for chaotic vs lawful
    lastChange?: string; // Reason for the last alignment change
  };
  influenceActionCosts?: {
    guideHero?: number;
    empowerHero?: number;
    reviveHero?: number; // Added reviveHero cost
    forceNotableEvent?: number; // Added forceNotableEvent cost
  };
  lifecycleSummary?: HeroLifecycleSummary;
  relationshipContext?: HeroRelationshipContext;
  recentHistory?: HeroRecentHistory;
  championStatus?: HeroChampionStatus;
}

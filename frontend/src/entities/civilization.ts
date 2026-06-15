export type CivilizationBehaviorKey =
  | 'expansion'
  | 'defense'
  | 'trade'
  | 'rivalry'
  | 'research'
  | 'recovery';

export type CivilizationPriorityTier = 'urgent' | 'active' | 'watch' | 'quiet';

export interface CivilizationSignal {
  label: string;
  value: string;
  summary: string;
}

export interface CivilizationBehaviorOption {
  key: CivilizationBehaviorKey;
  label: string;
  summary: string;
}

export interface CivilizationBehaviorScore {
  key: CivilizationBehaviorKey;
  label: string;
  score: number;
  signals: CivilizationSignal[];
}

export interface CivilizationRegionStats {
  prosperity: number;
  chaos: number;
  dangerLevel: number;
  magicAffinity: number;
  culture: string;
  settlements: number;
  resources: number;
  heroes: number;
  landmarks: number;
  population: number;
  avgSettlementProsperity: number;
  avgSettlementDefense: number;
  productiveResources: number;
  disruptedResources: number;
}

export interface CivilizationRegionAgenda {
  regionId: string;
  regionName: string;
  dominantBehavior: CivilizationBehaviorKey;
  dominantBehaviorLabel: string;
  score: number;
  priorityTier: CivilizationPriorityTier;
  summary: string;
  signals: CivilizationSignal[];
  scores: CivilizationBehaviorScore[];
  stats: CivilizationRegionStats;
  relatedRegionIds: string[];
  relatedSettlementIds: string[];
  relatedHeroIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
}

export interface CivilizationChange {
  id: string;
  name: string;
  before: Record<string, unknown>;
  after: Record<string, unknown>;
  summary: string;
}

export interface CivilizationChanges {
  regions: CivilizationChange[];
  settlements: CivilizationChange[];
  resources: CivilizationChange[];
  heroes: CivilizationChange[];
  landmarks: CivilizationChange[];
}

export interface CivilizationDecision {
  id: string;
  year: number;
  source: 'player' | 'tick' | string;
  regionId: string;
  regionName: string;
  behavior: CivilizationBehaviorKey;
  behaviorLabel: string;
  score: number;
  priorityTier: CivilizationPriorityTier;
  summary: string;
  signalSummary: string;
  effectSummary: string;
  eventId?: string | null;
  changes: CivilizationChanges;
  relatedRegionIds: string[];
  relatedSettlementIds: string[];
  relatedHeroIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
}

export interface CivilizationStatusResponse {
  currentYear: number;
  summary: string;
  behaviorOptions: CivilizationBehaviorOption[];
  topAgenda?: CivilizationRegionAgenda | null;
  regionAgendas: CivilizationRegionAgenda[];
  recentDecisions: CivilizationDecision[];
  behaviorCounts: Record<CivilizationBehaviorKey, number>;
}

export interface CivilizationAdvanceResponse {
  success?: boolean;
  message?: string;
  decision?: CivilizationDecision | null;
  status?: CivilizationStatusResponse;
}

export interface CivilizationTickSummary {
  processed?: number;
  decisions?: CivilizationDecision[];
  events?: number;
  errors?: Array<string | { regionId?: string | null; message?: string }>;
}

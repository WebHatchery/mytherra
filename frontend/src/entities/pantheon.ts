export type PantheonDomain = 'prosperity' | 'strife' | 'secrets' | 'entropy' | string;

export type PantheonPressureTier = 'dominant' | 'active' | 'watch' | 'quiet' | string;

export type PantheonCounterplayMode = 'appease' | 'challenge';

export type PantheonEventType =
  | 'pantheon_intervention'
  | 'pantheon_counterplay'
  | 'pantheon_relationship_arc'
  | string;

export interface PantheonSignal {
  label: string;
  value: string;
  summary: string;
}

export interface PantheonChange {
  id: string;
  name: string;
  before: Record<string, unknown>;
  after: Record<string, unknown>;
  summary: string;
}

export interface PantheonChanges {
  regions: PantheonChange[];
  settlements: PantheonChange[];
  resources: PantheonChange[];
  heroes: PantheonChange[];
  landmarks: PantheonChange[];
}

export interface PantheonIntervention {
  id: string;
  year: number;
  deityId: string;
  deityName: string;
  domain: PantheonDomain;
  alignment: string;
  strategy: string;
  pressureScore: number;
  pressureTier: PantheonPressureTier;
  targetRegionId?: string | null;
  targetRegionName?: string | null;
  title: string;
  summary: string;
  eventId?: string | null;
  changes: PantheonChanges;
  relatedRegionIds: string[];
  relatedSettlementIds: string[];
  relatedHeroIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
}

export interface PantheonDeity {
  id: string;
  name: string;
  domain: PantheonDomain;
  alignment: string;
  goal: string;
  strategy: string;
  allyId?: string | null;
  rivalId?: string | null;
  pressureScore: number;
  pressureTier: PantheonPressureTier;
  targetRegionId?: string | null;
  targetRegionName?: string | null;
  interventionCount: number;
  latestIntervention?: PantheonIntervention | null;
  counterplay?: PantheonCounterplayState | null;
}

export interface PantheonPressure {
  deityId: string;
  deityName: string;
  domain: PantheonDomain;
  pressureScore: number;
  pressureTier: PantheonPressureTier;
  targetRegionId?: string | null;
  targetRegionName?: string | null;
  summary: string;
  signals: PantheonSignal[];
  counterplay?: PantheonCounterplayState | null;
  relatedRegionIds: string[];
  relatedSettlementIds: string[];
  relatedHeroIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
}

export interface PantheonRelationship {
  sourceId: string;
  sourceName: string;
  targetId: string;
  targetName: string;
  stance: 'ally' | 'rival' | string;
  tension?: number;
  stage?: string | null;
  summary: string;
}

export interface PantheonPoliticalEscalation {
  sourceId: string;
  sourceName: string;
  targetId: string;
  targetName: string;
  stance: 'ally' | 'rival' | string;
  tension: number;
  stage: string;
  pressureScore: number;
  targetRegionId?: string | null;
  targetRegionName?: string | null;
  interventionCount: number;
  betType?: string | null;
  summary: string;
}

export interface PantheonRelationshipArc {
  id: string;
  arcKey: string;
  year: number;
  sourceId: string;
  sourceName: string;
  targetId: string;
  targetName: string;
  stance: 'ally' | 'rival' | string;
  stage: string;
  momentum: number;
  tension: number;
  pressureScore: number;
  targetRegionId?: string | null;
  targetRegionName?: string | null;
  startedYear: number;
  lastAdvancedYear: number;
  stepCount: number;
  title: string;
  summary: string;
  eventId?: string | null;
  eventIds: string[];
  changes: PantheonChanges;
  relatedRegionIds: string[];
  relatedSettlementIds: string[];
  relatedHeroIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
  nextPressure?: string | null;
}

export interface PantheonPoliticsStatus {
  summary: string;
  escalations: PantheonPoliticalEscalation[];
}

export interface PantheonBettingHook {
  id: string;
  title: string;
  summary: string;
  betType: 'pantheon_intervention' | string;
  targetId: string;
  regionId?: string | null;
  regionName?: string | null;
  deityId: string;
  deityName: string;
  domain: PantheonDomain;
  pressureScore: number;
  pressureTier: PantheonPressureTier;
  confidence: string;
  minimumYears: number;
  maximumYears: number;
  relationshipStance?: string | null;
  politicalStage?: string | null;
  riskSummary?: string | null;
}

export interface PantheonCounterplayState {
  deityId: string;
  deityName: string;
  appeasement: number;
  defiance: number;
  pressureReduction: number;
  lastActionYear?: number | null;
  lastMode?: PantheonCounterplayMode | string | null;
  summary: string;
  relationshipSummary: string;
}

export interface PantheonCounterplayAction {
  id: string;
  year: number;
  deityId: string;
  deityName: string;
  domain: PantheonDomain;
  mode: PantheonCounterplayMode | string;
  cost: number;
  targetRegionId?: string | null;
  targetRegionName?: string | null;
  title: string;
  summary: string;
  eventId?: string | null;
}

export interface PantheonCounterplayStatus {
  summary: string;
  costs: Record<PantheonCounterplayMode, number>;
  byDeity: PantheonCounterplayState[];
  recentActions: PantheonCounterplayAction[];
}

export interface PantheonCounterplayPayload {
  mode: PantheonCounterplayMode;
}

export interface PantheonCounterplayResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  action?: PantheonCounterplayAction | null;
  status?: PantheonStatusResponse;
}

export interface PantheonStatusResponse {
  currentYear: number;
  summary: string;
  deities: PantheonDeity[];
  pressure: PantheonPressure[];
  topActor?: PantheonDeity | null;
  recentInterventions: PantheonIntervention[];
  relationships: PantheonRelationship[];
  politics?: PantheonPoliticsStatus | null;
  relationshipArcs: PantheonRelationshipArc[];
  bettingHooks: PantheonBettingHook[];
  counterplay?: PantheonCounterplayStatus | null;
}

export interface PantheonTickSummary {
  processed?: number;
  changed?: number;
  events?: number;
  interventions?: PantheonIntervention[];
  arcs?: PantheonRelationshipArc[];
  errors?: Array<string | { deityId?: string | null; regionId?: string | null; message?: string }>;
}

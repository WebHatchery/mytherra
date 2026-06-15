import { Region } from '../entities/region';
import {
  ChampionFocus,
  ChampionFocusOption,
  ChampionBettingHook,
  ChampionLegacyHook,
  ChampionOutcome,
  ChampionProfile,
  ChampionStatusResponse,
  Hero,
  HeroChampionStatus,
} from '../entities/hero';
import { GameEvent } from '../entities/event';
import { Settlement } from '../entities/settlement';
import { Building } from '../entities/building';
import { Landmark } from '../entities/landmark';
import { ResourceNode } from '../entities/resourceNode';
import {
  ArtifactFocus,
  ArtifactFocusOption,
  ArtifactHistoryEntry,
  ArtifactOwnerType,
  ArtifactRiskTier,
  ArtifactStatus,
  ArtifactStatusResponse,
  DivineArtifact,
} from '../entities/artifact';
import {
  WeatherChangeDelta,
  WeatherEntityChange,
  WeatherInfluenceEntry,
  WeatherIntensityKey,
  WeatherIntensityOption,
  WeatherPatternKey,
  WeatherPatternOption,
  WeatherRiskOutcome,
  WeatherSnapshot,
  WeatherStatusResponse,
} from '../entities/weather';
import {
  TemporalOmenConfidence,
  TemporalOmenEntry,
  TemporalOmenHorizon,
  TemporalOmenHorizonOption,
  TemporalOmenPrediction,
  TemporalOmenRiskBand,
  TemporalOmenSignal,
  TemporalOmenStatusResponse,
  TemporalOmenTargetOption,
  TemporalOmenTargetType,
  TemporalOmenTone,
} from '../entities/temporalOmen';
import {
  MagicDiscoveryBettingHook,
  MagicDiscoveryHistoryEntry,
  MagicDiscoveryPath,
  MagicDiscoveryPathOption,
  MagicDiscoveryPathStatus,
  MagicDiscoverySignal,
  MagicDiscoveryStatusResponse,
  MagicDiscoverySuggestedTarget,
  MagicDiscoveryTarget,
  MagicDiscoveryTargetOption,
  MagicDiscoveryTargetType,
} from '../entities/magicDiscovery';
import {
  MythCandidate,
  MythEffectTarget,
  MythEffects,
  MythologyStatusResponse,
  MythStrength,
  MythTypeOption,
  PromotedMyth,
} from '../entities/mythology';
import {
  CivilizationAdvanceResponse,
  CivilizationBehaviorKey,
  CivilizationBehaviorOption,
  CivilizationBehaviorScore,
  CivilizationChanges,
  CivilizationDecision,
  CivilizationPriorityTier,
  CivilizationRegionAgenda,
  CivilizationRegionStats,
  CivilizationSignal,
  CivilizationStatusResponse,
  CivilizationTickSummary,
} from '../entities/civilization';
import {
  PantheonChange,
  PantheonChanges,
  PantheonBettingHook,
  PantheonCounterplayAction,
  PantheonCounterplayPayload,
  PantheonCounterplayResponse,
  PantheonCounterplayState,
  PantheonCounterplayStatus,
  PantheonDeity,
  PantheonIntervention,
  PantheonPoliticalEscalation,
  PantheonPoliticsStatus,
  PantheonPressure,
  PantheonRelationship,
  PantheonSignal,
  PantheonStatusResponse,
  PantheonTickSummary,
} from '../entities/pantheon';
import {
  BetPayoutProfile,
  DivineBet,
  DivineBetSummary,
  SpeculationEvent,
  BettingOdds,
} from '../entities/divineBet';
import { apiClient } from './apiClient';
import { ApiError } from './types';

export interface GameStatus {
  currentYear: number;
  divineFavor: number; // Added divineFavor
  eraPressure?: EraPressureSummary;
  eraLegacy?: EraLegacySummary;
  eraTransition?: EraTransitionSummary;
  eraComparison?: EraComparisonSummary;
  artifacts?: ArtifactStatusResponse;
  temporalOmens?: TemporalOmenStatusResponse;
  weather?: WeatherStatusResponse;
  magicDiscovery?: MagicDiscoveryStatusResponse;
  mythology?: MythologyStatusResponse;
  civilization?: CivilizationStatusResponse;
  champions?: ChampionStatusResponse;
  pantheon?: PantheonStatusResponse;
  simulation?: {
    enabled: boolean;
    lastTickAt: string | null;
    lastTickResult: GameTickResult | null;
    queue: {
      jobs: number | null;
      failedJobs: number | null;
      available: boolean;
      error?: string;
    };
  };
}

export interface EraPressureSignal {
  label: string;
  value: string;
}

export interface EraPressureTrigger {
  code: string;
  label: string;
  score: number;
  tier: string;
  summary: string;
  signals: EraPressureSignal[];
  relatedRegionIds?: string[];
  relatedHeroIds?: string[];
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
  relatedResourceIds?: string[];
}

export interface EraPressureSummary {
  currentEra: number;
  currentYear: number;
  eraYear: number;
  eraLengthYears: number;
  pressureScore: number;
  tier: string;
  tierLabel: string;
  summary: string;
  highestTrigger?: EraPressureTrigger;
  triggers: EraPressureTrigger[];
  warnings: string[];
  eventId?: string;
  relatedRegionIds?: string[];
  relatedHeroIds?: string[];
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
  relatedResourceIds?: string[];
}

export interface EraLegacySignal {
  label: string;
  value: string;
}

export interface EraLegacyHero {
  heroId: string;
  name: string;
  regionId?: string | null;
  regionName?: string | null;
  role?: string;
  level?: number;
  status?: string;
  legacyType: string;
  strength: string;
  reason: string;
  signals?: EraLegacySignal[];
}

export interface EraLegacyBloodline {
  id: string;
  name: string;
  sourceHeroId: string;
  regionId?: string | null;
  regionName?: string | null;
  settlementId?: string | null;
  settlementName?: string | null;
  lineageType: string;
  strength: string;
  reason: string;
  signals?: EraLegacySignal[];
}

export interface EraLegacyLandmark {
  landmarkId: string;
  name: string;
  regionId?: string | null;
  regionName?: string | null;
  type?: string;
  status?: string;
  legacyType: string;
  strength: string;
  reason: string;
  signals?: EraLegacySignal[];
}

export interface EraLegacyScar {
  id: string;
  targetType: 'region' | 'settlement' | 'landmark' | 'resource';
  targetId: string;
  targetName: string;
  regionId?: string | null;
  settlementId?: string | null;
  landmarkId?: string | null;
  resourceId?: string | null;
  scarType: string;
  severity: string;
  reason: string;
}

export interface EraLegacyMyth {
  eventId: string;
  title: string;
  type: string;
  year?: number | null;
  regionId?: string | null;
  mythType: string;
  reason: string;
  relatedRegionIds?: string[];
  relatedHeroIds?: string[];
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
  relatedResourceIds?: string[];
}

export interface EraLegacyBet {
  betId: string;
  description: string;
  betType: string;
  targetId: string;
  stake: number;
  placedYear: number;
  timeframe: number;
  expiresYear: number;
  yearsRemaining: number;
  spansNextEra: boolean;
  reason: string;
}

export interface EraLegacySummary {
  currentEra: number;
  currentYear: number;
  eraYear: number;
  eraEndYear: number;
  nextEraYear: number;
  yearsUntilNextEra: number;
  continuityScore: number;
  readinessTier: string;
  readinessLabel: string;
  summary: string;
  heroLegacies: EraLegacyHero[];
  bloodlineSeeds: EraLegacyBloodline[];
  landmarkLegacies: EraLegacyLandmark[];
  worldScars: EraLegacyScar[];
  carriedMyths: EraLegacyMyth[];
  eraSpanningBets: EraLegacyBet[];
  relatedRegionIds?: string[];
  relatedHeroIds?: string[];
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
  relatedResourceIds?: string[];
}

export interface EraTransitionPreview {
  completedEra: number;
  nextEra: number;
  nextEraYear: number;
  pressureTrigger: string;
  readinessLabel: string;
  counts: {
    regions: number;
    settlements: number;
    heroes: number;
    landmarks: number;
    resources: number;
    heroLegacies: number;
    bloodlineSeeds: number;
    landmarkLegacies: number;
    worldScars: number;
    carriedMyths: number;
    eraSpanningBets: number;
  };
  carryForward: {
    heroes: string[];
    landmarks: string[];
    scars: string[];
    myths: string[];
  };
}

export interface EraComparisonSignal {
  label: string;
  value: string;
}

export interface EraComparisonMetricDelta {
  key: string;
  label: string;
  before: number;
  after: number;
  delta: number;
  direction: 'up' | 'down' | 'flat';
  unit: string;
}

export interface EraComparisonWorldSnapshot {
  year: number;
  era: number;
  regions: {
    count: number;
    avgProsperity: number;
    avgChaos: number;
    avgDanger: number;
    avgMagic: number;
    totalPopulation: number;
    statusDistribution: Record<string, number>;
  };
  settlements: {
    count: number;
    population: number;
    avgProsperity: number;
    avgDefensibility: number;
    statusDistribution: Record<string, number>;
  };
  heroes: {
    count: number;
    living: number;
    fallen: number;
    avgLevel: number;
    legendary: number;
    statusDistribution: Record<string, number>;
  };
  landmarks: {
    count: number;
    avgMagic: number;
    avgDanger: number;
    statusDistribution: Record<string, number>;
  };
  resources: {
    count: number;
    avgOutput: number;
    avgEffectiveOutput: number;
    disrupted: number;
    statusDistribution: Record<string, number>;
  };
  bets: {
    count: number;
    active: number;
    resolved: number;
    activeStake: number;
    statusDistribution: Record<string, number>;
  };
  signals: EraComparisonSignal[];
}

export interface EraComparisonLatest {
  id?: string | null;
  completedEra: number;
  nextEra: number;
  previousYear: number;
  currentYear: number;
  transitionedAt?: string | null;
  eventId?: string | null;
  summary: string;
  beforeSnapshot: EraComparisonWorldSnapshot;
  afterSnapshot: EraComparisonWorldSnapshot;
  transitionDelta: EraComparisonMetricDelta[];
  currentDelta: EraComparisonMetricDelta[];
}

export interface EraComparisonSummary {
  currentEra: number;
  currentYear: number;
  generatedAt: string;
  summary: string;
  historyCount: number;
  currentSnapshot: EraComparisonWorldSnapshot;
  latestComparison: EraComparisonLatest | null;
}

export interface EraGeneratedEntity {
  id: string;
  name: string;
  regionId?: string | null;
  regionName?: string | null;
  settlementId?: string | null;
  type?: string;
  role?: string;
  level?: number;
  population?: number;
  output?: number;
  status?: string;
  lineageSource?: string | null;
  summary: string;
}

export interface EraGeneratedContent {
  eventId?: string | null;
  summary?: string | null;
  settlements: EraGeneratedEntity[];
  heroes: EraGeneratedEntity[];
  landmarks: EraGeneratedEntity[];
  resources: EraGeneratedEntity[];
}

export interface EraTransitionHistoryEntry {
  id: string;
  completedEra: number;
  nextEra: number;
  previousYear: number;
  currentYear: number;
  transitionedAt: string;
  triggerReason: string;
  forced: boolean;
  eventId?: string;
  pressureScore: number;
  continuityScore: number;
  summary: string;
  carried: {
    heroes: string[];
    landmarks: string[];
    scars: string[];
    myths: string[];
  };
  changeCounts: {
    regions: number;
    settlements: number;
    heroes: number;
    landmarks: number;
    resources: number;
    carriedBets: number;
    expiredBets: number;
    generatedSettlements?: number;
    generatedHeroes?: number;
    generatedLandmarks?: number;
    generatedResources?: number;
  };
  generated?: EraGeneratedContent;
  beforeSnapshot?: EraComparisonWorldSnapshot;
  afterSnapshot?: EraComparisonWorldSnapshot;
  transitionDelta?: EraComparisonMetricDelta[];
  comparisonSummary?: string;
}

export interface EraTransitionResult {
  currentEra: number;
  currentYear: number;
  eligible: true;
  transitionEra: number;
  completedEra: number;
  nextEra: number;
  nextEraYear: number;
  eventId?: string;
  forced: boolean;
  triggerReason: string;
  summary: string;
  historyEntry: EraTransitionHistoryEntry;
  history: EraTransitionHistoryEntry[];
}

export interface EraTransitionSummary {
  currentEra: number;
  currentYear: number;
  eraYear: number;
  lastCompletedEra: number;
  transitionEra: number;
  nextEra: number;
  eraEndYear: number;
  nextEraYear: number;
  eligible: boolean;
  requiresForce: boolean;
  triggerReason: string;
  summary: string;
  pressureScore: number;
  continuityScore: number;
  preview: EraTransitionPreview;
  history: EraTransitionHistoryEntry[];
  completedEra?: number;
  eventId?: string;
  forced?: boolean;
  historyEntry?: EraTransitionHistoryEntry;
}

export interface GameTickChange {
  id?: string;
  name?: string;
  type?: string;
  regionId?: string | null;
  eventId?: string;
  eventIds?: string[];
  summary?: string;
  reason?: string;
  before?: Record<string, string | number | boolean | null>;
  after?: Record<string, string | number | boolean | null>;
}

export interface GameTickEntitySummary {
  processed?: number;
  changed?: number;
  events?: number;
  changes?: GameTickChange[];
  errors?: Array<string | { id?: string; message?: string }>;
}

export interface GameTickBetResolution {
  id?: string;
  description?: string;
  targetId?: string;
  betType?: string;
  status?: 'won' | 'lost' | 'expired';
  notes?: string;
  resolvedYear?: number;
  regionId?: string | null;
  heroId?: string | null;
  payout?: number;
  eventId?: string;
}

export interface GameTickBetSummary {
  processed?: number;
  won?: number;
  lost?: number;
  expired?: number;
  resolved?: GameTickBetResolution[];
  errors?: Array<string | { id?: string; message?: string }>;
}

export interface GameTickFavorSummary {
  before?: number;
  after?: number;
  recovered?: number;
}

export interface GameTickChampionSummary {
  processed?: number;
  changed?: number;
  events?: number;
  outcomes?: ChampionOutcome[];
  errors?: Array<string | { id?: string; message?: string }>;
}

export interface GameTickDivineToolConsequence {
  id?: string;
  tool?: 'artifact' | 'weather' | 'omen' | string;
  title?: string;
  summary?: string;
  eventId?: string;
  year?: number;
  artifactId?: string;
  artifactName?: string;
  weatherId?: string;
  omenId?: string;
  regionId?: string | null;
  regionName?: string;
  targetType?: string;
  targetId?: string | null;
  targetName?: string;
  outcome?: string;
  predictedRiskScore?: number;
  actualRiskScore?: number;
}

export interface GameTickDivineToolSection {
  processed?: number;
  changed?: number;
  events?: number;
  consequences?: GameTickDivineToolConsequence[];
  followUps?: GameTickDivineToolConsequence[];
  errors?: Array<string | { id?: string; message?: string; tool?: string }>;
}

export interface GameTickDivineToolsSummary {
  processed?: number;
  changed?: number;
  events?: number;
  artifacts?: GameTickDivineToolSection | null;
  weather?: GameTickDivineToolSection | null;
  omens?: GameTickDivineToolSection | null;
  consequences?: GameTickDivineToolConsequence[];
  errors?: Array<string | { id?: string; message?: string; tool?: string }>;
}

export interface GameTickResult {
  startedAt?: string;
  completedAt?: string | null;
  previousYear?: number;
  currentYear?: number;
  advancedYear?: boolean;
  regions?: GameTickEntitySummary;
  settlements?: GameTickEntitySummary;
  resources?: GameTickEntitySummary;
  heroes?: GameTickEntitySummary;
  champions?: GameTickChampionSummary;
  divineTools?: GameTickDivineToolsSummary;
  civilization?: CivilizationTickSummary;
  pantheon?: PantheonTickSummary;
  bets?: GameTickBetSummary;
  divineFavor?: GameTickFavorSummary;
  eraPressure?: EraPressureSummary | null;
  eraLegacy?: EraLegacySummary | null;
  eraTransition?: EraTransitionSummary | EraTransitionResult | null;
  errors?: string[];
}

export interface EntityHistorySignal {
  label: string;
  value: string;
}

export interface EntityHistoryCurrentState {
  summary: string;
  signals: EntityHistorySignal[];
}

export interface EntityHistoryEvent {
  id: string;
  title: string;
  description: string;
  type: string;
  status: string;
  regionId?: string | null;
  relatedRegionIds?: string[];
  relatedHeroIds?: string[];
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
  relatedResourceIds?: string[];
  year?: number | null;
  timestamp?: string;
  matchType: 'direct' | 'region_context';
}

export interface EntityHistoryItem {
  id: string;
  name: string;
  entityType: 'region' | 'settlement' | 'landmark' | 'resource' | 'hero';
  regionId?: string | null;
  currentState: EntityHistoryCurrentState;
  historyStatus: 'direct' | 'direct_with_region_context' | 'region_context' | 'none';
  historyNote: string;
  directEventCount: number;
  shownEventCount: number;
  lastEventYear?: number | null;
  lastEventTitle?: string | null;
  lastEventDescription?: string | null;
  recentEvents: EntityHistoryEvent[];
}

export interface BetHistoryItem {
  id: string;
  description: string;
  betType: DivineBet['betType'];
  targetId: string;
  targetName?: string | null;
  targetType?: string | null;
  status: DivineBet['status'];
  placedYear: number;
  resolvedYear?: number | null;
  currentOdds: number;
  stake: number;
  potentialPayout: number;
  resolutionNotes?: string | null;
}

export interface BetHistorySummary {
  total: number;
  active: number;
  won: number;
  lost: number;
  expired: number;
  recentActive: BetHistoryItem[];
  recentResolved: BetHistoryItem[];
}

export interface EntityHistorySummaryResponse {
  generatedAt: string;
  limits: {
    entitiesPerType: number;
    eventsPerEntity: number;
    regionId?: string | null;
  };
  coverageNotes: Record<string, string>;
  entities: {
    regions: EntityHistoryItem[];
    settlements: EntityHistoryItem[];
    landmarks: EntityHistoryItem[];
    resources: EntityHistoryItem[];
    heroes: EntityHistoryItem[];
  };
  bets: BetHistorySummary;
}

export interface GameEventFilters {
  regionId?: string;
  heroId?: string;
  settlementId?: string;
  landmarkId?: string;
  resourceId?: string;
  era?: string;
  type?: string;
  status?: string;
}

export interface ApiErrorBody {
  message?: string;
}

export interface ChampionActionResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  champion?: ChampionProfile | null;
  status?: ChampionStatusResponse;
  target?: Hero | Record<string, unknown> | null;
}

export interface CreateArtifactPayload {
  name: string;
  focus: ArtifactFocus;
  targetType?: ArtifactOwnerType;
  targetId?: string | null;
}

export interface TransferArtifactPayload {
  targetType: ArtifactOwnerType;
  targetId?: string | null;
}

export interface ArtifactActionResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  artifact?: DivineArtifact | null;
  status?: ArtifactStatusResponse;
  riskOutcome?: {
    type: 'none' | 'corruption' | 'theft';
    summary: string;
    eventId?: string | null;
  };
}

export interface WeatherNudgePayload {
  regionId: string;
  pattern: WeatherPatternKey;
  intensity: WeatherIntensityKey;
}

export interface WeatherActionResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  influence?: WeatherInfluenceEntry | null;
  status?: WeatherStatusResponse;
}

export interface TemporalOmenReadPayload {
  targetType: TemporalOmenTargetType;
  targetId?: string | null;
  horizon: TemporalOmenHorizon;
}

export interface TemporalOmenActionResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  omen?: TemporalOmenEntry | null;
  status?: TemporalOmenStatusResponse;
}

export interface MagicResearchPayload {
  targetType: MagicDiscoveryTargetType;
  targetId: string;
  path?: string;
}

export interface MagicResearchResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  path?: MagicDiscoveryPath | null;
  research?: {
    target: MagicDiscoveryTarget;
    evidenceScore: number;
    progressGain: number;
    signals: MagicDiscoverySignal[];
    eventId?: string | null;
  } | null;
  status?: MagicDiscoveryStatusResponse;
}

export interface PromoteMythPayload {
  eventId: string;
}

export interface PromoteMythResponse {
  success?: boolean;
  message?: string;
  cost?: number;
  remainingDivineFavor?: number;
  myth?: PromotedMyth | null;
  status?: MythologyStatusResponse;
}

export interface AdvanceCivilizationPayload {
  regionId?: string;
}

interface WrappedApiResponse<T> {
  success: boolean;
  data: T;
  error?: string;
}

const isWrappedApiResponse = <T>(value: unknown): value is WrappedApiResponse<T> => {
  if (!value || typeof value !== 'object') {
    return false;
  }
  return 'success' in value && 'data' in value;
};

const isAxiosLikeError = (
  error: unknown
): error is {
  code?: string;
  message?: string;
  response?: {
    status?: number;
    data?: { message?: string };
  };
} => {
  return typeof error === 'object' && error !== null;
};

export const apiService = {
  get: <T>(path: string) => fetchData<T>(path),
  post: <T, R>(path: string, body: T) => postData<T, R>(path, body),
};

// Helper function to fetch data from the backend API
async function fetchData<T>(path: string): Promise<T> {
  // Add timeout controller
  const controller = new AbortController();
  const id = setTimeout(() => controller.abort(), 10000); // 10s timeout

  try {
    const response = await apiClient.get<T>(path, { signal: controller.signal });
    clearTimeout(id);

    // The apiClient already handles 401s via the interceptor, but we still
    // return the data in the expected format for this legacy wrapper.

    // Check if response is wrapped in { success: boolean, data: T } format natively
    const data = response.data;
    if (isWrappedApiResponse<T>(data)) {
      if (!data.success) {
        throw new ApiError(data.error || 'API Error', 500);
      }
      return data.data;
    }

    // Otherwise return the parsed response directly
    return data as T;
  } catch (error: unknown) {
    if (
      isAxiosLikeError(error) &&
      (error.code === 'ECONNABORTED' || error.message === 'canceled')
    ) {
      throw new Error(`Request timeout for ${path}`);
    }

    // If it's already an ApiError, just throw it
    if (error instanceof ApiError) {
      throw error;
    }

    // Convert Axios errors to standard errors to match legacy behavior
    let errorMessage = `Failed to fetch ${path}`;
    if (isAxiosLikeError(error) && error.response) {
      errorMessage = error.response.data?.message || `Status ${error.response.status}`;
      if (error.response.status === 401) {
        throw new Error('AUTHENTICATION_REQUIRED');
      }
    }
    throw new Error(errorMessage);
  }
}

// Helper function to post data to the backend API
async function postData<T, R>(path: string, body: T): Promise<R> {
  try {
    const response = await apiClient.post<R>(path, body);

    // Check if response is wrapped natively
    const data = response.data;
    if (isWrappedApiResponse<R>(data)) {
      if (!data.success) {
        throw new ApiError(data.error || 'API Error', 500);
      }
      return data.data;
    }

    return data as R;
  } catch (error: unknown) {
    if (error instanceof ApiError) {
      throw error;
    }

    let errorMessage = `Failed to post to ${path}`;
    if (isAxiosLikeError(error) && error.response) {
      if (error.response.status === 401) {
        console.warn('Authentication required (401) - handled by global interceptor');
        return {} as R;
      }
      errorMessage = error.response.data?.message || `Status ${error.response.status}`;
    }
    throw new Error(errorMessage);
  }
}

async function putData<T, R>(path: string, body: T): Promise<R> {
  try {
    const response = await apiClient.put<R>(path, body);
    const data = response.data;
    if (isWrappedApiResponse<R>(data)) {
      if (!data.success) {
        throw new ApiError(data.error || 'API Error', 500);
      }
      return data.data;
    }

    return data as R;
  } catch (error: unknown) {
    if (error instanceof ApiError) {
      throw error;
    }

    let errorMessage = `Failed to put to ${path}`;
    if (isAxiosLikeError(error) && error.response) {
      errorMessage = error.response.data?.message || `Status ${error.response.status}`;
    }
    throw new Error(errorMessage);
  }
}

const isRecordValue = (value: unknown): value is Record<string, unknown> => {
  return typeof value === 'object' && value !== null && !Array.isArray(value);
};

const asStringArray = (value: unknown): string[] => {
  if (Array.isArray(value)) return value.filter((item): item is string => typeof item === 'string');
  return [];
};

const normalizeRegion = (value: Region | Record<string, unknown>): Region => {
  const source = value as Record<string, unknown>;
  return {
    ...(value as Region),
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    color: String(source.color ?? '#64748b'),
    prosperity: Number(source.prosperity ?? 0),
    chaos: Number(source.chaos ?? 0),
    magicAffinity: Number(source.magicAffinity ?? source.magic_affinity ?? 0),
    status: String(source.status ?? 'peaceful') as Region['status'],
    eventIds: asStringArray(source.eventIds ?? source.event_ids),
    populationTotal:
      source.populationTotal !== undefined || source.population_total !== undefined
        ? Number(source.populationTotal ?? source.population_total)
        : undefined,
    regionalTraits: asStringArray(source.regionalTraits ?? source.regional_traits),
    climateType: (source.climateType ?? source.climate_type) as Region['climateType'],
    tradeRoutes: asStringArray(source.tradeRoutes ?? source.trade_routes),
    culturalInfluence:
      typeof (source.culturalInfluence ?? source.cultural_influence) === 'string'
        ? String(source.culturalInfluence ?? source.cultural_influence)
        : undefined,
    divineResonance:
      source.divineResonance !== undefined || source.divine_resonance !== undefined
        ? Number(source.divineResonance ?? source.divine_resonance)
        : undefined,
    dangerLevel:
      source.dangerLevel !== undefined || source.danger_level !== undefined
        ? Number(source.dangerLevel ?? source.danger_level)
        : undefined,
    tags: asStringArray(source.tags),
    influenceActionCosts: isRecordValue(source.influenceActionCosts)
      ? (source.influenceActionCosts as Region['influenceActionCosts'])
      : undefined,
    influenceEffectiveness: isRecordValue(
      source.influenceEffectiveness ?? source.influence_effectiveness
    )
      ? ((source.influenceEffectiveness ??
          source.influence_effectiveness) as Region['influenceEffectiveness'])
      : undefined,
  };
};

const normalizeNullableNumber = (value: unknown): number | null => {
  if (value === undefined || value === null || value === '') return null;
  return Number(value);
};

const normalizeHeroLifecycleSummary = (value: unknown): Hero['lifecycleSummary'] => {
  if (!isRecordValue(value)) return undefined;

  return {
    levelCategory: String(value.levelCategory ?? value.level_category ?? ''),
    ageCategory: String(value.ageCategory ?? value.age_category ?? ''),
    statusLabel: String(value.statusLabel ?? value.status_label ?? ''),
    summary: String(value.summary ?? ''),
    isMilestoneLevel: Boolean(value.isMilestoneLevel ?? value.is_milestone_level),
    nextMilestoneLevel: normalizeNullableNumber(
      value.nextMilestoneLevel ?? value.next_milestone_level
    ),
    levelsToMilestone: normalizeNullableNumber(
      value.levelsToMilestone ?? value.levels_to_milestone
    ),
    featCount: Number(value.featCount ?? value.feat_count ?? 0),
    mortalityPressure: String(value.mortalityPressure ?? value.mortality_pressure ?? ''),
    alignmentDescription:
      typeof (value.alignmentDescription ?? value.alignment_description) === 'string'
        ? String(value.alignmentDescription ?? value.alignment_description)
        : null,
    deathReason:
      typeof (value.deathReason ?? value.death_reason) === 'string'
        ? String(value.deathReason ?? value.death_reason)
        : null,
  };
};

const normalizeHeroRelationshipContext = (value: unknown): Hero['relationshipContext'] => {
  if (!isRecordValue(value)) return undefined;

  const regionSource = value.region;
  const nearbySettlementValues = value.nearbySettlements ?? value.nearby_settlements;
  const nearbySettlements = Array.isArray(nearbySettlementValues)
    ? nearbySettlementValues.filter(isRecordValue).map(settlement => ({
        id: String(settlement.id ?? ''),
        name: String(settlement.name ?? ''),
        type: String(settlement.type ?? ''),
        status: String(settlement.status ?? ''),
        population: Number(settlement.population ?? 0),
      }))
    : [];
  const recentSettlementInteractionValues =
    value.recentSettlementInteractions ?? value.recent_settlement_interactions;
  const recentSettlementInteractions = Array.isArray(recentSettlementInteractionValues)
    ? recentSettlementInteractionValues.filter(isRecordValue).map(interaction => ({
        id: String(interaction.id ?? ''),
        targetType: String(interaction.targetType ?? interaction.target_type ?? 'settlement') as
          | 'settlement'
          | 'landmark',
        targetId:
          (interaction.targetId ?? interaction.target_id)
            ? String(interaction.targetId ?? interaction.target_id)
            : null,
        targetName:
          (interaction.targetName ?? interaction.target_name)
            ? String(interaction.targetName ?? interaction.target_name)
            : null,
        action: String(interaction.action ?? ''),
        interactionType: String(interaction.interactionType ?? interaction.interaction_type ?? ''),
        startedYear: normalizeNullableNumber(interaction.startedYear ?? interaction.started_year),
        duration: normalizeNullableNumber(interaction.duration),
        success:
          interaction.success === undefined || interaction.success === null
            ? null
            : Boolean(interaction.success),
        outcomeDescription:
          (interaction.outcomeDescription ?? interaction.outcome_description)
            ? String(interaction.outcomeDescription ?? interaction.outcome_description)
            : null,
      }))
    : [];

  return {
    region: isRecordValue(regionSource)
      ? {
          id: String(regionSource.id ?? ''),
          name: String(regionSource.name ?? ''),
          status: typeof regionSource.status === 'string' ? String(regionSource.status) : null,
          prosperity: normalizeNullableNumber(regionSource.prosperity),
          chaos: normalizeNullableNumber(regionSource.chaos),
          dangerLevel: normalizeNullableNumber(
            regionSource.dangerLevel ?? regionSource.danger_level
          ),
        }
      : null,
    settlementCount: Number(value.settlementCount ?? value.settlement_count ?? 0),
    nearbySettlements,
    peerHeroCount: Number(value.peerHeroCount ?? value.peer_hero_count ?? 0),
    recentSettlementInteractions,
    relationshipSummary: String(value.relationshipSummary ?? value.relationship_summary ?? ''),
  };
};

const normalizeHeroRecentHistory = (value: unknown): Hero['recentHistory'] => {
  if (!isRecordValue(value)) return undefined;

  const recentEventValues = value.recentEvents ?? value.recent_events;
  const recentEvents = Array.isArray(recentEventValues)
    ? recentEventValues.filter(isRecordValue).map(event => ({
        id: String(event.id ?? ''),
        title: String(event.title ?? ''),
        description: String(event.description ?? ''),
        type: String(event.type ?? ''),
        status: String(event.status ?? ''),
        regionId:
          (event.regionId ?? event.region_id) ? String(event.regionId ?? event.region_id) : null,
        year: normalizeNullableNumber(event.year),
        timestamp: event.timestamp ? String(event.timestamp) : null,
      }))
    : [];

  return {
    eventCount: Number(value.eventCount ?? value.event_count ?? recentEvents.length),
    recentEvents,
  };
};

const normalizeChampionFocus = (value: unknown): ChampionFocus => {
  return value === 'defense' || value === 'research' || value === 'rivalry' ? value : 'quest';
};

const normalizeChampionFocusOption = (value: unknown): ChampionFocusOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeChampionFocus(source.key);

  return {
    key,
    label: String(source.label ?? key),
    summary: String(source.summary ?? ''),
  };
};

const defaultChampionFocusOptions = (): ChampionFocusOption[] => [
  {
    key: 'quest',
    label: 'Quest',
    summary: 'Pushes the champion into a public trial.',
  },
  {
    key: 'defense',
    label: 'Defense',
    summary: 'Binds the champion to protect their region.',
  },
  {
    key: 'research',
    label: 'Research',
    summary: 'Guides the champion toward hidden lore.',
  },
  {
    key: 'rivalry',
    label: 'Rivalry',
    summary: 'Turns the champion toward visible opposition.',
  },
];

const normalizeChampionFocusOptions = (value: unknown): ChampionFocusOption[] => {
  if (!Array.isArray(value)) return defaultChampionFocusOptions();

  const options = value.map(normalizeChampionFocusOption).filter(option => option.label.length > 0);
  return options.length > 0 ? options : defaultChampionFocusOptions();
};

const normalizeChampionCosts = (value: unknown): Record<ChampionFocus, number> => {
  const source = isRecordValue(value) ? value : {};

  return {
    quest: Number(source.quest ?? 20),
    defense: Number(source.defense ?? 20),
    research: Number(source.research ?? 25),
    rivalry: Number(source.rivalry ?? 25),
  };
};

const normalizeChampionQuest = (value: unknown): ChampionProfile['currentQuest'] => {
  if (!isRecordValue(value)) return null;

  return {
    focus: normalizeChampionFocus(value.focus),
    title: String(value.title ?? ''),
    summary: String(value.summary ?? ''),
    startedYear: Number(value.startedYear ?? value.started_year ?? 0),
    progress: Number(value.progress ?? 0),
  };
};

const normalizeChampionOutcome = (value: unknown): ChampionOutcome => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    eventId:
      (source.eventId ?? source.event_id) ? String(source.eventId ?? source.event_id) : null,
    type: String(source.type ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    effectSummary: String(source.effectSummary ?? source.effect_summary ?? ''),
    result: String(source.result ?? ''),
    focus: normalizeChampionFocus(source.focus),
    focusLabel: String(source.focusLabel ?? source.focus_label ?? ''),
    year: Number(source.year ?? 0),
    heroId: String(source.heroId ?? source.hero_id ?? ''),
    heroName: String(source.heroName ?? source.hero_name ?? ''),
    regionId:
      (source.regionId ?? source.region_id) ? String(source.regionId ?? source.region_id) : null,
    regionName:
      (source.regionName ?? source.region_name)
        ? String(source.regionName ?? source.region_name)
        : null,
    legacyType:
      (source.legacyType ?? source.legacy_type)
        ? String(source.legacyType ?? source.legacy_type)
        : null,
    legacyReason:
      (source.legacyReason ?? source.legacy_reason)
        ? String(source.legacyReason ?? source.legacy_reason)
        : null,
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
  };
};

const normalizeChampionBettingHook = (value: unknown): ChampionBettingHook => {
  const source = isRecordValue(value) ? value : {};
  const confidence = source.confidence;

  return {
    id: String(source.id ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    betType: String(source.betType ?? source.bet_type ?? ''),
    targetId: String(source.targetId ?? source.target_id ?? ''),
    targetType: source.targetType === 'region' || source.target_type === 'region' ? 'region' : 'hero',
    regionId:
      (source.regionId ?? source.region_id) ? String(source.regionId ?? source.region_id) : null,
    minimumYears: Number(source.minimumYears ?? source.minimum_years ?? 1),
    maximumYears: Number(source.maximumYears ?? source.maximum_years ?? 5),
    confidence:
      confidence === 'long_shot' ||
      confidence === 'likely' ||
      confidence === 'near_certain' ||
      confidence === 'possible'
        ? confidence
        : 'possible',
  };
};

const normalizeChampionLegacyHook = (value: unknown): ChampionLegacyHook => {
  const source = isRecordValue(value) ? value : {};

  return {
    heroId: (source.heroId ?? source.hero_id) ? String(source.heroId ?? source.hero_id) : null,
    name: String(source.name ?? ''),
    legacyType: String(source.legacyType ?? source.legacy_type ?? ''),
    reason: String(source.reason ?? ''),
    eventId:
      (source.eventId ?? source.event_id) ? String(source.eventId ?? source.event_id) : null,
  };
};

const normalizeChampionProfile = (value: unknown): ChampionProfile | null => {
  if (!isRecordValue(value)) return null;

  const focus = normalizeChampionFocus(value.focus);
  const rawOutcomes = value.outcomes;
  const outcomes = Array.isArray(rawOutcomes)
    ? rawOutcomes.map(normalizeChampionOutcome).filter(outcome => outcome.id.length > 0)
    : [];
  const latestOutcomeValue = value.latestOutcome ?? value.latest_outcome;

  return {
    heroId: String(value.heroId ?? value.hero_id ?? ''),
    name: String(value.name ?? ''),
    role: String(value.role ?? 'undecided'),
    regionId:
      (value.regionId ?? value.region_id) ? String(value.regionId ?? value.region_id) : null,
    regionName:
      (value.regionName ?? value.region_name)
        ? String(value.regionName ?? value.region_name)
        : null,
    designatedYear: Number(value.designatedYear ?? value.designated_year ?? 0),
    rank: Number(value.rank ?? 1),
    bond: Number(value.bond ?? 0),
    focus,
    focusLabel: String(value.focusLabel ?? value.focus_label ?? focus),
    questsCompleted: Number(value.questsCompleted ?? value.quests_completed ?? 0),
    rivalryPressure: Number(value.rivalryPressure ?? value.rivalry_pressure ?? 0),
    eventIds: asStringArray(value.eventIds ?? value.event_ids),
    currentQuest: normalizeChampionQuest(value.currentQuest ?? value.current_quest),
    outcomes,
    latestOutcome: isRecordValue(latestOutcomeValue)
      ? normalizeChampionOutcome(latestOutcomeValue)
      : (outcomes[0] ?? null),
    lastCultivatedYear: normalizeNullableNumber(
      value.lastCultivatedYear ?? value.last_cultivated_year
    ),
    lastOutcomeYear: normalizeNullableNumber(value.lastOutcomeYear ?? value.last_outcome_year),
    summary: String(value.summary ?? ''),
  };
};

const normalizeChampionStatus = (value: unknown): HeroChampionStatus | undefined => {
  if (!isRecordValue(value)) return undefined;

  return {
    isChampion: Boolean(value.isChampion ?? value.is_champion),
    profile: normalizeChampionProfile(value.profile),
    rosterLimit: Number(value.rosterLimit ?? value.roster_limit ?? 3),
    activeChampionCount: Number(value.activeChampionCount ?? value.active_champion_count ?? 0),
    designationCost: Number(value.designationCost ?? value.designation_cost ?? 25),
    cultivationCosts: normalizeChampionCosts(value.cultivationCosts ?? value.cultivation_costs),
    focusOptions: normalizeChampionFocusOptions(value.focusOptions ?? value.focus_options),
    eligible: Boolean(value.eligible),
    eligibilityReason:
      (value.eligibilityReason ?? value.eligibility_reason)
        ? String(value.eligibilityReason ?? value.eligibility_reason)
        : null,
  };
};

const normalizeChampionStatusResponse = (value: unknown): ChampionStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawChampions = source.champions;
  const champions = Array.isArray(rawChampions)
    ? rawChampions.map(normalizeChampionProfile).filter(profile => profile !== null)
    : [];
  const rawRecentOutcomes = source.recentOutcomes ?? source.recent_outcomes;
  const rawBettingHooks = source.bettingHooks ?? source.betting_hooks;
  const rawLegacyHooks = source.legacyHooks ?? source.legacy_hooks;

  return {
    rosterLimit: Number(source.rosterLimit ?? source.roster_limit ?? 3),
    activeCount: Number(source.activeCount ?? source.active_count ?? champions.length),
    designationCost: Number(source.designationCost ?? source.designation_cost ?? 25),
    cultivationBaseCost: Number(source.cultivationBaseCost ?? source.cultivation_base_cost ?? 15),
    focusOptions: normalizeChampionFocusOptions(source.focusOptions ?? source.focus_options),
    champions,
    recentOutcomes: Array.isArray(rawRecentOutcomes)
      ? rawRecentOutcomes.map(normalizeChampionOutcome)
      : [],
    bettingHooks: Array.isArray(rawBettingHooks)
      ? rawBettingHooks.map(normalizeChampionBettingHook)
      : [],
    legacyHooks: Array.isArray(rawLegacyHooks)
      ? rawLegacyHooks.map(normalizeChampionLegacyHook)
      : [],
  };
};

const normalizeChampionActionResponse = (value: unknown): ChampionActionResponse => {
  if (!isRecordValue(value)) return {};

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    cost: value.cost !== undefined ? Number(value.cost) : undefined,
    remainingDivineFavor:
      value.remainingDivineFavor !== undefined || value.remaining_divine_favor !== undefined
        ? Number(value.remainingDivineFavor ?? value.remaining_divine_favor)
        : undefined,
    champion: normalizeChampionProfile(value.champion),
    status: isRecordValue(value.status) ? normalizeChampionStatusResponse(value.status) : undefined,
    target: isRecordValue(value.target) ? normalizeHero(value.target) : null,
  };
};

const normalizeArtifactFocus = (value: unknown): ArtifactFocus => {
  return value === 'prosperity' || value === 'war' || value === 'knowledge' ? value : 'protection';
};

const normalizeArtifactOwnerType = (value: unknown): ArtifactOwnerType => {
  return value === 'hero' || value === 'region' || value === 'landmark' ? value : 'unbound';
};

const normalizeArtifactStatusValue = (value: unknown): ArtifactStatus => {
  return value === 'corrupted' || value === 'lost' ? value : 'active';
};

const normalizeArtifactRiskTier = (value: unknown): ArtifactRiskTier => {
  return value === 'rising' || value === 'high' || value === 'critical' ? value : 'low';
};

const defaultArtifactFocusOptions = (): ArtifactFocusOption[] => [
  {
    key: 'protection',
    label: 'Protection',
    summary: 'Wards settlements and champions.',
  },
  {
    key: 'prosperity',
    label: 'Prosperity',
    summary: 'Amplifies growth and resources.',
  },
  {
    key: 'war',
    label: 'War',
    summary: 'Turns conflict in the owner region.',
  },
  {
    key: 'knowledge',
    label: 'Knowledge',
    summary: 'Draws out magic and research.',
  },
];

const normalizeArtifactFocusOption = (value: unknown): ArtifactFocusOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeArtifactFocus(source.key);

  return {
    key,
    label: String(source.label ?? key),
    summary: String(source.summary ?? ''),
  };
};

const normalizeArtifactFocusOptions = (value: unknown): ArtifactFocusOption[] => {
  if (!Array.isArray(value)) return defaultArtifactFocusOptions();

  const options = value.map(normalizeArtifactFocusOption).filter(option => option.label.length > 0);
  return options.length > 0 ? options : defaultArtifactFocusOptions();
};

const normalizeArtifactHistoryEntry = (value: unknown): ArtifactHistoryEntry => {
  const source = isRecordValue(value) ? value : {};

  return {
    eventId: String(source.eventId ?? source.event_id ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    type: String(source.type ?? ''),
    year: normalizeNullableNumber(source.year),
    ownerType: normalizeArtifactOwnerType(source.ownerType ?? source.owner_type),
    ownerName: String(source.ownerName ?? source.owner_name ?? 'Unbound'),
    status: normalizeArtifactStatusValue(source.status),
    powerLevel: Number(source.powerLevel ?? source.power_level ?? 1),
    instability: Number(source.instability ?? 0),
    corruption: Number(source.corruption ?? 0),
  };
};

const normalizeDivineArtifact = (value: unknown): DivineArtifact => {
  const source = isRecordValue(value) ? value : {};
  const historyValue = source.history;
  const history = Array.isArray(historyValue)
    ? historyValue.map(normalizeArtifactHistoryEntry)
    : [];
  const focus = normalizeArtifactFocus(source.focus);

  return {
    id: String(source.id ?? ''),
    name: String(source.name ?? 'Unnamed Artifact'),
    focus,
    focusLabel: String(source.focusLabel ?? source.focus_label ?? focus),
    createdYear: Number(source.createdYear ?? source.created_year ?? 1),
    powerLevel: Number(source.powerLevel ?? source.power_level ?? 1),
    instability: Number(source.instability ?? 0),
    corruption: Number(source.corruption ?? 0),
    status: normalizeArtifactStatusValue(source.status),
    ownerType: normalizeArtifactOwnerType(source.ownerType ?? source.owner_type),
    ownerId: (source.ownerId ?? source.owner_id) ? String(source.ownerId ?? source.owner_id) : null,
    ownerName: String(source.ownerName ?? source.owner_name ?? 'Unbound'),
    ownerRegionId:
      (source.ownerRegionId ?? source.owner_region_id)
        ? String(source.ownerRegionId ?? source.owner_region_id)
        : null,
    eventIds: asStringArray(source.eventIds ?? source.event_ids),
    history,
    empowerCost: Number(source.empowerCost ?? source.empower_cost ?? 0),
    stabilizeCost: Number(source.stabilizeCost ?? source.stabilize_cost ?? 0),
    riskTier: normalizeArtifactRiskTier(source.riskTier ?? source.risk_tier),
    summary: String(source.summary ?? ''),
  };
};

const normalizeArtifactStatusResponse = (value: unknown): ArtifactStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawArtifacts = source.artifacts;
  const artifacts = Array.isArray(rawArtifacts) ? rawArtifacts.map(normalizeDivineArtifact) : [];

  return {
    artifactLimit: Number(source.artifactLimit ?? source.artifact_limit ?? 5),
    activeCount: Number(source.activeCount ?? source.active_count ?? artifacts.length),
    creationCost: Number(source.creationCost ?? source.creation_cost ?? 40),
    empowerBaseCost: Number(source.empowerBaseCost ?? source.empower_base_cost ?? 20),
    transferCost: Number(source.transferCost ?? source.transfer_cost ?? 8),
    stabilizeBaseCost: Number(source.stabilizeBaseCost ?? source.stabilize_base_cost ?? 15),
    focusOptions: normalizeArtifactFocusOptions(source.focusOptions ?? source.focus_options),
    summary: String(source.summary ?? ''),
    artifacts,
  };
};

const normalizeArtifactActionResponse = (value: unknown): ArtifactActionResponse => {
  if (!isRecordValue(value)) return {};

  const riskOutcome = value.riskOutcome ?? value.risk_outcome;

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    cost: value.cost !== undefined ? Number(value.cost) : undefined,
    remainingDivineFavor:
      value.remainingDivineFavor !== undefined || value.remaining_divine_favor !== undefined
        ? Number(value.remainingDivineFavor ?? value.remaining_divine_favor)
        : undefined,
    artifact: isRecordValue(value.artifact) ? normalizeDivineArtifact(value.artifact) : null,
    status: isRecordValue(value.status) ? normalizeArtifactStatusResponse(value.status) : undefined,
    riskOutcome: isRecordValue(riskOutcome)
      ? {
          type:
            riskOutcome.type === 'corruption' || riskOutcome.type === 'theft'
              ? riskOutcome.type
              : 'none',
          summary: String(riskOutcome.summary ?? ''),
          eventId:
            (riskOutcome.eventId ?? riskOutcome.event_id)
              ? String(riskOutcome.eventId ?? riskOutcome.event_id)
              : null,
        }
      : undefined,
  };
};

const normalizeWeatherPatternKey = (value: unknown): WeatherPatternKey => {
  if (
    value === 'drought' ||
    value === 'protective_winds' ||
    value === 'tempest' ||
    value === 'arcane_mist'
  ) {
    return value;
  }

  return 'gentle_rains';
};

const normalizeWeatherIntensityKey = (value: unknown): WeatherIntensityKey => {
  return value === 'strong' || value === 'severe' ? value : 'minor';
};

const defaultWeatherPatternOptions = (): WeatherPatternOption[] => [
  {
    key: 'gentle_rains',
    label: 'Gentle Rains',
    summary: 'Refreshes resources and settlements.',
    baseCost: 14,
    travelEffect: 'Travel becomes safer as water and forage recover.',
    conflictEffect: 'Raids lose momentum while supply pressure eases.',
  },
  {
    key: 'drought',
    label: 'Drought',
    summary: 'Pressures supplies and settlement survival.',
    baseCost: 16,
    travelEffect: 'Dry roads move quickly, but water scarcity raises risk.',
    conflictEffect: 'Scarcity raises unrest and contested supplies.',
  },
  {
    key: 'protective_winds',
    label: 'Protective Winds',
    summary: 'Screens roads and settlements.',
    baseCost: 13,
    travelEffect: 'Routes are shielded from ambush and foul weather.',
    conflictEffect: 'Raids struggle against defensive weather.',
  },
  {
    key: 'tempest',
    label: 'Tempest',
    summary: 'Disrupts travel, conflict, and resource nodes.',
    baseCost: 18,
    travelEffect: 'Roads, crossings, and ports become hazardous.',
    conflictEffect: 'Open conflict scatters, but aftermath violence rises.',
  },
  {
    key: 'arcane_mist',
    label: 'Arcane Mist',
    summary: 'Amplifies magic while making outcomes stranger.',
    baseCost: 20,
    travelEffect: 'Travelers move unseen, but routes become harder to read.',
    conflictEffect: 'Skirmishes become unpredictable around wild magic.',
  },
];

const defaultWeatherIntensityOptions = (): WeatherIntensityOption[] => [
  {
    key: 'minor',
    label: 'Minor',
    costMultiplier: 1,
    effectMultiplier: 1,
    risk: 8,
    summary: 'A light, local nudge.',
  },
  {
    key: 'strong',
    label: 'Strong',
    costMultiplier: 1.55,
    effectMultiplier: 1.55,
    risk: 18,
    summary: 'A visible regional shift.',
  },
  {
    key: 'severe',
    label: 'Severe',
    costMultiplier: 2.2,
    effectMultiplier: 2.15,
    risk: 34,
    summary: 'A forceful climate turn.',
  },
];

const normalizeOptionalNumber = (value: unknown): number | undefined => {
  if (value === undefined || value === null || value === '') return undefined;
  return Number(value);
};

const normalizeWeatherPatternOption = (value: unknown): WeatherPatternOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeWeatherPatternKey(source.key);

  return {
    key,
    label: String(source.label ?? key),
    summary: String(source.summary ?? ''),
    baseCost: Number(source.baseCost ?? source.base_cost ?? 0),
    travelEffect: String(source.travelEffect ?? source.travel_effect ?? ''),
    conflictEffect: String(source.conflictEffect ?? source.conflict_effect ?? ''),
  };
};

const normalizeWeatherPatternOptions = (value: unknown): WeatherPatternOption[] => {
  if (!Array.isArray(value)) return defaultWeatherPatternOptions();

  const options = value
    .map(normalizeWeatherPatternOption)
    .filter(option => option.label.length > 0);
  return options.length > 0 ? options : defaultWeatherPatternOptions();
};

const normalizeWeatherIntensityOption = (value: unknown): WeatherIntensityOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeWeatherIntensityKey(source.key);

  return {
    key,
    label: String(source.label ?? key),
    costMultiplier: Number(source.costMultiplier ?? source.cost_multiplier ?? 1),
    effectMultiplier: Number(source.effectMultiplier ?? source.effect_multiplier ?? 1),
    risk: Number(source.risk ?? 0),
    summary: String(source.summary ?? ''),
  };
};

const normalizeWeatherIntensityOptions = (value: unknown): WeatherIntensityOption[] => {
  if (!Array.isArray(value)) return defaultWeatherIntensityOptions();

  const options = value
    .map(normalizeWeatherIntensityOption)
    .filter(option => option.label.length > 0);
  return options.length > 0 ? options : defaultWeatherIntensityOptions();
};

const normalizeWeatherSnapshot = (value: unknown): WeatherSnapshot => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    prosperity: normalizeOptionalNumber(source.prosperity),
    chaos: normalizeOptionalNumber(source.chaos),
    magicAffinity: normalizeOptionalNumber(source.magicAffinity ?? source.magic_affinity),
    dangerLevel: normalizeOptionalNumber(source.dangerLevel ?? source.danger_level),
    population: normalizeOptionalNumber(source.population),
    defensibility: normalizeOptionalNumber(source.defensibility),
    output: normalizeOptionalNumber(source.output),
    effectiveOutput: normalizeOptionalNumber(source.effectiveOutput ?? source.effective_output),
    status: typeof source.status === 'string' ? String(source.status) : undefined,
    type: typeof source.type === 'string' ? String(source.type) : undefined,
    climateType:
      typeof (source.climateType ?? source.climate_type) === 'string'
        ? String(source.climateType ?? source.climate_type)
        : undefined,
  };
};

const normalizeWeatherDeltaValue = (
  value: unknown
): WeatherChangeDelta['before'] | WeatherChangeDelta['after'] => {
  if (value === undefined || value === null) return null;
  if (typeof value === 'number' || typeof value === 'string' || typeof value === 'boolean') {
    return value;
  }

  return String(value);
};

const normalizeWeatherChangeDelta = (value: unknown): WeatherChangeDelta => {
  const source = isRecordValue(value) ? value : {};

  return {
    before: normalizeWeatherDeltaValue(source.before),
    after: normalizeWeatherDeltaValue(source.after),
    delta: normalizeNullableNumber(source.delta),
  };
};

const normalizeWeatherChangeMap = (value: unknown): Record<string, WeatherChangeDelta> => {
  if (!isRecordValue(value)) return {};

  return Object.fromEntries(
    Object.entries(value).map(([key, rawDelta]) => [key, normalizeWeatherChangeDelta(rawDelta)])
  );
};

const normalizeWeatherEntityChange = (value: unknown): WeatherEntityChange => {
  const source = isRecordValue(value) ? value : {};
  const base = normalizeWeatherSnapshot(source);

  return {
    ...base,
    before: normalizeWeatherSnapshot(source.before),
    after: normalizeWeatherSnapshot(source.after),
    change: normalizeWeatherChangeMap(source.change),
  };
};

const normalizeWeatherRiskOutcome = (value: unknown): WeatherRiskOutcome => {
  const source = isRecordValue(value) ? value : {};

  return {
    type: source.type === 'backlash' ? 'backlash' : 'none',
    summary: String(source.summary ?? ''),
  };
};

const normalizeWeatherInfluenceEntry = (value: unknown): WeatherInfluenceEntry => {
  const source = isRecordValue(value) ? value : {};
  const settlementChangesValue = source.settlementChanges ?? source.settlement_changes;
  const resourceChangesValue = source.resourceChanges ?? source.resource_changes;

  return {
    id: String(source.id ?? ''),
    eventId: (source.eventId ?? source.event_id) ? String(source.eventId ?? source.event_id) : null,
    year: Number(source.year ?? 0),
    regionId: String(source.regionId ?? source.region_id ?? ''),
    regionName: String(source.regionName ?? source.region_name ?? ''),
    pattern: normalizeWeatherPatternKey(source.pattern),
    patternLabel: String(source.patternLabel ?? source.pattern_label ?? ''),
    intensity: normalizeWeatherIntensityKey(source.intensity),
    intensityLabel: String(source.intensityLabel ?? source.intensity_label ?? ''),
    cost: Number(source.cost ?? 0),
    summary: String(source.summary ?? ''),
    travelEffect: String(source.travelEffect ?? source.travel_effect ?? ''),
    conflictEffect: String(source.conflictEffect ?? source.conflict_effect ?? ''),
    riskOutcome: normalizeWeatherRiskOutcome(source.riskOutcome ?? source.risk_outcome),
    regionBefore: normalizeWeatherSnapshot(source.regionBefore ?? source.region_before),
    regionAfter: normalizeWeatherSnapshot(source.regionAfter ?? source.region_after),
    regionChange: normalizeWeatherChangeMap(source.regionChange ?? source.region_change),
    settlementChanges: Array.isArray(settlementChangesValue)
      ? settlementChangesValue.map(normalizeWeatherEntityChange)
      : [],
    resourceChanges: Array.isArray(resourceChangesValue)
      ? resourceChangesValue.map(normalizeWeatherEntityChange)
      : [],
  };
};

const normalizeWeatherStatusResponse = (value: unknown): WeatherStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawRecentInfluences = source.recentInfluences ?? source.recent_influences;
  const recentInfluences = Array.isArray(rawRecentInfluences)
    ? rawRecentInfluences.map(normalizeWeatherInfluenceEntry)
    : [];

  return {
    currentYear: Number(source.currentYear ?? source.current_year ?? 0),
    summary: String(source.summary ?? ''),
    patternOptions: normalizeWeatherPatternOptions(source.patternOptions ?? source.pattern_options),
    intensityOptions: normalizeWeatherIntensityOptions(
      source.intensityOptions ?? source.intensity_options
    ),
    currentInfluence: isRecordValue(source.currentInfluence ?? source.current_influence)
      ? normalizeWeatherInfluenceEntry(source.currentInfluence ?? source.current_influence)
      : null,
    recentInfluences,
  };
};

const normalizeWeatherActionResponse = (value: unknown): WeatherActionResponse => {
  if (!isRecordValue(value)) return {};

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    cost: value.cost !== undefined ? Number(value.cost) : undefined,
    remainingDivineFavor:
      value.remainingDivineFavor !== undefined || value.remaining_divine_favor !== undefined
        ? Number(value.remainingDivineFavor ?? value.remaining_divine_favor)
        : undefined,
    influence: isRecordValue(value.influence) ? normalizeWeatherInfluenceEntry(value.influence) : null,
    status: isRecordValue(value.status) ? normalizeWeatherStatusResponse(value.status) : undefined,
  };
};

const normalizeTemporalOmenTargetType = (value: unknown): TemporalOmenTargetType => {
  if (value === 'region' || value === 'hero') {
    return value;
  }

  return 'world';
};

const normalizeTemporalOmenHorizon = (value: unknown): TemporalOmenHorizon => {
  if (value === 'generation' || value === 'era') {
    return value;
  }

  return 'near';
};

const normalizeTemporalOmenConfidence = (value: unknown): TemporalOmenConfidence => {
  if (value === 'high' || value === 'low') {
    return value;
  }

  return 'medium';
};

const normalizeTemporalOmenRiskBand = (value: unknown): TemporalOmenRiskBand => {
  if (value === 'favorable' || value === 'dangerous' || value === 'dire') {
    return value;
  }

  return 'uncertain';
};

const normalizeTemporalOmenTone = (value: unknown): TemporalOmenTone => {
  if (value === 'good' || value === 'warn' || value === 'bad') {
    return value;
  }

  return 'neutral';
};

const defaultTemporalOmenTargetOptions = (): TemporalOmenTargetOption[] => [
  { key: 'world', label: 'World', summary: 'Read the broad pressure of the entire world.' },
  { key: 'region', label: 'Region', summary: 'Read local prosperity, danger, and resources.' },
  { key: 'hero', label: 'Hero', summary: 'Read a mortal thread.' },
];

const defaultTemporalOmenHorizonOptions = (): TemporalOmenHorizonOption[] => [
  {
    key: 'near',
    label: 'Near Future',
    years: 3,
    baseCost: 12,
    confidence: 'high',
    summary: 'A short reach with clear but narrow signs.',
  },
  {
    key: 'generation',
    label: 'A Generation',
    years: 12,
    baseCost: 22,
    confidence: 'medium',
    summary: 'A wider reach where trends matter more than exact events.',
  },
  {
    key: 'era',
    label: 'Era Edge',
    years: 0,
    baseCost: 34,
    confidence: 'low',
    summary: 'A long reach toward the era boundary.',
  },
];

const normalizeTemporalOmenTargetOption = (value: unknown): TemporalOmenTargetOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeTemporalOmenTargetType(source.key);

  return {
    key,
    label: String(source.label ?? key),
    summary: String(source.summary ?? ''),
  };
};

const normalizeTemporalOmenTargetOptions = (value: unknown): TemporalOmenTargetOption[] => {
  if (!Array.isArray(value)) return defaultTemporalOmenTargetOptions();

  const options = value
    .map(normalizeTemporalOmenTargetOption)
    .filter(option => option.label.length > 0);
  return options.length > 0 ? options : defaultTemporalOmenTargetOptions();
};

const normalizeTemporalOmenHorizonOption = (value: unknown): TemporalOmenHorizonOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeTemporalOmenHorizon(source.key);

  return {
    key,
    label: String(source.label ?? key),
    years: Number(source.years ?? 0),
    baseCost: Number(source.baseCost ?? source.base_cost ?? 0),
    confidence: normalizeTemporalOmenConfidence(source.confidence),
    summary: String(source.summary ?? ''),
  };
};

const normalizeTemporalOmenHorizonOptions = (value: unknown): TemporalOmenHorizonOption[] => {
  if (!Array.isArray(value)) return defaultTemporalOmenHorizonOptions();

  const options = value
    .map(normalizeTemporalOmenHorizonOption)
    .filter(option => option.label.length > 0);
  return options.length > 0 ? options : defaultTemporalOmenHorizonOptions();
};

const normalizeTemporalOmenSignal = (value: unknown): TemporalOmenSignal => {
  const source = isRecordValue(value) ? value : {};

  return {
    label: String(source.label ?? ''),
    value: String(source.value ?? ''),
    tone: normalizeTemporalOmenTone(source.tone),
  };
};

const normalizeTemporalOmenPrediction = (value: unknown): TemporalOmenPrediction => {
  const source = isRecordValue(value) ? value : {};

  return {
    type: String(source.type ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    riskScore: Number(source.riskScore ?? source.risk_score ?? 0),
    riskBand: normalizeTemporalOmenRiskBand(source.riskBand ?? source.risk_band),
    confidence: normalizeTemporalOmenConfidence(source.confidence),
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
  };
};

const normalizeTemporalOmenEntry = (value: unknown): TemporalOmenEntry => {
  const source = isRecordValue(value) ? value : {};
  const signals = Array.isArray(source.signals)
    ? source.signals.map(normalizeTemporalOmenSignal).filter(signal => signal.label)
    : [];
  const predictions = Array.isArray(source.predictions)
    ? source.predictions.map(normalizeTemporalOmenPrediction).filter(prediction => prediction.title)
    : [];

  return {
    id: String(source.id ?? ''),
    eventId: (source.eventId ?? source.event_id) ? String(source.eventId ?? source.event_id) : null,
    createdYear: Number(source.createdYear ?? source.created_year ?? 0),
    targetYear: Number(source.targetYear ?? source.target_year ?? 0),
    horizon: normalizeTemporalOmenHorizon(source.horizon),
    horizonLabel: String(source.horizonLabel ?? source.horizon_label ?? ''),
    horizonYears: Number(source.horizonYears ?? source.horizon_years ?? 0),
    targetType: normalizeTemporalOmenTargetType(source.targetType ?? source.target_type),
    targetId: (source.targetId ?? source.target_id) ? String(source.targetId ?? source.target_id) : null,
    targetName: String(source.targetName ?? source.target_name ?? ''),
    cost: Number(source.cost ?? 0),
    confidence: normalizeTemporalOmenConfidence(source.confidence),
    riskBand: normalizeTemporalOmenRiskBand(source.riskBand ?? source.risk_band),
    summary: String(source.summary ?? ''),
    signals,
    predictions,
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
    consistencyNote: String(source.consistencyNote ?? source.consistency_note ?? ''),
  };
};

const normalizeTemporalOmenStatusResponse = (value: unknown): TemporalOmenStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawRecentOmens = source.recentOmens ?? source.recent_omens;
  const recentOmens = Array.isArray(rawRecentOmens)
    ? rawRecentOmens.map(normalizeTemporalOmenEntry)
    : [];

  return {
    currentYear: Number(source.currentYear ?? source.current_year ?? 0),
    summary: String(source.summary ?? ''),
    targetOptions: normalizeTemporalOmenTargetOptions(source.targetOptions ?? source.target_options),
    horizonOptions: normalizeTemporalOmenHorizonOptions(
      source.horizonOptions ?? source.horizon_options
    ),
    currentOmen: isRecordValue(source.currentOmen ?? source.current_omen)
      ? normalizeTemporalOmenEntry(source.currentOmen ?? source.current_omen)
      : null,
    recentOmens,
  };
};

const normalizeTemporalOmenActionResponse = (value: unknown): TemporalOmenActionResponse => {
  if (!isRecordValue(value)) return {};

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    cost: value.cost !== undefined ? Number(value.cost) : undefined,
    remainingDivineFavor:
      value.remainingDivineFavor !== undefined || value.remaining_divine_favor !== undefined
        ? Number(value.remainingDivineFavor ?? value.remaining_divine_favor)
        : undefined,
    omen: isRecordValue(value.omen) ? normalizeTemporalOmenEntry(value.omen) : null,
    status: isRecordValue(value.status)
      ? normalizeTemporalOmenStatusResponse(value.status)
      : undefined,
  };
};

const normalizeMagicDiscoveryTargetType = (value: unknown): MagicDiscoveryTargetType => {
  if (value === 'hero' || value === 'landmark') {
    return value;
  }

  return 'region';
};

const normalizeMagicDiscoveryPathStatus = (value: unknown): MagicDiscoveryPathStatus => {
  if (value === 'emerging' || value === 'known') {
    return value;
  }

  return 'hidden';
};

const defaultMagicDiscoveryPathOptions = (): MagicDiscoveryPathOption[] => [
  {
    key: 'ley_weaving',
    label: 'Ley Weaving',
    domain: 'Region',
    summary: 'Channels regional ley lines into prosperity, travel, and magic pressure.',
    hiddenSummary: 'Look for high magic regions, magical springs, and arcane landmarks.',
  },
  {
    key: 'spirit_compacts',
    label: 'Spirit Compacts',
    domain: 'Hero',
    summary: 'Binds prophets, sacred groves, and local spirits into durable obligations.',
    hiddenSummary: 'Look for prophets, sacred groves, temples, and mystical cultures.',
  },
  {
    key: 'ruin_script',
    label: 'Ruin Script',
    domain: 'Landmark',
    summary: 'Deciphers ruins, towers, and ancient writing into research breakthroughs.',
    hiddenSummary: 'Look for scholars, ancient ruins, towers, and hidden landmarks.',
  },
  {
    key: 'storm_rites',
    label: 'Storm Rites',
    domain: 'Region',
    summary: 'Turns weather, danger, and volatile magic into repeatable ritual practice.',
    hiddenSummary: 'Look for dangerous, chaotic, weather-shaken, or highly magical regions.',
  },
  {
    key: 'civic_enchantment',
    label: 'Civic Enchantment',
    domain: 'Settlement',
    summary: 'Lets settlements carry culture, trade, and prosperity through enchanted civic works.',
    hiddenSummary: 'Look for prosperous settlements, mercantile culture, and agent-of-change heroes.',
  },
];

const normalizeMagicDiscoveryPathOption = (value: unknown): MagicDiscoveryPathOption => {
  const source = isRecordValue(value) ? value : {};

  return {
    key: String(source.key ?? ''),
    label: String(source.label ?? source.key ?? ''),
    domain: String(source.domain ?? ''),
    summary: String(source.summary ?? ''),
    hiddenSummary: String(source.hiddenSummary ?? source.hidden_summary ?? ''),
  };
};

const normalizeMagicDiscoveryPathOptions = (value: unknown): MagicDiscoveryPathOption[] => {
  if (!Array.isArray(value)) return defaultMagicDiscoveryPathOptions();

  const options = value
    .map(normalizeMagicDiscoveryPathOption)
    .filter(option => option.key.length > 0);
  return options.length > 0 ? options : defaultMagicDiscoveryPathOptions();
};

const normalizeMagicDiscoveryTargetOption = (value: unknown): MagicDiscoveryTargetOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeMagicDiscoveryTargetType(source.key);

  return {
    key,
    label: String(source.label ?? key),
  };
};

const normalizeMagicDiscoveryTargetOptions = (value: unknown): MagicDiscoveryTargetOption[] => {
  if (!Array.isArray(value)) {
    return [
      { key: 'region', label: 'Region' },
      { key: 'hero', label: 'Hero' },
      { key: 'landmark', label: 'Landmark' },
    ];
  }

  const options = value
    .map(normalizeMagicDiscoveryTargetOption)
    .filter(option => option.label.length > 0);
  return options.length > 0
    ? options
    : [
        { key: 'region', label: 'Region' },
        { key: 'hero', label: 'Hero' },
        { key: 'landmark', label: 'Landmark' },
      ];
};

const normalizeMagicDiscoveryTarget = (value: unknown): MagicDiscoveryTarget => {
  const source = isRecordValue(value) ? value : {};

  return {
    type: normalizeMagicDiscoveryTargetType(source.type),
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    regionId: (source.regionId ?? source.region_id)
      ? String(source.regionId ?? source.region_id)
      : null,
  };
};

const normalizeMagicDiscoverySignal = (value: unknown): MagicDiscoverySignal => {
  const source = isRecordValue(value) ? value : {};

  return {
    label: String(source.label ?? ''),
    value: String(source.value ?? ''),
    summary: String(source.summary ?? ''),
  };
};

const normalizeMagicDiscoveryHistoryEntry = (value: unknown): MagicDiscoveryHistoryEntry => {
  const source = isRecordValue(value) ? value : {};

  return {
    eventId: String(source.eventId ?? source.event_id ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    type: String(source.type ?? ''),
    year: Number(source.year ?? 0),
    targetType: normalizeMagicDiscoveryTargetType(source.targetType ?? source.target_type),
    targetId: String(source.targetId ?? source.target_id ?? ''),
    targetName: String(source.targetName ?? source.target_name ?? ''),
    progress: Number(source.progress ?? 0),
    status: normalizeMagicDiscoveryPathStatus(source.status),
  };
};

const normalizeMagicDiscoveryBettingHook = (value: unknown): MagicDiscoveryBettingHook => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    path: String(source.path ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    betType: String(source.betType ?? source.bet_type ?? ''),
    targetId: String(source.targetId ?? source.target_id ?? ''),
    targetType: normalizeMagicDiscoveryTargetType(source.targetType ?? source.target_type),
    regionId: (source.regionId ?? source.region_id)
      ? String(source.regionId ?? source.region_id)
      : null,
  };
};

const normalizeMagicDiscoveryPath = (value: unknown): MagicDiscoveryPath => {
  const source = isRecordValue(value) ? value : {};
  const rawSignals = source.signals;
  const rawEventIds = source.eventIds ?? source.event_ids;
  const rawHistory = source.history;
  const bettingHookValue = source.bettingHook ?? source.betting_hook;

  return {
    key: String(source.key ?? ''),
    label: String(source.label ?? source.key ?? ''),
    domain: String(source.domain ?? ''),
    summary: String(source.summary ?? ''),
    hiddenSummary: String(source.hiddenSummary ?? source.hidden_summary ?? ''),
    status: normalizeMagicDiscoveryPathStatus(source.status),
    progress: Number(source.progress ?? 0),
    evidenceScore: Number(source.evidenceScore ?? source.evidence_score ?? 0),
    discoveryYear:
      source.discoveryYear !== undefined || source.discovery_year !== undefined
        ? normalizeNullableNumber(source.discoveryYear ?? source.discovery_year)
        : null,
    lastResearchedYear:
      source.lastResearchedYear !== undefined || source.last_researched_year !== undefined
        ? normalizeNullableNumber(source.lastResearchedYear ?? source.last_researched_year)
        : null,
    lastTarget: isRecordValue(source.lastTarget ?? source.last_target)
      ? normalizeMagicDiscoveryTarget(source.lastTarget ?? source.last_target)
      : null,
    signals: Array.isArray(rawSignals)
      ? rawSignals.map(normalizeMagicDiscoverySignal).filter(signal => signal.label)
      : [],
    eventIds: asStringArray(rawEventIds),
    history: Array.isArray(rawHistory)
      ? rawHistory
          .map(normalizeMagicDiscoveryHistoryEntry)
          .filter(entry => entry.eventId || entry.title)
      : [],
    bettingHook: isRecordValue(bettingHookValue)
      ? normalizeMagicDiscoveryBettingHook(bettingHookValue)
      : null,
    visibilitySummary: String(source.visibilitySummary ?? source.visibility_summary ?? ''),
  };
};

const normalizeMagicDiscoverySuggestedTarget = (value: unknown): MagicDiscoverySuggestedTarget => {
  const source = isRecordValue(value) ? value : {};

  return {
    ...normalizeMagicDiscoveryTarget(source),
    bestPath: String(source.bestPath ?? source.best_path ?? ''),
    reason: String(source.reason ?? ''),
  };
};

const normalizeMagicDiscoveryStatusResponse = (value: unknown): MagicDiscoveryStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawPaths = source.paths;
  const rawSuggestedTargets = source.suggestedTargets ?? source.suggested_targets;
  const rawBettingHooks = source.bettingHooks ?? source.betting_hooks;

  return {
    currentYear: Number(source.currentYear ?? source.current_year ?? 0),
    researchCost: Number(source.researchCost ?? source.research_cost ?? 0),
    summary: String(source.summary ?? ''),
    pathOptions: normalizeMagicDiscoveryPathOptions(source.pathOptions ?? source.path_options),
    paths: Array.isArray(rawPaths) ? rawPaths.map(normalizeMagicDiscoveryPath) : [],
    targetOptions: normalizeMagicDiscoveryTargetOptions(
      source.targetOptions ?? source.target_options
    ),
    suggestedTargets: Array.isArray(rawSuggestedTargets)
      ? rawSuggestedTargets.map(normalizeMagicDiscoverySuggestedTarget)
      : [],
    bettingHooks: Array.isArray(rawBettingHooks)
      ? rawBettingHooks.map(normalizeMagicDiscoveryBettingHook)
      : [],
  };
};

const normalizeMagicResearchResponse = (value: unknown): MagicResearchResponse => {
  if (!isRecordValue(value)) return {};

  const researchValue = value.research;
  const research = isRecordValue(researchValue)
    ? {
        target: normalizeMagicDiscoveryTarget(researchValue.target),
        evidenceScore: Number(researchValue.evidenceScore ?? researchValue.evidence_score ?? 0),
        progressGain: Number(researchValue.progressGain ?? researchValue.progress_gain ?? 0),
        signals: Array.isArray(researchValue.signals)
          ? researchValue.signals.map(normalizeMagicDiscoverySignal)
          : [],
        eventId: (researchValue.eventId ?? researchValue.event_id)
          ? String(researchValue.eventId ?? researchValue.event_id)
          : null,
      }
    : null;

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    cost: value.cost !== undefined ? Number(value.cost) : undefined,
    remainingDivineFavor:
      value.remainingDivineFavor !== undefined || value.remaining_divine_favor !== undefined
        ? Number(value.remainingDivineFavor ?? value.remaining_divine_favor)
        : undefined,
    path: isRecordValue(value.path) ? normalizeMagicDiscoveryPath(value.path) : null,
    research,
    status: isRecordValue(value.status)
      ? normalizeMagicDiscoveryStatusResponse(value.status)
      : undefined,
  };
};

const normalizeMythStrength = (value: unknown): MythStrength => {
  if (value === 'mythic' || value === 'strong' || value === 'forming') {
    return value;
  }

  return 'faint';
};

const normalizeMythTypeOption = (value: unknown): MythTypeOption => {
  const source = isRecordValue(value) ? value : {};

  return {
    key: String(source.key ?? ''),
    label: String(source.label ?? source.key ?? ''),
  };
};

const normalizeMythEffectTarget = (value: unknown): MythEffectTarget => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    trait: typeof source.trait === 'string' ? source.trait : undefined,
    summary: String(source.summary ?? ''),
  };
};

const normalizeMythEffects = (value: unknown): MythEffects => {
  const source = isRecordValue(value) ? value : {};
  const regions = source.regions;
  const heroes = source.heroes;
  const landmarks = source.landmarks;

  return {
    regions: Array.isArray(regions) ? regions.map(normalizeMythEffectTarget) : [],
    heroes: Array.isArray(heroes) ? heroes.map(normalizeMythEffectTarget) : [],
    landmarks: Array.isArray(landmarks) ? landmarks.map(normalizeMythEffectTarget) : [],
    futureEventSignals: asStringArray(source.futureEventSignals ?? source.future_event_signals),
  };
};

const normalizeMythCandidate = (value: unknown): MythCandidate => {
  const source = isRecordValue(value) ? value : {};

  return {
    eventId: String(source.eventId ?? source.event_id ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    sourceType: String(source.sourceType ?? source.source_type ?? ''),
    mythType: String(source.mythType ?? source.myth_type ?? ''),
    mythTypeLabel: String(source.mythTypeLabel ?? source.myth_type_label ?? ''),
    score: Number(source.score ?? 0),
    strength: normalizeMythStrength(source.strength),
    year:
      source.year !== undefined || source.source_year !== undefined
        ? normalizeNullableNumber(source.year ?? source.source_year)
        : null,
    regionId: (source.regionId ?? source.region_id) ? String(source.regionId ?? source.region_id) : null,
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
    reason: String(source.reason ?? ''),
    influencePreview: String(source.influencePreview ?? source.influence_preview ?? ''),
  };
};

const normalizePromotedMyth = (value: unknown): PromotedMyth => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    sourceEventId: String(source.sourceEventId ?? source.source_event_id ?? ''),
    promotionEventId: String(source.promotionEventId ?? source.promotion_event_id ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    mythType: String(source.mythType ?? source.myth_type ?? ''),
    mythTypeLabel: String(source.mythTypeLabel ?? source.myth_type_label ?? ''),
    sourceType: String(source.sourceType ?? source.source_type ?? ''),
    strength: normalizeMythStrength(source.strength),
    score: Number(source.score ?? 0),
    sourceYear:
      source.sourceYear !== undefined || source.source_year !== undefined
        ? normalizeNullableNumber(source.sourceYear ?? source.source_year)
        : null,
    promotedYear: Number(source.promotedYear ?? source.promoted_year ?? 0),
    regionId: (source.regionId ?? source.region_id) ? String(source.regionId ?? source.region_id) : null,
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
    reason: String(source.reason ?? ''),
    influenceSummary: String(source.influenceSummary ?? source.influence_summary ?? ''),
    effects: normalizeMythEffects(source.effects),
  };
};

const normalizeMythologyStatusResponse = (value: unknown): MythologyStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawMythTypeOptions = source.mythTypeOptions ?? source.myth_type_options;
  const rawMyths = source.myths;
  const rawCandidates = source.candidates;

  return {
    currentYear: Number(source.currentYear ?? source.current_year ?? 0),
    promotionCost: Number(source.promotionCost ?? source.promotion_cost ?? 0),
    summary: String(source.summary ?? ''),
    mythTypeOptions: Array.isArray(rawMythTypeOptions)
      ? rawMythTypeOptions.map(normalizeMythTypeOption).filter(option => option.key)
      : [],
    myths: Array.isArray(rawMyths) ? rawMyths.map(normalizePromotedMyth) : [],
    candidates: Array.isArray(rawCandidates)
      ? rawCandidates.map(normalizeMythCandidate).filter(candidate => candidate.eventId)
      : [],
    influenceSummary: String(source.influenceSummary ?? source.influence_summary ?? ''),
  };
};

const normalizePromoteMythResponse = (value: unknown): PromoteMythResponse => {
  if (!isRecordValue(value)) return {};

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    cost: value.cost !== undefined ? Number(value.cost) : undefined,
    remainingDivineFavor:
      value.remainingDivineFavor !== undefined || value.remaining_divine_favor !== undefined
        ? Number(value.remainingDivineFavor ?? value.remaining_divine_favor)
        : undefined,
    myth: isRecordValue(value.myth) ? normalizePromotedMyth(value.myth) : null,
    status: isRecordValue(value.status) ? normalizeMythologyStatusResponse(value.status) : undefined,
  };
};

const normalizeCivilizationBehaviorKey = (value: unknown): CivilizationBehaviorKey => {
  if (
    value === 'defense' ||
    value === 'trade' ||
    value === 'rivalry' ||
    value === 'research' ||
    value === 'recovery'
  ) {
    return value;
  }

  return 'expansion';
};

const normalizeCivilizationPriorityTier = (value: unknown): CivilizationPriorityTier => {
  if (value === 'urgent' || value === 'active' || value === 'watch') {
    return value;
  }

  return 'quiet';
};

const normalizeCivilizationSignal = (value: unknown): CivilizationSignal => {
  const source = isRecordValue(value) ? value : {};

  return {
    label: String(source.label ?? ''),
    value: String(source.value ?? ''),
    summary: String(source.summary ?? ''),
  };
};

const normalizeCivilizationBehaviorOption = (value: unknown): CivilizationBehaviorOption => {
  const source = isRecordValue(value) ? value : {};
  const key = normalizeCivilizationBehaviorKey(source.key);

  return {
    key,
    label: String(source.label ?? key),
    summary: String(source.summary ?? ''),
  };
};

const normalizeCivilizationBehaviorScore = (value: unknown): CivilizationBehaviorScore => {
  const source = isRecordValue(value) ? value : {};
  const rawSignals = source.signals;
  const key = normalizeCivilizationBehaviorKey(source.key);

  return {
    key,
    label: String(source.label ?? key),
    score: Number(source.score ?? 0),
    signals: Array.isArray(rawSignals)
      ? rawSignals.map(normalizeCivilizationSignal).filter(signal => signal.label)
      : [],
  };
};

const normalizeCivilizationStats = (value: unknown): CivilizationRegionStats => {
  const source = isRecordValue(value) ? value : {};

  return {
    prosperity: Number(source.prosperity ?? 0),
    chaos: Number(source.chaos ?? 0),
    dangerLevel: Number(source.dangerLevel ?? source.danger_level ?? 0),
    magicAffinity: Number(source.magicAffinity ?? source.magic_affinity ?? 0),
    culture: String(source.culture ?? ''),
    settlements: Number(source.settlements ?? 0),
    resources: Number(source.resources ?? 0),
    heroes: Number(source.heroes ?? 0),
    landmarks: Number(source.landmarks ?? 0),
    population: Number(source.population ?? 0),
    avgSettlementProsperity: Number(
      source.avgSettlementProsperity ?? source.avg_settlement_prosperity ?? 0
    ),
    avgSettlementDefense: Number(
      source.avgSettlementDefense ?? source.avg_settlement_defense ?? 0
    ),
    productiveResources: Number(source.productiveResources ?? source.productive_resources ?? 0),
    disruptedResources: Number(source.disruptedResources ?? source.disrupted_resources ?? 0),
  };
};

const normalizeCivilizationRegionAgenda = (value: unknown): CivilizationRegionAgenda => {
  const source = isRecordValue(value) ? value : {};
  const rawSignals = source.signals;
  const rawScores = source.scores;

  return {
    regionId: String(source.regionId ?? source.region_id ?? ''),
    regionName: String(source.regionName ?? source.region_name ?? ''),
    dominantBehavior: normalizeCivilizationBehaviorKey(
      source.dominantBehavior ?? source.dominant_behavior
    ),
    dominantBehaviorLabel: String(
      source.dominantBehaviorLabel ?? source.dominant_behavior_label ?? ''
    ),
    score: Number(source.score ?? 0),
    priorityTier: normalizeCivilizationPriorityTier(
      source.priorityTier ?? source.priority_tier
    ),
    summary: String(source.summary ?? ''),
    signals: Array.isArray(rawSignals)
      ? rawSignals.map(normalizeCivilizationSignal).filter(signal => signal.label)
      : [],
    scores: Array.isArray(rawScores) ? rawScores.map(normalizeCivilizationBehaviorScore) : [],
    stats: normalizeCivilizationStats(source.stats),
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
  };
};

const normalizeCivilizationChange = (value: unknown) => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    before: isRecordValue(source.before) ? source.before : {},
    after: isRecordValue(source.after) ? source.after : {},
    summary: String(source.summary ?? ''),
  };
};

const normalizeCivilizationChanges = (value: unknown): CivilizationChanges => {
  const source = isRecordValue(value) ? value : {};
  const normalizeChanges = (raw: unknown) =>
    Array.isArray(raw) ? raw.map(normalizeCivilizationChange) : [];

  return {
    regions: normalizeChanges(source.regions),
    settlements: normalizeChanges(source.settlements),
    resources: normalizeChanges(source.resources),
    heroes: normalizeChanges(source.heroes),
    landmarks: normalizeChanges(source.landmarks),
  };
};

const normalizeCivilizationDecision = (value: unknown): CivilizationDecision => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    year: Number(source.year ?? 0),
    source: String(source.source ?? 'tick'),
    regionId: String(source.regionId ?? source.region_id ?? ''),
    regionName: String(source.regionName ?? source.region_name ?? ''),
    behavior: normalizeCivilizationBehaviorKey(source.behavior),
    behaviorLabel: String(source.behaviorLabel ?? source.behavior_label ?? ''),
    score: Number(source.score ?? 0),
    priorityTier: normalizeCivilizationPriorityTier(source.priorityTier ?? source.priority_tier),
    summary: String(source.summary ?? ''),
    signalSummary: String(source.signalSummary ?? source.signal_summary ?? ''),
    effectSummary: String(source.effectSummary ?? source.effect_summary ?? ''),
    eventId:
      source.eventId !== undefined || source.event_id !== undefined
        ? String(source.eventId ?? source.event_id)
        : null,
    changes: normalizeCivilizationChanges(source.changes),
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
  };
};

const defaultCivilizationBehaviorCounts = (): Record<CivilizationBehaviorKey, number> => ({
  expansion: 0,
  defense: 0,
  trade: 0,
  rivalry: 0,
  research: 0,
  recovery: 0,
});

const normalizeCivilizationBehaviorCounts = (
  value: unknown
): Record<CivilizationBehaviorKey, number> => {
  const counts = defaultCivilizationBehaviorCounts();
  if (!isRecordValue(value)) {
    return counts;
  }

  Object.keys(counts).forEach(key => {
    counts[key as CivilizationBehaviorKey] = Number(value[key] ?? 0);
  });

  return counts;
};

const normalizeCivilizationStatusResponse = (value: unknown): CivilizationStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawOptions = source.behaviorOptions ?? source.behavior_options;
  const rawRegionAgendas = source.regionAgendas ?? source.region_agendas;
  const rawRecentDecisions = source.recentDecisions ?? source.recent_decisions;
  const rawTopAgenda = source.topAgenda ?? source.top_agenda;

  return {
    currentYear: Number(source.currentYear ?? source.current_year ?? 0),
    summary: String(source.summary ?? ''),
    behaviorOptions: Array.isArray(rawOptions)
      ? rawOptions.map(normalizeCivilizationBehaviorOption)
      : [],
    topAgenda: isRecordValue(rawTopAgenda) ? normalizeCivilizationRegionAgenda(rawTopAgenda) : null,
    regionAgendas: Array.isArray(rawRegionAgendas)
      ? rawRegionAgendas.map(normalizeCivilizationRegionAgenda)
      : [],
    recentDecisions: Array.isArray(rawRecentDecisions)
      ? rawRecentDecisions.map(normalizeCivilizationDecision)
      : [],
    behaviorCounts: normalizeCivilizationBehaviorCounts(
      source.behaviorCounts ?? source.behavior_counts
    ),
  };
};

const normalizeCivilizationAdvanceResponse = (
  value: unknown
): CivilizationAdvanceResponse => {
  if (!isRecordValue(value)) return {};

  return {
    success: typeof value.success === 'boolean' ? value.success : undefined,
    message: typeof value.message === 'string' ? value.message : undefined,
    decision: isRecordValue(value.decision) ? normalizeCivilizationDecision(value.decision) : null,
    status: isRecordValue(value.status)
      ? normalizeCivilizationStatusResponse(value.status)
      : undefined,
  };
};

const normalizePantheonSignal = (value: unknown): PantheonSignal => {
  const source = isRecordValue(value) ? value : {};

  return {
    label: String(source.label ?? ''),
    value: String(source.value ?? ''),
    summary: String(source.summary ?? ''),
  };
};

const normalizePantheonChange = (value: unknown): PantheonChange => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    before: isRecordValue(source.before) ? source.before : {},
    after: isRecordValue(source.after) ? source.after : {},
    summary: String(source.summary ?? ''),
  };
};

const normalizePantheonChanges = (value: unknown): PantheonChanges => {
  const source = isRecordValue(value) ? value : {};
  const normalizeChanges = (raw: unknown): PantheonChange[] =>
    Array.isArray(raw) ? raw.map(normalizePantheonChange) : [];

  return {
    regions: normalizeChanges(source.regions),
    settlements: normalizeChanges(source.settlements),
    resources: normalizeChanges(source.resources),
    heroes: normalizeChanges(source.heroes),
    landmarks: normalizeChanges(source.landmarks),
  };
};

const normalizePantheonIntervention = (value: unknown): PantheonIntervention => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    year: Number(source.year ?? 0),
    deityId: String(source.deityId ?? source.deity_id ?? ''),
    deityName: String(source.deityName ?? source.deity_name ?? ''),
    domain: String(source.domain ?? ''),
    alignment: String(source.alignment ?? ''),
    strategy: String(source.strategy ?? ''),
    pressureScore: Number(source.pressureScore ?? source.pressure_score ?? 0),
    pressureTier: String(source.pressureTier ?? source.pressure_tier ?? 'quiet'),
    targetRegionId:
      source.targetRegionId !== undefined || source.target_region_id !== undefined
        ? String(source.targetRegionId ?? source.target_region_id)
        : null,
    targetRegionName:
      source.targetRegionName !== undefined || source.target_region_name !== undefined
        ? String(source.targetRegionName ?? source.target_region_name)
        : null,
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    eventId:
      source.eventId !== undefined || source.event_id !== undefined
        ? String(source.eventId ?? source.event_id)
        : null,
    changes: normalizePantheonChanges(source.changes),
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
  };
};

const normalizePantheonCounterplayState = (value: unknown): PantheonCounterplayState => {
  const source = isRecordValue(value) ? value : {};

  return {
    deityId: String(source.deityId ?? source.deity_id ?? ''),
    deityName: String(source.deityName ?? source.deity_name ?? ''),
    appeasement: Number(source.appeasement ?? 0),
    defiance: Number(source.defiance ?? 0),
    pressureReduction: Number(source.pressureReduction ?? source.pressure_reduction ?? 0),
    lastActionYear:
      source.lastActionYear !== undefined || source.last_action_year !== undefined
        ? Number(source.lastActionYear ?? source.last_action_year)
        : null,
    lastMode:
      source.lastMode !== undefined || source.last_mode !== undefined
        ? String(source.lastMode ?? source.last_mode)
        : null,
    summary: String(source.summary ?? ''),
    relationshipSummary: String(source.relationshipSummary ?? source.relationship_summary ?? ''),
  };
};

const normalizePantheonCounterplayAction = (value: unknown): PantheonCounterplayAction => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    year: Number(source.year ?? 0),
    deityId: String(source.deityId ?? source.deity_id ?? ''),
    deityName: String(source.deityName ?? source.deity_name ?? ''),
    domain: String(source.domain ?? ''),
    mode: String(source.mode ?? 'appease'),
    cost: Number(source.cost ?? 0),
    targetRegionId:
      source.targetRegionId !== undefined || source.target_region_id !== undefined
        ? String(source.targetRegionId ?? source.target_region_id)
        : null,
    targetRegionName:
      source.targetRegionName !== undefined || source.target_region_name !== undefined
        ? String(source.targetRegionName ?? source.target_region_name)
        : null,
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    eventId:
      source.eventId !== undefined || source.event_id !== undefined
        ? String(source.eventId ?? source.event_id)
        : null,
  };
};

const normalizePantheonCounterplayStatus = (value: unknown): PantheonCounterplayStatus => {
  const source = isRecordValue(value) ? value : {};
  const rawCosts = isRecordValue(source.costs) ? source.costs : {};
  const rawByDeity = source.byDeity ?? source.by_deity;
  const rawRecentActions = source.recentActions ?? source.recent_actions;

  return {
    summary: String(source.summary ?? ''),
    costs: {
      appease: Number(rawCosts.appease ?? 12),
      challenge: Number(rawCosts.challenge ?? 18),
    },
    byDeity: Array.isArray(rawByDeity)
      ? rawByDeity.map(normalizePantheonCounterplayState)
      : [],
    recentActions: Array.isArray(rawRecentActions)
      ? rawRecentActions.map(normalizePantheonCounterplayAction)
      : [],
  };
};

const normalizePantheonDeity = (value: unknown): PantheonDeity => {
  const source = isRecordValue(value) ? value : {};
  const latest = source.latestIntervention ?? source.latest_intervention;
  const counterplay = source.counterplay;

  return {
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    domain: String(source.domain ?? ''),
    alignment: String(source.alignment ?? ''),
    goal: String(source.goal ?? ''),
    strategy: String(source.strategy ?? ''),
    allyId:
      source.allyId !== undefined || source.ally_id !== undefined
        ? String(source.allyId ?? source.ally_id)
        : null,
    rivalId:
      source.rivalId !== undefined || source.rival_id !== undefined
        ? String(source.rivalId ?? source.rival_id)
        : null,
    pressureScore: Number(source.pressureScore ?? source.pressure_score ?? 0),
    pressureTier: String(source.pressureTier ?? source.pressure_tier ?? 'quiet'),
    targetRegionId:
      source.targetRegionId !== undefined || source.target_region_id !== undefined
        ? String(source.targetRegionId ?? source.target_region_id)
        : null,
    targetRegionName:
      source.targetRegionName !== undefined || source.target_region_name !== undefined
        ? String(source.targetRegionName ?? source.target_region_name)
        : null,
    interventionCount: Number(source.interventionCount ?? source.intervention_count ?? 0),
    latestIntervention: isRecordValue(latest) ? normalizePantheonIntervention(latest) : null,
    counterplay: isRecordValue(counterplay) ? normalizePantheonCounterplayState(counterplay) : null,
  };
};

const normalizePantheonPressure = (value: unknown): PantheonPressure => {
  const source = isRecordValue(value) ? value : {};
  const rawSignals = source.signals;
  const counterplay = source.counterplay;

  return {
    deityId: String(source.deityId ?? source.deity_id ?? ''),
    deityName: String(source.deityName ?? source.deity_name ?? ''),
    domain: String(source.domain ?? ''),
    pressureScore: Number(source.pressureScore ?? source.pressure_score ?? 0),
    pressureTier: String(source.pressureTier ?? source.pressure_tier ?? 'quiet'),
    targetRegionId:
      source.targetRegionId !== undefined || source.target_region_id !== undefined
        ? String(source.targetRegionId ?? source.target_region_id)
        : null,
    targetRegionName:
      source.targetRegionName !== undefined || source.target_region_name !== undefined
        ? String(source.targetRegionName ?? source.target_region_name)
        : null,
    summary: String(source.summary ?? ''),
    signals: Array.isArray(rawSignals)
      ? rawSignals.map(normalizePantheonSignal).filter(signal => signal.label)
      : [],
    counterplay: isRecordValue(counterplay) ? normalizePantheonCounterplayState(counterplay) : null,
    relatedRegionIds: asStringArray(source.relatedRegionIds ?? source.related_region_ids),
    relatedSettlementIds: asStringArray(
      source.relatedSettlementIds ?? source.related_settlement_ids
    ),
    relatedHeroIds: asStringArray(source.relatedHeroIds ?? source.related_hero_ids),
    relatedLandmarkIds: asStringArray(source.relatedLandmarkIds ?? source.related_landmark_ids),
    relatedResourceIds: asStringArray(source.relatedResourceIds ?? source.related_resource_ids),
  };
};

const normalizePantheonRelationship = (value: unknown): PantheonRelationship => {
  const source = isRecordValue(value) ? value : {};

  return {
    sourceId: String(source.sourceId ?? source.source_id ?? ''),
    sourceName: String(source.sourceName ?? source.source_name ?? ''),
    targetId: String(source.targetId ?? source.target_id ?? ''),
    targetName: String(source.targetName ?? source.target_name ?? ''),
    stance: String(source.stance ?? 'rival'),
    tension:
      source.tension !== undefined || source.tension_score !== undefined
        ? Number(source.tension ?? source.tension_score)
        : undefined,
    stage:
      source.stage !== undefined || source.political_stage !== undefined
        ? String(source.stage ?? source.political_stage)
        : null,
    summary: String(source.summary ?? ''),
  };
};

const normalizePantheonPoliticalEscalation = (
  value: unknown
): PantheonPoliticalEscalation => {
  const source = isRecordValue(value) ? value : {};

  return {
    sourceId: String(source.sourceId ?? source.source_id ?? ''),
    sourceName: String(source.sourceName ?? source.source_name ?? ''),
    targetId: String(source.targetId ?? source.target_id ?? ''),
    targetName: String(source.targetName ?? source.target_name ?? ''),
    stance: String(source.stance ?? 'rival'),
    tension: Number(source.tension ?? source.tension_score ?? 0),
    stage: String(source.stage ?? source.political_stage ?? 'watchful'),
    pressureScore: Number(source.pressureScore ?? source.pressure_score ?? 0),
    targetRegionId:
      (source.targetRegionId ?? source.target_region_id)
        ? String(source.targetRegionId ?? source.target_region_id)
        : null,
    targetRegionName:
      (source.targetRegionName ?? source.target_region_name)
        ? String(source.targetRegionName ?? source.target_region_name)
        : null,
    interventionCount: Number(source.interventionCount ?? source.intervention_count ?? 0),
    betType:
      (source.betType ?? source.bet_type)
        ? String(source.betType ?? source.bet_type)
        : null,
    summary: String(source.summary ?? ''),
  };
};

const normalizePantheonPoliticsStatus = (value: unknown): PantheonPoliticsStatus => {
  const source = isRecordValue(value) ? value : {};
  const rawEscalations = source.escalations;

  return {
    summary: String(source.summary ?? ''),
    escalations: Array.isArray(rawEscalations)
      ? rawEscalations.map(normalizePantheonPoliticalEscalation)
      : [],
  };
};

const normalizePantheonBettingHook = (value: unknown): PantheonBettingHook => {
  const source = isRecordValue(value) ? value : {};

  return {
    id: String(source.id ?? ''),
    title: String(source.title ?? ''),
    summary: String(source.summary ?? ''),
    betType: String(source.betType ?? source.bet_type ?? 'pantheon_intervention'),
    targetId: String(source.targetId ?? source.target_id ?? ''),
    regionId:
      (source.regionId ?? source.region_id)
        ? String(source.regionId ?? source.region_id)
        : null,
    regionName:
      (source.regionName ?? source.region_name)
        ? String(source.regionName ?? source.region_name)
        : null,
    deityId: String(source.deityId ?? source.deity_id ?? ''),
    deityName: String(source.deityName ?? source.deity_name ?? ''),
    domain: String(source.domain ?? ''),
    pressureScore: Number(source.pressureScore ?? source.pressure_score ?? 0),
    pressureTier: String(source.pressureTier ?? source.pressure_tier ?? 'quiet'),
    confidence: String(source.confidence ?? 'possible'),
    minimumYears: Number(source.minimumYears ?? source.minimum_years ?? 1),
    maximumYears: Number(source.maximumYears ?? source.maximum_years ?? 8),
    relationshipStance:
      (source.relationshipStance ?? source.relationship_stance)
        ? String(source.relationshipStance ?? source.relationship_stance)
        : null,
    politicalStage:
      (source.politicalStage ?? source.political_stage)
        ? String(source.politicalStage ?? source.political_stage)
        : null,
    riskSummary:
      (source.riskSummary ?? source.risk_summary)
        ? String(source.riskSummary ?? source.risk_summary)
        : null,
  };
};

const normalizePantheonStatusResponse = (value: unknown): PantheonStatusResponse => {
  const source = isRecordValue(value) ? value : {};
  const rawDeities = source.deities;
  const rawPressure = source.pressure;
  const rawRecentInterventions = source.recentInterventions ?? source.recent_interventions;
  const rawRelationships = source.relationships;
  const rawPolitics = source.politics;
  const rawBettingHooks = source.bettingHooks ?? source.betting_hooks;
  const rawTopActor = source.topActor ?? source.top_actor;
  const rawCounterplay = source.counterplay;

  return {
    currentYear: Number(source.currentYear ?? source.current_year ?? 0),
    summary: String(source.summary ?? ''),
    deities: Array.isArray(rawDeities) ? rawDeities.map(normalizePantheonDeity) : [],
    pressure: Array.isArray(rawPressure) ? rawPressure.map(normalizePantheonPressure) : [],
    topActor: isRecordValue(rawTopActor) ? normalizePantheonDeity(rawTopActor) : null,
    recentInterventions: Array.isArray(rawRecentInterventions)
      ? rawRecentInterventions.map(normalizePantheonIntervention)
      : [],
    relationships: Array.isArray(rawRelationships)
      ? rawRelationships.map(normalizePantheonRelationship)
      : [],
    politics: isRecordValue(rawPolitics)
      ? normalizePantheonPoliticsStatus(rawPolitics)
      : null,
    bettingHooks: Array.isArray(rawBettingHooks)
      ? rawBettingHooks.map(normalizePantheonBettingHook)
      : [],
    counterplay: isRecordValue(rawCounterplay)
      ? normalizePantheonCounterplayStatus(rawCounterplay)
      : null,
  };
};

const normalizePantheonCounterplayResponse = (value: unknown): PantheonCounterplayResponse => {
  const source = isRecordValue(value) ? value : {};
  const action = source.action;
  const status = source.status;

  return {
    success: typeof source.success === 'boolean' ? source.success : undefined,
    message: typeof source.message === 'string' ? source.message : undefined,
    cost: source.cost !== undefined ? Number(source.cost) : undefined,
    remainingDivineFavor:
      source.remainingDivineFavor !== undefined || source.remaining_divine_favor !== undefined
        ? Number(source.remainingDivineFavor ?? source.remaining_divine_favor)
        : undefined,
    action: isRecordValue(action) ? normalizePantheonCounterplayAction(action) : null,
    status: isRecordValue(status) ? normalizePantheonStatusResponse(status) : undefined,
  };
};

const normalizeHero = (value: Hero | Record<string, unknown>): Hero => {
  const source = value as Record<string, unknown>;
  return {
    ...(value as Hero),
    id: String(source.id ?? ''),
    name: String(source.name ?? ''),
    regionId: String(source.regionId ?? source.region_id ?? ''),
    role: String(source.role ?? 'undecided') as Hero['role'],
    description: String(source.description ?? ''),
    feats: asStringArray(source.feats),
    level: source.level !== undefined ? Number(source.level) : undefined,
    age: source.age !== undefined ? Number(source.age) : undefined,
    isAlive:
      source.isAlive !== undefined || source.is_alive !== undefined
        ? Boolean(source.isAlive ?? source.is_alive)
        : undefined,
    deathReason:
      typeof (source.deathReason ?? source.death_reason) === 'string'
        ? String(source.deathReason ?? source.death_reason)
        : undefined,
    status: source.status as Hero['status'],
    personalityTraits: asStringArray(source.personalityTraits ?? source.personality_traits),
    alignment: isRecordValue(source.alignment)
      ? {
          good: Number(source.alignment.good ?? 50),
          chaotic: Number(source.alignment.chaotic ?? 50),
          lastChange:
            typeof (source.alignment.lastChange ?? source.alignment.last_change) === 'string'
              ? String(source.alignment.lastChange ?? source.alignment.last_change)
              : undefined,
        }
      : undefined,
    influenceActionCosts: isRecordValue(
      source.influenceActionCosts ?? source.influence_action_costs
    )
      ? ((source.influenceActionCosts ??
          source.influence_action_costs) as Hero['influenceActionCosts'])
      : undefined,
    lifecycleSummary: normalizeHeroLifecycleSummary(
      source.lifecycleSummary ?? source.lifecycle_summary
    ),
    relationshipContext: normalizeHeroRelationshipContext(
      source.relationshipContext ?? source.relationship_context
    ),
    recentHistory: normalizeHeroRecentHistory(source.recentHistory ?? source.recent_history),
    championStatus: normalizeChampionStatus(source.championStatus ?? source.champion_status),
  };
};

const normalizeSettlement = (value: Settlement | Record<string, unknown>): Settlement => {
  const source = value as Record<string, unknown>;
  return {
    ...(value as Settlement),
    id: String(source.id ?? ''),
    regionId: String(source.regionId ?? source.region_id ?? ''),
    name: String(source.name ?? ''),
    type: String(source.type ?? 'village') as Settlement['type'],
    population: Number(source.population ?? 0),
    prosperity: Number(source.prosperity ?? 0),
    defensibility: Number(source.defensibility ?? 0),
    status: String(source.status ?? 'stable') as Settlement['status'],
    specializations: asStringArray(source.specializations),
    events: asStringArray(source.events),
    foundedYear: Number(source.foundedYear ?? source.founded_year ?? 1),
    lastEventYear:
      source.lastEventYear !== undefined || source.last_event_year !== undefined
        ? Number(source.lastEventYear ?? source.last_event_year)
        : undefined,
    traits: asStringArray(source.traits),
  };
};

const normalizeLandmark = (value: Landmark | Record<string, unknown>): Landmark => {
  const source = value as Record<string, unknown>;
  return {
    ...(value as Landmark),
    id: String(source.id ?? ''),
    regionId: String(source.regionId ?? source.region_id ?? ''),
    name: String(source.name ?? ''),
    type: String(source.type ?? 'monument') as Landmark['type'],
    description: String(source.description ?? ''),
    status: String(source.status ?? 'weathered') as Landmark['status'],
    magicLevel: Number(source.magicLevel ?? source.magic_level ?? 0),
    dangerLevel: Number(source.dangerLevel ?? source.danger_level ?? 0),
    discoveredYear:
      source.discoveredYear !== undefined || source.discovered_year !== undefined
        ? Number(source.discoveredYear ?? source.discovered_year)
        : undefined,
    lastVisitedYear:
      source.lastVisitedYear !== undefined || source.last_visited_year !== undefined
        ? Number(source.lastVisitedYear ?? source.last_visited_year)
        : undefined,
    associatedEvents: asStringArray(source.associatedEvents ?? source.associated_events),
    traits: asStringArray(source.traits),
  };
};

const normalizeResourceNode = (value: ResourceNode | Record<string, unknown>): ResourceNode => {
  const source = value as Record<string, unknown>;
  return {
    ...(value as ResourceNode),
    id: String(source.id ?? ''),
    regionId: String(source.regionId ?? source.region_id ?? ''),
    settlementId:
      typeof (source.settlementId ?? source.settlement_id) === 'string'
        ? String(source.settlementId ?? source.settlement_id)
        : undefined,
    type: String(source.type ?? 'mine') as ResourceNode['type'],
    name: String(source.name ?? ''),
    outputValue: Number(source.outputValue ?? source.output ?? 0),
    status: String(source.status ?? 'active') as ResourceNode['status'],
  };
};

const normalizeGameEvent = (value: GameEvent | Record<string, unknown>): GameEvent => {
  const source = value as Record<string, unknown>;
  const relatedRegionIds = asStringArray(source.relatedRegionIds ?? source.related_region_ids);
  const relatedHeroIds = asStringArray(source.relatedHeroIds ?? source.related_hero_ids);
  const relatedSettlementIds = asStringArray(
    source.relatedSettlementIds ?? source.related_settlement_ids
  );
  const relatedLandmarkIds = asStringArray(
    source.relatedLandmarkIds ?? source.related_landmark_ids
  );
  const relatedResourceIds = asStringArray(
    source.relatedResourceIds ?? source.related_resource_ids
  );
  const title = String(source.title ?? '');
  const description = String(source.description ?? '');

  return {
    ...(value as GameEvent),
    id: String(source.id ?? ''),
    title: title || description || 'Untitled Event',
    timestamp: String(source.timestamp ?? source.created_at ?? ''),
    description,
    type: String(source.type ?? 'general'),
    status: String(source.status ?? 'active'),
    regionId:
      typeof (source.regionId ?? source.region_id) === 'string'
        ? String(source.regionId ?? source.region_id)
        : null,
    relatedRegionIds,
    relatedHeroIds,
    relatedSettlementIds,
    relatedLandmarkIds,
    relatedResourceIds,
    year:
      source.year !== undefined && source.year !== null && source.year !== ''
        ? Number(source.year)
        : undefined,
    createdAt:
      typeof (source.createdAt ?? source.created_at) === 'string'
        ? String(source.createdAt ?? source.created_at)
        : undefined,
    updatedAt:
      typeof (source.updatedAt ?? source.updated_at) === 'string'
        ? String(source.updatedAt ?? source.updated_at)
        : undefined,
  };
};

const normalizeBetPayoutProfile = (value: unknown): BetPayoutProfile | undefined => {
  if (!isRecordValue(value)) {
    return undefined;
  }

  return {
    stake: Number(value.stake ?? 0),
    odds: Number(value.odds ?? 0),
    confidence: String(value.confidence ?? 'possible') as DivineBet['confidence'],
    rawMultiplier: Number(value.rawMultiplier ?? value.raw_multiplier ?? 0),
    grossMultiplier: Number(value.grossMultiplier ?? value.gross_multiplier ?? 0),
    maximumMultiplier: Number(value.maximumMultiplier ?? value.maximum_multiplier ?? 0),
    grossPayout: Number(value.grossPayout ?? value.gross_payout ?? 0),
    netProfit: Number(value.netProfit ?? value.net_profit ?? 0),
    probabilityPercent: Number(value.probabilityPercent ?? value.probability_percent ?? 0),
    riskBand: String(value.riskBand ?? value.risk_band ?? 'medium') as BetPayoutProfile['riskBand'],
    summary: String(value.summary ?? ''),
  };
};

const normalizeDivineBet = (value: DivineBet | Record<string, unknown>): DivineBet => {
  const source = value as Record<string, unknown>;
  return {
    ...(value as DivineBet),
    id: String(source.id ?? ''),
    playerId: String(source.playerId ?? source.player_id ?? ''),
    betType: String(
      source.betType ?? source.bet_type ?? 'settlement_growth'
    ) as DivineBet['betType'],
    targetId: String(source.targetId ?? source.target_id ?? ''),
    description: String(source.description ?? ''),
    timeframe: Number(source.timeframe ?? 0),
    confidence: String(source.confidence ?? 'possible') as DivineBet['confidence'],
    divineFavorStake: Number(source.divineFavorStake ?? source.divine_favor_stake ?? 0),
    potentialPayout: Number(source.potentialPayout ?? source.potential_payout ?? 0),
    currentOdds: Number(source.currentOdds ?? source.current_odds ?? 0),
    status: String(source.status ?? 'active') as DivineBet['status'],
    placedYear: Number(source.placedYear ?? source.placed_year ?? 0),
    resolvedYear:
      source.resolvedYear !== undefined || source.resolved_year !== undefined
        ? Number(source.resolvedYear ?? source.resolved_year)
        : undefined,
    resolutionNotes:
      typeof (source.resolutionNotes ?? source.resolution_notes) === 'string'
        ? String(source.resolutionNotes ?? source.resolution_notes)
        : undefined,
    payoutProfile: normalizeBetPayoutProfile(source.payoutProfile ?? source.payout_profile),
    createdAt:
      typeof (source.createdAt ?? source.created_at) === 'string'
        ? new Date(String(source.createdAt ?? source.created_at))
        : undefined,
    updatedAt:
      typeof (source.updatedAt ?? source.updated_at) === 'string'
        ? new Date(String(source.updatedAt ?? source.updated_at))
        : undefined,
  };
};

const normalizeDivineBetSummary = (
  value: DivineBetSummary | Record<string, unknown>
): DivineBetSummary => {
  const source = value as Record<string, unknown>;
  const normalizeBetArray = (raw: unknown): DivineBet[] =>
    Array.isArray(raw) ? raw.filter(isRecordValue).map(normalizeDivineBet) : [];
  const rawTopBetTypes = source.topBetTypes ?? source.top_bet_types;
  const topBetTypes = Array.isArray(rawTopBetTypes)
    ? rawTopBetTypes.filter(isRecordValue).map(item => ({
        betType: String(
          item.betType ?? item.bet_type ?? 'settlement_growth'
        ) as DivineBet['betType'],
        total: Number(item.total ?? 0),
        stake: Number(item.stake ?? 0),
      }))
    : [];

  return {
    generatedAt: String(source.generatedAt ?? source.generated_at ?? ''),
    total: Number(source.total ?? 0),
    active: Number(source.active ?? 0),
    won: Number(source.won ?? 0),
    lost: Number(source.lost ?? 0),
    expired: Number(source.expired ?? 0),
    activeStake: Number(source.activeStake ?? source.active_stake ?? 0),
    activePotentialPayout: Number(
      source.activePotentialPayout ?? source.active_potential_payout ?? 0
    ),
    resolvedStake: Number(source.resolvedStake ?? source.resolved_stake ?? 0),
    wonPayout: Number(source.wonPayout ?? source.won_payout ?? 0),
    netResolvedFavor: Number(source.netResolvedFavor ?? source.net_resolved_favor ?? 0),
    winRatePercent:
      source.winRatePercent !== undefined || source.win_rate_percent !== undefined
        ? Number(source.winRatePercent ?? source.win_rate_percent)
        : null,
    averageOdds: Number(source.averageOdds ?? source.average_odds ?? 0),
    topBetTypes,
    recentActive: normalizeBetArray(source.recentActive ?? source.recent_active),
    recentResolved: normalizeBetArray(source.recentResolved ?? source.recent_resolved),
    summary: String(source.summary ?? ''),
  };
};

const normalizeBetTargetState = (value: unknown): SpeculationEvent['targetState'] | undefined => {
  if (!isRecordValue(value)) {
    return undefined;
  }

  const signals = Array.isArray(value.signals)
    ? value.signals
        .filter(isRecordValue)
        .map(signal => ({
          label: String(signal.label ?? ''),
          value: String(signal.value ?? ''),
        }))
        .filter(signal => signal.label && signal.value)
    : [];

  return {
    type: String(value.type ?? 'region') as NonNullable<SpeculationEvent['targetState']>['type'],
    name: String(value.name ?? ''),
    summary: String(value.summary ?? ''),
    signals,
  };
};

const normalizeOddsFactors = (value: unknown): NonNullable<SpeculationEvent['oddsFactors']> => {
  if (!Array.isArray(value)) {
    return [];
  }

  return value
    .filter(isRecordValue)
    .map(factor => ({
      label: String(factor.label ?? ''),
      value: String(factor.value ?? ''),
      effect: typeof factor.effect === 'string' ? factor.effect : undefined,
    }))
    .filter(factor => factor.label && factor.value);
};

const normalizeSpeculationEvent = (
  value: SpeculationEvent | Record<string, unknown>
): SpeculationEvent => {
  const source = value as Record<string, unknown>;
  const rawTimeframe = source.timeframe;
  const timeframe = isRecordValue(rawTimeframe)
    ? {
        minimum: Number(rawTimeframe.minimum ?? 1),
        maximum: Number(rawTimeframe.maximum ?? 1),
      }
    : { minimum: 1, maximum: 1 };

  const rawBettingOptions = source.bettingOptions ?? source.betting_options;
  const bettingOptions = Array.isArray(rawBettingOptions)
    ? rawBettingOptions.filter(isRecordValue).map((option: Record<string, unknown>) => ({
        id: String(option.id ?? ''),
        description: String(option.description ?? ''),
        betType:
          option.betType || option.bet_type
            ? (String(option.betType ?? option.bet_type) as DivineBet['betType'])
            : undefined,
        targetId:
          option.targetId || option.target_id
            ? String(option.targetId ?? option.target_id)
            : undefined,
        currentOdds: Number(option.currentOdds ?? option.current_odds ?? 0),
        minimumStake: Number(option.minimumStake ?? option.minimum_stake ?? 0),
        potentialPayout: Number(option.potentialPayout ?? option.potential_payout ?? 0),
        payoutProfile: normalizeBetPayoutProfile(option.payoutProfile ?? option.payout_profile),
        timeframe:
          option.timeframe !== undefined || option.timeframe_years !== undefined
            ? Number(option.timeframe ?? option.timeframe_years)
            : undefined,
        confidence:
          option.confidence !== undefined
            ? (String(option.confidence) as DivineBet['confidence'])
            : undefined,
        targetState: normalizeBetTargetState(option.targetState ?? option.target_state),
        oddsFactors: normalizeOddsFactors(option.oddsFactors ?? option.odds_factors),
      }))
    : [];

  return {
    ...(value as SpeculationEvent),
    id: String(source.id ?? ''),
    title: String(source.title ?? ''),
    description: String(source.description ?? ''),
    targetId:
      source.targetId || source.target_id ? String(source.targetId ?? source.target_id) : undefined,
    regionId:
      source.regionId || source.region_id ? String(source.regionId ?? source.region_id) : undefined,
    settlementId:
      source.settlementId || source.settlement_id
        ? String(source.settlementId ?? source.settlement_id)
        : undefined,
    landmarkId:
      source.landmarkId || source.landmark_id
        ? String(source.landmarkId ?? source.landmark_id)
        : undefined,
    heroId: source.heroId || source.hero_id ? String(source.heroId ?? source.hero_id) : undefined,
    eventType: String(
      source.eventType ?? source.event_type ?? 'settlement_growth'
    ) as DivineBet['betType'],
    targetState: normalizeBetTargetState(source.targetState ?? source.target_state),
    oddsFactors: normalizeOddsFactors(source.oddsFactors ?? source.odds_factors),
    timeframe,
    bettingOptions,
  };
};

export const getRegions = (): Promise<Region[]> => {
  return fetchData<Array<Region | Record<string, unknown>>>('regions').then(regions =>
    regions.map(normalizeRegion)
  );
};

export const getRegionById = (id: string): Promise<Region | Record<string, unknown>> => {
  return fetchData<Region | Record<string, unknown>>(`regions/${id}`).then(region =>
    isRecordValue(region) ? normalizeRegion(region) : region
  );
};

export const getHeroes = (): Promise<Hero[]> => {
  return fetchData<Array<Hero | Record<string, unknown>>>('heroes').then(heroes =>
    heroes.map(normalizeHero)
  );
};

export const getHeroById = (id: string): Promise<Hero | Record<string, unknown>> => {
  return fetchData<Hero | Record<string, unknown>>(`heroes/${id}`).then(hero =>
    isRecordValue(hero) ? normalizeHero(hero) : hero
  );
};

export const getChampionStatus = (): Promise<ChampionStatusResponse> => {
  return fetchData<ChampionStatusResponse | Record<string, unknown>>('champions').then(
    normalizeChampionStatusResponse
  );
};

export const designateChampion = (heroId: string): Promise<ChampionActionResponse> => {
  return postData<Record<string, never>, ChampionActionResponse | Record<string, unknown>>(
    `heroes/${heroId}/champion`,
    {}
  ).then(normalizeChampionActionResponse);
};

export const cultivateChampion = (
  heroId: string,
  focus: ChampionFocus
): Promise<ChampionActionResponse> => {
  return postData<{ focus: ChampionFocus }, ChampionActionResponse | Record<string, unknown>>(
    `heroes/${heroId}/champion/cultivate`,
    { focus }
  ).then(normalizeChampionActionResponse);
};

export const getArtifacts = (): Promise<ArtifactStatusResponse> => {
  return fetchData<ArtifactStatusResponse | Record<string, unknown>>('artifacts').then(
    normalizeArtifactStatusResponse
  );
};

export const createArtifact = (payload: CreateArtifactPayload): Promise<ArtifactActionResponse> => {
  return postData<CreateArtifactPayload, ArtifactActionResponse | Record<string, unknown>>(
    'artifacts',
    payload
  ).then(normalizeArtifactActionResponse);
};

export const empowerArtifact = (artifactId: string): Promise<ArtifactActionResponse> => {
  return postData<Record<string, never>, ArtifactActionResponse | Record<string, unknown>>(
    `artifacts/${artifactId}/empower`,
    {}
  ).then(normalizeArtifactActionResponse);
};

export const transferArtifact = (
  artifactId: string,
  payload: TransferArtifactPayload
): Promise<ArtifactActionResponse> => {
  return postData<TransferArtifactPayload, ArtifactActionResponse | Record<string, unknown>>(
    `artifacts/${artifactId}/transfer`,
    payload
  ).then(normalizeArtifactActionResponse);
};

export const stabilizeArtifact = (artifactId: string): Promise<ArtifactActionResponse> => {
  return postData<Record<string, never>, ArtifactActionResponse | Record<string, unknown>>(
    `artifacts/${artifactId}/stabilize`,
    {}
  ).then(normalizeArtifactActionResponse);
};

export const getWeatherStatus = (): Promise<WeatherStatusResponse> => {
  return fetchData<WeatherStatusResponse | Record<string, unknown>>('weather').then(
    normalizeWeatherStatusResponse
  );
};

export const nudgeWeather = (payload: WeatherNudgePayload): Promise<WeatherActionResponse> => {
  return postData<WeatherNudgePayload, WeatherActionResponse | Record<string, unknown>>(
    'weather/nudge',
    payload
  ).then(normalizeWeatherActionResponse);
};

export const getTemporalOmens = (): Promise<TemporalOmenStatusResponse> => {
  return fetchData<TemporalOmenStatusResponse | Record<string, unknown>>('omens').then(
    normalizeTemporalOmenStatusResponse
  );
};

export const readTemporalOmen = (
  payload: TemporalOmenReadPayload
): Promise<TemporalOmenActionResponse> => {
  return postData<TemporalOmenReadPayload, TemporalOmenActionResponse | Record<string, unknown>>(
    'omens',
    payload
  ).then(normalizeTemporalOmenActionResponse);
};

export const getMagicDiscovery = (): Promise<MagicDiscoveryStatusResponse> => {
  return fetchData<MagicDiscoveryStatusResponse | Record<string, unknown>>('magic').then(
    normalizeMagicDiscoveryStatusResponse
  );
};

export const researchMagic = (payload: MagicResearchPayload): Promise<MagicResearchResponse> => {
  return postData<MagicResearchPayload, MagicResearchResponse | Record<string, unknown>>(
    'magic/research',
    payload
  ).then(normalizeMagicResearchResponse);
};

export const getMythology = (): Promise<MythologyStatusResponse> => {
  return fetchData<MythologyStatusResponse | Record<string, unknown>>('myths').then(
    normalizeMythologyStatusResponse
  );
};

export const promoteMyth = (payload: PromoteMythPayload): Promise<PromoteMythResponse> => {
  return postData<PromoteMythPayload, PromoteMythResponse | Record<string, unknown>>(
    'myths/promote',
    payload
  ).then(normalizePromoteMythResponse);
};

export const getCivilization = (): Promise<CivilizationStatusResponse> => {
  return fetchData<CivilizationStatusResponse | Record<string, unknown>>('civilization').then(
    normalizeCivilizationStatusResponse
  );
};

export const getPantheon = (): Promise<PantheonStatusResponse> => {
  return fetchData<PantheonStatusResponse | Record<string, unknown>>('pantheon').then(
    normalizePantheonStatusResponse
  );
};

export const counterplayPantheon = (
  deityId: string,
  payload: PantheonCounterplayPayload
): Promise<PantheonCounterplayResponse> => {
  return postData<PantheonCounterplayPayload, PantheonCounterplayResponse | Record<string, unknown>>(
    `pantheon/${deityId}/counterplay`,
    payload
  ).then(normalizePantheonCounterplayResponse);
};

export const advanceCivilization = (
  payload: AdvanceCivilizationPayload = {}
): Promise<CivilizationAdvanceResponse> => {
  return postData<AdvanceCivilizationPayload, CivilizationAdvanceResponse | Record<string, unknown>>(
    'civilization/advance',
    payload
  ).then(normalizeCivilizationAdvanceResponse);
};

export const getGameEvents = (
  page: number = 1,
  limit: number = 10,
  filters: GameEventFilters = {}
): Promise<GameEvent[]> => {
  const params = new URLSearchParams({
    page: String(page),
    limit: String(limit),
    offset: String(Math.max(0, (page - 1) * limit)),
  });

  Object.entries(filters).forEach(([key, value]) => {
    if (value) {
      params.set(key, value);
    }
  });

  return fetchData<Array<GameEvent | Record<string, unknown>>>(`events?${params.toString()}`).then(
    events => events.map(normalizeGameEvent)
  );
};

export const getGameEventById = (id: string): Promise<GameEvent> => {
  return fetchData<GameEvent | Record<string, unknown>>(`events/${id}`).then(normalizeGameEvent);
};

export const getGameStatus = async (): Promise<GameStatus> => {
  return fetchData<GameStatus>('status');
};

export const getEntityHistorySummary = async (options?: {
  limit?: number;
  eventsPerEntity?: number;
  regionId?: string;
}): Promise<EntityHistorySummaryResponse> => {
  const params = new URLSearchParams();
  if (options?.limit) {
    params.set('limit', String(options.limit));
  }
  if (options?.eventsPerEntity) {
    params.set('eventsPerEntity', String(options.eventsPerEntity));
  }
  if (options?.regionId) {
    params.set('regionId', options.regionId);
  }

  const suffix = params.toString() ? `?${params.toString()}` : '';
  return fetchData<EntityHistorySummaryResponse>(`history/summary${suffix}`);
};

export interface InfluenceActionPayload {
  action: string;
  entityId: string;
  entityType: 'region' | 'hero';
}

export interface InfluenceActionResponse {
  success?: boolean;
  message?: string;
  data?: unknown;
  cost?: number;
  remainingDivineFavor?: number;
  target?: Region | Hero | Record<string, unknown>;
  before?: Record<string, unknown>;
  effectChanges?: Record<string, number>;
  resonanceEffect?: Region['influenceEffectiveness'];
}

export const sendInfluenceAction = (
  payload: InfluenceActionPayload
): Promise<InfluenceActionResponse> => {
  // Backend endpoint to be defined, e.g., /api/influence
  // For now, let's assume a generic endpoint that can differentiate based on entityType
  let path = '';
  if (payload.entityType === 'region') {
    path = `influence/region/${payload.entityId}`;
  } else if (payload.entityType === 'hero') {
    path = `influence/hero/${payload.entityId}`;
  } else {
    // Should not happen with current types, but good for robustness
    return Promise.reject(new Error('Invalid entity type for influence action'));
  }
  // The actual data sent might be just the action, as entityId is in the path
  // Or the backend might prefer the full payload. Adjust as needed.
  return postData<Omit<InfluenceActionPayload, 'entityId' | 'entityType'>, InfluenceActionResponse>(
    path,
    { action: payload.action }
  );
};

// ===== Settlement API =====
export const getSettlements = (): Promise<Settlement[]> => {
  return fetchData<Array<Settlement | Record<string, unknown>>>('settlements').then(settlements =>
    settlements.map(normalizeSettlement)
  );
};

export const getSettlementById = (id: string): Promise<Settlement | Record<string, unknown>> => {
  return fetchData<Settlement | Record<string, unknown>>(`settlements/${id}`).then(settlement =>
    isRecordValue(settlement) ? normalizeSettlement(settlement) : settlement
  );
};

// ===== Building API =====
export const getBuildings = (): Promise<Building[]> => {
  return fetchData<Building[]>('buildings');
};

export const getBuildingById = (id: string): Promise<Building | Record<string, unknown>> => {
  return fetchData<Building | Record<string, unknown>>(`buildings/${id}`);
};

// ===== Landmark API =====
export const getLandmarks = (): Promise<Landmark[]> => {
  return fetchData<Array<Landmark | Record<string, unknown>>>('landmarks').then(landmarks =>
    landmarks.map(normalizeLandmark)
  );
};

export const getLandmarkById = (id: string): Promise<Landmark | Record<string, unknown>> => {
  return fetchData<Landmark | Record<string, unknown>>(`landmarks/${id}`).then(landmark =>
    isRecordValue(landmark) ? normalizeLandmark(landmark) : landmark
  );
};

// ===== Resource Node API =====
export const getResourceNodes = (): Promise<ResourceNode[]> => {
  return fetchData<Array<ResourceNode | Record<string, unknown>>>('resource-nodes').then(nodes =>
    nodes.map(normalizeResourceNode)
  );
};

export const getResourceNodeById = (
  id: string
): Promise<ResourceNode | Record<string, unknown>> => {
  return fetchData<ResourceNode | Record<string, unknown>>(`resource-nodes/${id}`).then(node =>
    isRecordValue(node) ? normalizeResourceNode(node) : node
  );
};

// ===== Divine Betting API =====
export interface CreateDivineBetPayload {
  betType: string;
  targetId: string;
  description: string;
  timeframe: number;
  confidence: string;
  divineFavorStake: number;
}

export type AdminWorldEditorEntityType =
  | 'regions'
  | 'settlements'
  | 'landmarks'
  | 'resources'
  | 'heroes';

export type AdminWorldEditorPayload = Record<string, unknown>;

export interface AdminWorldEditorOptions {
  regions: {
    statuses: string[];
    climateTypes: string[];
    culturalInfluences: string[];
  };
  settlements: {
    types: string[];
    statuses: string[];
  };
  landmarks: {
    types: string[];
    statuses: string[];
  };
  resources: {
    types: string[];
    statuses: string[];
  };
  heroes: {
    roles: string[];
    statuses: string[];
  };
}

export interface AdminWorldEditorStatusResponse {
  currentYear: number;
  entityTypes: AdminWorldEditorEntityType[];
  summary: Record<AdminWorldEditorEntityType, number>;
  options: AdminWorldEditorOptions;
  entities: Record<AdminWorldEditorEntityType, Array<Record<string, unknown>>>;
}

export interface AdminWorldEditorMutationResponse {
  entityType: AdminWorldEditorEntityType;
  action: 'created' | 'updated';
  entity: Record<string, unknown>;
  eventId: string;
  summary: string;
  status: AdminWorldEditorStatusResponse;
}

export const getAdminWorldEditor = (): Promise<AdminWorldEditorStatusResponse> => {
  return fetchData<AdminWorldEditorStatusResponse | Record<string, unknown>>(
    'admin/world-editor'
  ).then(value => value as AdminWorldEditorStatusResponse);
};

export const createAdminWorldEntity = (
  entityType: AdminWorldEditorEntityType,
  payload: AdminWorldEditorPayload
): Promise<AdminWorldEditorMutationResponse> => {
  return postData<AdminWorldEditorPayload, AdminWorldEditorMutationResponse | Record<string, unknown>>(
    `admin/world-editor/${encodeURIComponent(entityType)}`,
    payload
  ).then(value => value as AdminWorldEditorMutationResponse);
};

export const updateAdminWorldEntity = (
  entityType: AdminWorldEditorEntityType,
  id: string,
  payload: AdminWorldEditorPayload
): Promise<AdminWorldEditorMutationResponse> => {
  return putData<AdminWorldEditorPayload, AdminWorldEditorMutationResponse | Record<string, unknown>>(
    `admin/world-editor/${encodeURIComponent(entityType)}/${encodeURIComponent(id)}`,
    payload
  ).then(value => value as AdminWorldEditorMutationResponse);
};

export const placeDivineBet = (payload: CreateDivineBetPayload): Promise<DivineBet> => {
  return postData<CreateDivineBetPayload, DivineBet | Record<string, unknown>>(
    'bets',
    payload
  ).then(normalizeDivineBet);
};

export const getDivineBets = (): Promise<DivineBet[]> => {
  return fetchData<Array<DivineBet | Record<string, unknown>>>('bets').then(bets =>
    bets.map(normalizeDivineBet)
  );
};

export const getDivineBetSummary = (): Promise<DivineBetSummary> => {
  return fetchData<DivineBetSummary | Record<string, unknown>>('bets/summary').then(
    normalizeDivineBetSummary
  );
};

export const getDivineBetById = (id: string): Promise<DivineBet | Record<string, unknown>> => {
  return fetchData<DivineBet | Record<string, unknown>>(`bets/${id}`).then(bet =>
    isRecordValue(bet) ? normalizeDivineBet(bet) : bet
  );
};

export const getSpeculationEvents = (): Promise<SpeculationEvent[]> => {
  return fetchData<Array<SpeculationEvent | Record<string, unknown>>>('speculation-events').then(
    events => events.map(normalizeSpeculationEvent)
  );
};

export const getBettingOdds = (): Promise<BettingOdds[]> => {
  return fetchData<BettingOdds[]>('betting-odds');
};

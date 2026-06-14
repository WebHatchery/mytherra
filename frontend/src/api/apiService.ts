import { Region } from '../entities/region';
import { Hero } from '../entities/hero';
import { GameEvent } from '../entities/event';
import { Settlement } from '../entities/settlement';
import { Building } from '../entities/building';
import { Landmark } from '../entities/landmark';
import { ResourceNode } from '../entities/resourceNode';
import { DivineBet, SpeculationEvent, BettingOdds } from '../entities/divineBet';
import { apiClient } from './apiClient';
import { ApiError } from './types';

export interface GameStatus {
  currentYear: number;
  divineFavor: number; // Added divineFavor
  simulation?: {
    enabled: boolean;
    lastTickAt: string | null;
    lastTickResult: unknown;
    queue: {
      jobs: number | null;
      failedJobs: number | null;
      available: boolean;
      error?: string;
    };
  };
}

export interface ApiErrorBody {
  message?: string;
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
            typeof source.alignment.lastChange === 'string'
              ? source.alignment.lastChange
              : undefined,
        }
      : undefined,
    influenceActionCosts: isRecordValue(source.influenceActionCosts)
      ? (source.influenceActionCosts as Hero['influenceActionCosts'])
      : undefined,
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

export const getGameEvents = (
  page: number = 1,
  limit: number = 10,
  regionId?: string,
  heroId?: string
): Promise<GameEvent[]> => {
  const regionFilter = regionId ? `&regionId=${regionId}` : '';
  const heroFilter = heroId ? `&heroId=${heroId}` : '';
  return fetchData<GameEvent[]>(`events?page=${page}&limit=${limit}${regionFilter}${heroFilter}`);
};

export const getGameEventById = (id: string): Promise<GameEvent | Record<string, unknown>> => {
  return fetchData<GameEvent | Record<string, unknown>>(`events/${id}`);
};

export const getGameStatus = async (): Promise<GameStatus> => {
  return fetchData<GameStatus>('status');
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

export const placeDivineBet = (payload: CreateDivineBetPayload): Promise<DivineBet> => {
  return postData<CreateDivineBetPayload, DivineBet>('bets', payload);
};

export const getDivineBets = (): Promise<DivineBet[]> => {
  return fetchData<DivineBet[]>('bets');
};

export const getDivineBetById = (id: string): Promise<DivineBet | Record<string, unknown>> => {
  return fetchData<DivineBet | Record<string, unknown>>(`bets/${id}`);
};

export const getSpeculationEvents = (): Promise<SpeculationEvent[]> => {
  return fetchData<SpeculationEvent[]>('speculation-events');
};

export const getBettingOdds = (): Promise<BettingOdds[]> => {
  return fetchData<BettingOdds[]>('betting-odds');
};

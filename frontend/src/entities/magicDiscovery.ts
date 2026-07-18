export type MagicDiscoveryTargetType = 'region' | 'hero' | 'landmark';
export type MagicDiscoveryPathStatus = 'hidden' | 'emerging' | 'known';

export interface MagicDiscoveryPathOption {
  key: string;
  label: string;
  domain: string;
  summary: string;
  hiddenSummary: string;
}

export interface MagicDiscoveryTargetOption {
  key: MagicDiscoveryTargetType;
  label: string;
}

export interface MagicDiscoveryTarget {
  type: MagicDiscoveryTargetType;
  id: string;
  name: string;
  regionId?: string | null;
}

export interface MagicDiscoverySignal {
  label: string;
  value: string;
  summary: string;
}

export interface MagicDiscoveryChange {
  id: string;
  name: string;
  before: Record<string, unknown>;
  after: Record<string, unknown>;
  summary: string;
}

export interface MagicDiscoveryChanges {
  regions: MagicDiscoveryChange[];
  settlements: MagicDiscoveryChange[];
  resources: MagicDiscoveryChange[];
  heroes: MagicDiscoveryChange[];
  landmarks: MagicDiscoveryChange[];
}

export interface MagicDiscoveryHistoryEntry {
  eventId: string;
  title: string;
  summary: string;
  type: string;
  year: number;
  targetType: MagicDiscoveryTargetType;
  targetId: string;
  targetName: string;
  progress: number;
  status: MagicDiscoveryPathStatus;
}

export interface MagicDiscoveryProgression {
  id: string;
  path: string;
  pathLabel: string;
  status: MagicDiscoveryPathStatus;
  progress: number;
  maturity: number;
  progressGain: number;
  maturityGain: number;
  targetType: MagicDiscoveryTargetType;
  targetId: string;
  targetName: string;
  regionId?: string | null;
  year: number;
  eventId?: string | null;
  title: string;
  summary: string;
  type: string;
  changes: MagicDiscoveryChanges;
}

export interface MagicDiscoveryBettingHook {
  id: string;
  path: string;
  title: string;
  summary: string;
  betType: string;
  targetId: string;
  targetType: MagicDiscoveryTargetType;
  regionId?: string | null;
}

export interface MagicDiscoveryPath {
  key: string;
  label: string;
  domain: string;
  summary: string;
  hiddenSummary: string;
  status: MagicDiscoveryPathStatus;
  progress: number;
  evidenceScore: number;
  maturity: number;
  discoveryYear?: number | null;
  lastResearchedYear?: number | null;
  lastProgressionYear?: number | null;
  lastTarget?: MagicDiscoveryTarget | null;
  signals: MagicDiscoverySignal[];
  eventIds: string[];
  history: MagicDiscoveryHistoryEntry[];
  bettingHook?: MagicDiscoveryBettingHook | null;
  visibilitySummary: string;
}

export interface MagicDiscoverySuggestedTarget extends MagicDiscoveryTarget {
  bestPath: string;
  reason: string;
}

export interface MagicDiscoveryStatusResponse {
  currentYear: number;
  researchCost: number;
  summary: string;
  pathOptions: MagicDiscoveryPathOption[];
  paths: MagicDiscoveryPath[];
  progressionSummary: string;
  recentProgressions: MagicDiscoveryProgression[];
  targetOptions: MagicDiscoveryTargetOption[];
  suggestedTargets: MagicDiscoverySuggestedTarget[];
  bettingHooks: MagicDiscoveryBettingHook[];
}

export interface MagicDiscoveryTickSummary {
  processed?: number;
  changed?: number;
  events?: number;
  progressions?: MagicDiscoveryProgression[];
  errors?: Array<string | { pathKey?: string | null; message?: string }>;
}

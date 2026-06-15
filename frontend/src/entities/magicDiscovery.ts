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
  discoveryYear?: number | null;
  lastResearchedYear?: number | null;
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
  targetOptions: MagicDiscoveryTargetOption[];
  suggestedTargets: MagicDiscoverySuggestedTarget[];
  bettingHooks: MagicDiscoveryBettingHook[];
}

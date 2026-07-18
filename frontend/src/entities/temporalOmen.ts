export type TemporalOmenTargetType = 'world' | 'region' | 'hero';
export type TemporalOmenHorizon = 'near' | 'generation' | 'era';
export type TemporalOmenConfidence = 'high' | 'medium' | 'low';
export type TemporalOmenRiskBand = 'favorable' | 'uncertain' | 'dangerous' | 'dire';
export type TemporalOmenTone = 'good' | 'warn' | 'bad' | 'neutral';

export interface TemporalOmenOption {
  key: string;
  label: string;
  summary: string;
}

export interface TemporalOmenHorizonOption extends TemporalOmenOption {
  key: TemporalOmenHorizon;
  years: number;
  baseCost: number;
  confidence: TemporalOmenConfidence;
}

export interface TemporalOmenTargetOption extends TemporalOmenOption {
  key: TemporalOmenTargetType;
}

export interface TemporalOmenSignal {
  label: string;
  value: string;
  tone: TemporalOmenTone;
}

export interface TemporalOmenPrediction {
  type: string;
  title: string;
  summary: string;
  riskScore: number;
  riskBand: TemporalOmenRiskBand;
  confidence: TemporalOmenConfidence;
  relatedRegionIds: string[];
  relatedHeroIds: string[];
  relatedSettlementIds: string[];
  relatedResourceIds: string[];
}

export interface TemporalOmenChain {
  id: string;
  status: string;
  startedYear?: number | null;
  lastAdvancedYear?: number | null;
  nextYear?: number | null;
  step: number;
  maxSteps: number;
  lastRiskScore?: number;
  sourceEventId?: string | null;
  eventIds: string[];
  title: string;
  latestSummary: string;
}

export interface TemporalOmenEntry {
  id: string;
  eventId?: string | null;
  createdYear: number;
  targetYear: number;
  horizon: TemporalOmenHorizon;
  horizonLabel: string;
  horizonYears: number;
  targetType: TemporalOmenTargetType;
  targetId?: string | null;
  targetName: string;
  cost: number;
  confidence: TemporalOmenConfidence;
  riskBand: TemporalOmenRiskBand;
  summary: string;
  signals: TemporalOmenSignal[];
  predictions: TemporalOmenPrediction[];
  relatedRegionIds: string[];
  relatedHeroIds: string[];
  relatedSettlementIds: string[];
  relatedResourceIds: string[];
  chains?: TemporalOmenChain[];
  consistencyNote: string;
}

export interface TemporalOmenStatusResponse {
  currentYear: number;
  summary: string;
  targetOptions: TemporalOmenTargetOption[];
  horizonOptions: TemporalOmenHorizonOption[];
  currentOmen?: TemporalOmenEntry | null;
  recentOmens: TemporalOmenEntry[];
}

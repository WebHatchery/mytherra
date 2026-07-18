export type WeatherPatternKey =
  | 'gentle_rains'
  | 'drought'
  | 'protective_winds'
  | 'tempest'
  | 'arcane_mist';

export type WeatherIntensityKey = 'minor' | 'strong' | 'severe';

export interface WeatherPatternOption {
  key: WeatherPatternKey;
  label: string;
  summary: string;
  baseCost: number;
  travelEffect: string;
  conflictEffect: string;
}

export interface WeatherIntensityOption {
  key: WeatherIntensityKey;
  label: string;
  costMultiplier: number;
  effectMultiplier: number;
  risk: number;
  summary: string;
}

export interface WeatherSnapshot {
  id: string;
  name: string;
  prosperity?: number;
  chaos?: number;
  magicAffinity?: number;
  dangerLevel?: number;
  population?: number;
  defensibility?: number;
  output?: number;
  effectiveOutput?: number;
  status?: string;
  type?: string;
  climateType?: string;
}

export interface WeatherChangeDelta {
  before: string | number | boolean | null;
  after: string | number | boolean | null;
  delta?: number | null;
}

export interface WeatherEntityChange extends WeatherSnapshot {
  before: WeatherSnapshot;
  after: WeatherSnapshot;
  change: Record<string, WeatherChangeDelta>;
}

export interface WeatherRiskOutcome {
  type: 'none' | 'backlash';
  summary: string;
}

export interface WeatherConsequenceChain {
  id: string;
  status: string;
  startedYear?: number | null;
  lastAdvancedYear?: number | null;
  nextYear?: number | null;
  step: number;
  maxSteps: number;
  sourceEventId?: string | null;
  eventIds: string[];
  title: string;
  latestSummary: string;
}

export interface WeatherInfluenceEntry {
  id: string;
  eventId?: string | null;
  year: number;
  regionId: string;
  regionName: string;
  pattern: WeatherPatternKey;
  patternLabel: string;
  intensity: WeatherIntensityKey;
  intensityLabel: string;
  cost: number;
  summary: string;
  travelEffect: string;
  conflictEffect: string;
  riskOutcome: WeatherRiskOutcome;
  regionBefore: WeatherSnapshot;
  regionAfter: WeatherSnapshot;
  regionChange: Record<string, WeatherChangeDelta>;
  settlementChanges: WeatherEntityChange[];
  resourceChanges: WeatherEntityChange[];
  chains?: WeatherConsequenceChain[];
}

export interface WeatherStatusResponse {
  currentYear: number;
  summary: string;
  patternOptions: WeatherPatternOption[];
  intensityOptions: WeatherIntensityOption[];
  currentInfluence?: WeatherInfluenceEntry | null;
  recentInfluences: WeatherInfluenceEntry[];
}

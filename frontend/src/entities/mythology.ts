export type MythStrength = 'faint' | 'forming' | 'strong' | 'mythic';

export interface MythTypeOption {
  key: string;
  label: string;
}

export interface MythEffectTarget {
  id: string;
  name: string;
  trait?: string;
  summary: string;
}

export interface MythEffects {
  regions: MythEffectTarget[];
  heroes: MythEffectTarget[];
  landmarks: MythEffectTarget[];
  futureEventSignals: string[];
}

export interface MythCandidate {
  eventId: string;
  title: string;
  summary: string;
  sourceType: string;
  mythType: string;
  mythTypeLabel: string;
  score: number;
  strength: MythStrength;
  year?: number | null;
  regionId?: string | null;
  relatedRegionIds: string[];
  relatedHeroIds: string[];
  relatedSettlementIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
  reason: string;
  influencePreview: string;
}

export interface PromotedMyth {
  id: string;
  sourceEventId: string;
  promotionEventId: string;
  title: string;
  summary: string;
  mythType: string;
  mythTypeLabel: string;
  sourceType: string;
  strength: MythStrength;
  score: number;
  sourceYear?: number | null;
  promotedYear: number;
  regionId?: string | null;
  relatedRegionIds: string[];
  relatedHeroIds: string[];
  relatedSettlementIds: string[];
  relatedLandmarkIds: string[];
  relatedResourceIds: string[];
  reason: string;
  influenceSummary: string;
  effects: MythEffects;
}

export interface MythologyStatusResponse {
  currentYear: number;
  promotionCost: number;
  summary: string;
  mythTypeOptions: MythTypeOption[];
  myths: PromotedMyth[];
  candidates: MythCandidate[];
  influenceSummary: string;
}

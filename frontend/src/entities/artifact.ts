export type ArtifactFocus = 'protection' | 'prosperity' | 'war' | 'knowledge';
export type ArtifactOwnerType = 'hero' | 'region' | 'landmark' | 'unbound';
export type ArtifactStatus = 'active' | 'corrupted' | 'lost';
export type ArtifactRiskTier = 'low' | 'rising' | 'high' | 'critical';

export interface ArtifactFocusOption {
  key: ArtifactFocus;
  label: string;
  summary: string;
}

export interface ArtifactHistoryEntry {
  eventId: string;
  title: string;
  summary: string;
  type: string;
  year?: number | null;
  ownerType: ArtifactOwnerType;
  ownerName: string;
  status: ArtifactStatus;
  powerLevel: number;
  instability: number;
  corruption: number;
}

export interface DivineArtifact {
  id: string;
  name: string;
  focus: ArtifactFocus;
  focusLabel: string;
  createdYear: number;
  powerLevel: number;
  instability: number;
  corruption: number;
  status: ArtifactStatus;
  ownerType: ArtifactOwnerType;
  ownerId?: string | null;
  ownerName: string;
  ownerRegionId?: string | null;
  eventIds: string[];
  history: ArtifactHistoryEntry[];
  empowerCost: number;
  stabilizeCost: number;
  riskTier: ArtifactRiskTier;
  summary: string;
}

export interface ArtifactStatusResponse {
  artifactLimit: number;
  activeCount: number;
  creationCost: number;
  empowerBaseCost: number;
  transferCost: number;
  stabilizeBaseCost: number;
  focusOptions: ArtifactFocusOption[];
  summary: string;
  artifacts: DivineArtifact[];
}

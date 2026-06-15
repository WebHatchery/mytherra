// F:\WebDevelopment\Mytherra\frontend\src\entities\event.ts

/**
 * Represents a significant event that occurs in the world.
 */
export interface GameEvent {
  id: string;
  title: string;
  timestamp: string; // ISO date string
  description: string;
  type: string;
  status: string;
  regionId?: string | null;
  relatedRegionIds?: string[];
  relatedHeroIds?: string[];
  relatedSettlementIds?: string[];
  relatedLandmarkIds?: string[];
  relatedResourceIds?: string[];
  year?: number; // Add year to the frontend entity
  createdAt?: string;
  updatedAt?: string;
}

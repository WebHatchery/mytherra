<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GameEvent;
use App\Models\Hero;
use App\Models\Landmark;
use App\Models\Region;
use App\Models\ResourceNode;
use App\Models\Settlement;
use Illuminate\Support\Collection;

class EraGenerationService
{
    private const MAX_TARGET_REGIONS = 2;

    public function generate(
        int $transitionEra,
        int $nextEra,
        int $currentYear,
        array $eraLegacy,
        array $eraPressure
    ): array {
        $regions = $this->targetRegions($eraLegacy, $eraPressure);
        if ($regions->isEmpty()) {
            return $this->emptyResult();
        }

        $settlements = [];
        $heroes = [];
        $landmarks = [];
        $resources = [];

        foreach ($regions as $index => $region) {
            $settlement = $this->createSettlement($region, $nextEra, $currentYear, $index);
            $settlements[] = $settlement;
            $heroes[] = $this->createHero($region, $settlement, $transitionEra, $nextEra, $eraLegacy, $index);
            $resources[] = $this->createResource($region, $settlement, $nextEra, $index);

            if ($index === 0) {
                $landmarks[] = $this->createLandmark($region, $transitionEra, $nextEra, $currentYear, $eraPressure);
            }
        }

        $eventId = $this->recordGenerationEvent($transitionEra, $nextEra, $currentYear, $settlements, $heroes, $landmarks, $resources);

        return [
            'processed' => count($settlements) + count($heroes) + count($landmarks) + count($resources),
            'changed' => count($settlements) + count($heroes) + count($landmarks) + count($resources),
            'eventId' => $eventId,
            'summary' => $this->summary($nextEra, $settlements, $heroes, $landmarks, $resources),
            'settlements' => $settlements,
            'heroes' => $heroes,
            'landmarks' => $landmarks,
            'resources' => $resources,
        ];
    }

    private function targetRegions(array $eraLegacy, array $eraPressure): Collection
    {
        $preferredIds = array_values(array_unique(array_filter([
            ...($eraLegacy['relatedRegionIds'] ?? []),
            ...($eraPressure['relatedRegionIds'] ?? []),
        ])));

        $regions = Region::all();
        $preferred = $regions->filter(
            fn(Region $region): bool => in_array((string)$region->id, $preferredIds, true)
        );

        $fallback = $regions
            ->reject(fn(Region $region): bool => $preferred->contains('id', $region->id))
            ->sortByDesc(
                fn(Region $region): int => (int)$region->prosperity + (int)$region->magic_affinity - (int)$region->chaos
            );

        return $preferred
            ->concat($fallback)
            ->take(self::MAX_TARGET_REGIONS)
            ->values();
    }

    private function createSettlement(Region $region, int $nextEra, int $currentYear, int $index): array
    {
        $population = max(120, min(850, (int)round(((int)$region->population_total / 18) + (180 - ($index * 45)))));
        $prosperity = $this->clamp(((int)$region->prosperity * 0.72) + 18 - ($index * 4), 20, 92);
        $defensibility = $this->clamp(((int)$region->danger_level * 0.35) + 28 + ($index * 6), 18, 86);
        $type = $population >= 501 ? 'town' : 'village';
        $status = $prosperity >= 68 ? 'prosperous' : 'stable';
        $name = $this->settlementName($region, $nextEra, $index);
        $id = $this->uniqueId('settlement', 'settlement-era-');

        Settlement::create([
            'id' => $id,
            'region_id' => $region->id,
            'name' => $name,
            'type' => $type,
            'population' => $population,
            'prosperity' => $prosperity,
            'defensibility' => $defensibility,
            'status' => $status,
            'specializations' => $this->settlementSpecializations($region),
            'events' => ["Founded at the dawn of Era {$nextEra}"],
            'founded_year' => $currentYear,
            'last_event_year' => $currentYear,
            'traits' => ['era_born', "era_{$nextEra}_founded", $this->regionIdentityTrait($region)],
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'regionId' => $region->id,
            'regionName' => $region->name,
            'type' => $type,
            'population' => $population,
            'status' => $status,
            'summary' => "{$name} was founded in {$region->name} as an Era {$nextEra} {$type}.",
        ];
    }

    private function createHero(
        Region $region,
        array $settlement,
        int $transitionEra,
        int $nextEra,
        array $eraLegacy,
        int $index
    ): array {
        $seed = $eraLegacy['bloodlineSeeds'][$index] ?? $eraLegacy['heroLegacies'][$index] ?? null;
        $seedName = is_array($seed) ? (string)($seed['name'] ?? $seed['sourceHeroName'] ?? '') : '';
        $name = $seedName !== ''
            ? "Heir of {$seedName}"
            : $this->heroName($region, $nextEra, $index);
        $role = $this->heroRoleForRegion($region, $index);
        $level = max(1, min(8, (int)round(((int)$region->magic_affinity + (int)$region->prosperity) / 30) + $index));
        $id = $this->uniqueId('hero', 'hero-era-');

        Hero::create([
            'id' => $id,
            'name' => $name,
            'region_id' => $region->id,
            'role' => $role,
            'description' => "{$name} rose near {$settlement['name']} as Era {$nextEra} began.",
            'feats' => [
                "Born into Era {$nextEra}",
                $seedName !== '' ? "Carries a lineage remembered from Era {$transitionEra}" : "Answered the first call of Era {$nextEra}",
            ],
            'level' => $level,
            'is_alive' => true,
            'age' => 18 + ($index * 3),
            'death_reason' => null,
            'personality_traits' => ['era_born', $this->regionIdentityTrait($region)],
            'alignment' => ['good' => 55 + ($index * 5), 'chaotic' => 45 + ((int)$region->chaos > 45 ? 10 : 0)],
            'status' => 'living',
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'regionId' => $region->id,
            'regionName' => $region->name,
            'role' => $role,
            'level' => $level,
            'lineageSource' => $seedName !== '' ? $seedName : null,
            'summary' => "{$name} emerged as a {$role} of Era {$nextEra}.",
        ];
    }

    private function createLandmark(
        Region $region,
        int $transitionEra,
        int $nextEra,
        int $currentYear,
        array $eraPressure
    ): array {
        $type = $this->landmarkTypeFor($region, $eraPressure);
        $status = (int)$region->magic_affinity >= 55 ? 'awakened' : 'pristine';
        $magic = $this->clamp(((int)$region->magic_affinity * 0.72) + 18, 15, 95);
        $danger = $this->clamp(((int)$region->danger_level * 0.55) + ((int)$region->chaos > 50 ? 18 : 6), 8, 88);
        $name = $this->landmarkName($region, $nextEra, $type);
        $id = $this->uniqueId('landmark', 'landmark-era-');

        Landmark::create([
            'id' => $id,
            'region_id' => $region->id,
            'name' => $name,
            'type' => $type,
            'description' => "{$name} formed from the aftershocks of Era {$transitionEra}, anchoring {$region->name} in Era {$nextEra}.",
            'status' => $status,
            'magic_level' => $magic,
            'danger_level' => $danger,
            'discovered_year' => $currentYear,
            'last_visited_year' => null,
            'associated_events' => [],
            'traits' => ['era_born', "legacy_of_era_{$transitionEra}", "era_{$nextEra}_anchor"],
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'regionId' => $region->id,
            'regionName' => $region->name,
            'type' => $type,
            'status' => $status,
            'summary' => "{$name} became a new Era {$nextEra} {$type}.",
        ];
    }

    private function createResource(Region $region, array $settlement, int $nextEra, int $index): array
    {
        $type = $this->resourceTypeFor($region, $index);
        $output = $this->clamp(((int)$region->prosperity * 0.45) + ((int)$region->magic_affinity * 0.25) + 25 - ($index * 5), 25, 92);
        $status = $output >= 75 ? 'status-flourishing' : ((int)$region->magic_affinity >= 70 ? 'blessed' : 'active');
        $name = $this->resourceName($region, $type, $nextEra);
        $id = $this->uniqueId('resource', 'resource-era-');

        ResourceNode::create([
            'id' => $id,
            'region_id' => $region->id,
            'settlement_id' => $settlement['id'],
            'type' => $type,
            'name' => $name,
            'output' => $output,
            'status' => $status,
        ]);

        return [
            'id' => $id,
            'name' => $name,
            'regionId' => $region->id,
            'settlementId' => $settlement['id'],
            'type' => $type,
            'output' => $output,
            'status' => $status,
            'summary' => "{$name} opened near {$settlement['name']} with {$output} output.",
        ];
    }

    private function recordGenerationEvent(
        int $transitionEra,
        int $nextEra,
        int $currentYear,
        array $settlements,
        array $heroes,
        array $landmarks,
        array $resources
    ): string {
        $eventId = 'event-' . bin2hex(random_bytes(8));
        $description = $this->summary($nextEra, $settlements, $heroes, $landmarks, $resources) .
            " These foundations grew from the transformed remnants of Era {$transitionEra}.";

        GameEvent::create([
            'id' => $eventId,
            'title' => "Era {$nextEra} First Foundations",
            'description' => $description,
            'type' => 'era_generation',
            'status' => 'completed',
            'region_id' => $settlements[0]['regionId'] ?? null,
            'timestamp' => date('c'),
            'related_region_ids' => array_values(array_unique(array_filter(array_map(
                fn(array $item): ?string => $item['regionId'] ?? null,
                [...$settlements, ...$heroes, ...$landmarks]
            )))),
            'related_hero_ids' => array_column($heroes, 'id'),
            'related_settlement_ids' => array_column($settlements, 'id'),
            'related_landmark_ids' => array_column($landmarks, 'id'),
            'related_resource_ids' => array_column($resources, 'id'),
            'year' => $currentYear,
        ]);

        return $eventId;
    }

    private function summary(int $nextEra, array $settlements, array $heroes, array $landmarks, array $resources): string
    {
        return "Era {$nextEra} began with " .
            count($settlements) . ' new settlements, ' .
            count($heroes) . ' era-born heroes, ' .
            count($landmarks) . ' new landmarks, and ' .
            count($resources) . ' renewed resources.';
    }

    private function emptyResult(): array
    {
        return [
            'processed' => 0,
            'changed' => 0,
            'eventId' => null,
            'summary' => 'No new-era content was generated because no regions were available.',
            'settlements' => [],
            'heroes' => [],
            'landmarks' => [],
            'resources' => [],
        ];
    }

    private function settlementName(Region $region, int $nextEra, int $index): string
    {
        $prefixes = ['Dawn', 'First', 'Renewed', 'Bright'];
        return $prefixes[$index % count($prefixes)] . " {$region->name} Haven {$nextEra}";
    }

    private function heroName(Region $region, int $nextEra, int $index): string
    {
        $titles = ['Dawnwarden', 'Oathkeeper', 'Wayfinder', 'Star-Scribe'];
        return "{$titles[$index % count($titles)]} of {$region->name} {$nextEra}";
    }

    private function landmarkName(Region $region, int $nextEra, string $type): string
    {
        $label = match ($type) {
            'temple' => 'Sanctum',
            'grove' => 'Grove',
            'tower' => 'Spire',
            'battlefield' => 'Memorial',
            default => 'Marker',
        };

        return "Era {$nextEra} {$region->name} {$label}";
    }

    private function resourceName(Region $region, string $type, int $nextEra): string
    {
        $label = match ($type) {
            'magical_spring' => 'Wellspring',
            'forest' => 'Greenline',
            'quarry' => 'Stonecut',
            'fishing' => 'Waters',
            'herb_garden' => 'Herbarium',
            default => 'Fields',
        };

        return "{$region->name} Era {$nextEra} {$label}";
    }

    private function settlementSpecializations(Region $region): array
    {
        $specializations = ['frontier'];
        if ((int)$region->magic_affinity >= 55) {
            $specializations[] = 'research';
        }
        if ((int)$region->danger_level >= 45) {
            $specializations[] = 'defense';
        }
        if ((int)$region->prosperity >= 65) {
            $specializations[] = 'trade';
        }

        return array_values(array_unique($specializations));
    }

    private function heroRoleForRegion(Region $region, int $index): string
    {
        if ((string)$region->cultural_influence === 'martial' || (int)$region->danger_level >= 55) {
            return 'warrior';
        }
        if ((int)$region->magic_affinity >= 65) {
            return $index % 2 === 0 ? 'scholar' : 'prophet';
        }
        if ((string)$region->cultural_influence === 'scholarly') {
            return 'scholar';
        }

        return $index % 2 === 0 ? 'prophet' : 'agent of change';
    }

    private function landmarkTypeFor(Region $region, array $eraPressure): string
    {
        $triggerCode = (string)($eraPressure['highestTrigger']['code'] ?? '');
        if ($triggerCode === 'conquest' || (string)$region->cultural_influence === 'martial') {
            return 'battlefield';
        }
        if ($triggerCode === 'magical_rupture' || (int)$region->magic_affinity >= 70) {
            return 'tower';
        }
        if ((int)$region->chaos >= 50 || (int)$region->danger_level >= 60) {
            return 'ruin';
        }
        if ((string)$region->climate_type === 'magical' || (int)$region->magic_affinity >= 50) {
            return 'grove';
        }

        return 'temple';
    }

    private function resourceTypeFor(Region $region, int $index): string
    {
        if ((int)$region->magic_affinity >= 70) {
            return 'magical_spring';
        }
        if ((string)$region->climate_type === 'arid') {
            return 'quarry';
        }
        if ((string)$region->climate_type === 'tropical' || (string)$region->climate_type === 'temperate') {
            return $index % 2 === 0 ? 'farmland' : 'forest';
        }

        return $index % 2 === 0 ? 'herb_garden' : 'fishing';
    }

    private function regionIdentityTrait(Region $region): string
    {
        $culture = trim((string)($region->cultural_influence ?? 'frontier'));
        return $culture !== '' ? "{$culture}_legacy" : 'frontier_legacy';
    }

    private function uniqueId(string $model, string $prefix): string
    {
        $class = match ($model) {
            'settlement' => Settlement::class,
            'hero' => Hero::class,
            'landmark' => Landmark::class,
            'resource' => ResourceNode::class,
            default => throw new \InvalidArgumentException("Unknown model '{$model}'"),
        };

        do {
            $id = $prefix . bin2hex(random_bytes(6));
        } while ($class::where('id', $id)->exists());

        return $id;
    }

    private function clamp(int|float $value, int $min = 0, int $max = 100): int
    {
        return (int)max($min, min($max, round($value)));
    }
}

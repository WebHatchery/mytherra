<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Region;
use App\Models\Hero;
use App\Models\Player;
use App\Models\GameEvent;
use App\Models\GameState;
use App\Services\DivineInfluenceService;
use App\Repositories\InfluenceRepository;
use App\Utils\Logger;
use Exception;

class InfluenceActions
{
    private DivineInfluenceService $divineService;
    private InfluenceRepository $influenceRepo;

    public function __construct(DivineInfluenceService $divineService, InfluenceRepository $influenceRepo)
    {
        $this->divineService = $divineService;
        $this->influenceRepo = $influenceRepo;
    }

    /**
     * Calculate divine influence cost
     */
    public function calculateDivineInfluenceCost(array $params): array
    {
        $request = \App\Utils\DTOs\DivineInfluenceRequest::fromArray($params);
        $errors = $request->validate();

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        return $this->divineService->calculateInfluenceCost(
            $request->targetId,
            $request->targetType,
            $request->influenceType,
            $request->strength
        );
    }

    /**
     * Apply divine influence
     */
    public function applyDivineInfluence(array $params): array
    {
        $request = \App\Utils\DTOs\DivineInfluenceRequest::fromArray($params);
        $errors = $request->validate();

        if (!empty($errors)) {
            return [
            'success' => false,
            'errors' => $errors
            ];
        }

        return $this->divineService->applyInfluence(
            $request->targetId,
            $request->targetType,
            $request->influenceType,
            $request->strength,
            $request->description ?? 'Divine influence applied'
        );
    }

    /**
     * Empower a hero
     */
    public function empowerHero(array $params): array
    {
        if (!isset($params['hero_id'])) {
            throw new Exception('hero_id is required');
        }
        if (!isset($params['amount'])) {
            throw new Exception('amount is required');
        }

        $hero = Hero::find($params['hero_id']);
        if (!$hero) {
            Logger::info("Hero not found", ['heroId' => $params['hero_id']]);
            throw new Exception('Hero not found');
        }

        return [
            'id' => uniqid('influence-'),
            'hero_id' => $params['hero_id'],
            'amount' => $params['amount'],
            'type' => 'empower',
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Guide a hero
     */
    public function guideHero(array $params): array
    {
        if (!isset($params['hero_id'])) {
            throw new Exception('hero_id is required');
        }
        if (!isset($params['destination_id'])) {
            throw new Exception('destination_id is required');
        }

        $hero = Hero::find($params['hero_id']);
        if (!$hero) {
            Logger::info("Hero not found", ['heroId' => $params['hero_id']]);
            throw new Exception('Hero not found');
        }

        return [
            'id' => uniqid('influence-'),
            'hero_id' => $params['hero_id'],
            'destination_id' => $params['destination_id'],
            'type' => 'guide',
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Guide region research
     */
    public function guideRegionResearch(array $params): array
    {
        if (!isset($params['region_id'])) {
            throw new Exception('region_id is required');
        }
        if (!isset($params['research_type'])) {
            throw new Exception('research_type is required');
        }

        $region = Region::find($params['region_id']);
        if (!$region) {
            Logger::info("Region not found", ['regionId' => $params['region_id']]);
            throw new Exception('Region not found');
        }

        return [
            'id' => uniqid('influence-'),
            'region_id' => $params['region_id'],
            'research_type' => $params['research_type'],
            'type' => 'guide-research',
            'status' => 'completed',
            'created_at' => date('Y-m-d H:i:s')
        ];
    }

    public function applyFrontendInfluenceAction(string $entityType, string $entityId, string $action): array
    {
        return match ($entityType) {
            'region' => $this->applyRegionAction($entityId, $action),
            'hero' => $this->applyHeroAction($entityId, $action),
            default => [
                'success' => false,
                'message' => "Unsupported influence target type: {$entityType}"
            ]
        };
    }

    private function applyRegionAction(string $regionId, string $action): array
    {
        $region = Region::find($regionId);
        if (!$region) {
            return [
                'success' => false,
                'message' => 'Region not found'
            ];
        }

        $costs = [
            'Bless Region' => 15,
            'Corrupt Region' => 15,
            'Guide Research in Region' => 12
        ];

        if (!isset($costs[$action])) {
            return [
                'success' => false,
                'message' => "Unsupported region influence action: {$action}"
            ];
        }

        $player = Player::getSinglePlayer();
        $cost = $costs[$action];
        if (!$player->spendDivineFavor($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => $cost
            ];
        }

        $before = [
            'prosperity' => (int)$region->prosperity,
            'chaos' => (int)$region->chaos,
            'magicAffinity' => (int)$region->magic_affinity,
            'dangerLevel' => (int)($region->danger_level ?? 0),
            'status' => $region->status
        ];

        switch ($action) {
            case 'Bless Region':
                $region->prosperity = min(100, (int)$region->prosperity + 8);
                $region->chaos = max(0, (int)$region->chaos - 4);
                $region->danger_level = max(0, (int)($region->danger_level ?? 0) - 3);
                if ($region->prosperity >= 80 && $region->chaos <= 30) {
                    $region->status = 'blessed';
                }
                $description = "Divine blessing steadied {$region->name}, lifting prosperity and calming unrest.";
                break;

            case 'Corrupt Region':
                $region->chaos = min(100, (int)$region->chaos + 8);
                $region->danger_level = min(100, (int)($region->danger_level ?? 0) + 5);
                $region->prosperity = max(0, (int)$region->prosperity - 3);
                if ($region->chaos >= 70 || $region->danger_level >= 70) {
                    $region->status = 'cursed';
                }
                $description = "A corrupting omen darkened {$region->name}, stirring chaos and danger.";
                break;

            case 'Guide Research in Region':
                $region->magic_affinity = min(100, (int)$region->magic_affinity + 7);
                $region->prosperity = min(100, (int)$region->prosperity + 2);
                $description = "Whispers of insight guided researchers in {$region->name} toward deeper magical understanding.";
                break;

            default:
                $description = "Divine influence touched {$region->name}.";
        }

        $region->influence_last_action = $action;
        $region->save();

        $this->recordInfluenceEvent(
            $description,
            'region_influence',
            $region->id,
            [$region->id],
            []
        );

        return [
            'success' => true,
            'message' => "{$action} applied to {$region->name}",
            'cost' => $cost,
            'remainingDivineFavor' => $player->fresh()->divine_favor,
            'target' => $region->fresh()->toArray(),
            'before' => $before
        ];
    }

    private function applyHeroAction(string $heroId, string $action): array
    {
        $hero = Hero::find($heroId);
        if (!$hero) {
            return [
                'success' => false,
                'message' => 'Hero not found'
            ];
        }

        $costs = $hero->getInfluenceCosts();
        $actionCosts = [
            'Guide Hero' => $costs['guideHero'] ?? 5,
            'Empower Hero' => $costs['empowerHero'] ?? 10,
            'Start Notable Event' => $costs['forceNotableEvent'] ?? 15,
            'Revive Hero' => $costs['reviveHero'] ?? 50
        ];

        if (!isset($actionCosts[$action])) {
            return [
                'success' => false,
                'message' => "Unsupported hero influence action: {$action}"
            ];
        }

        $player = Player::getSinglePlayer();
        $cost = (int)$actionCosts[$action];
        if (!$player->spendDivineFavor($cost)) {
            return [
                'success' => false,
                'message' => 'Insufficient divine favor',
                'cost' => $cost
            ];
        }

        $before = [
            'level' => (int)$hero->level,
            'age' => (int)$hero->age,
            'isAlive' => (bool)$hero->is_alive,
            'status' => $hero->status,
            'feats' => $hero->feats ?? []
        ];

        switch ($action) {
            case 'Guide Hero':
                $hero->addFeat('Received divine guidance in a moment of uncertainty.');
                $hero->updateAlignment(2, -1, 'Divine guidance');
                $description = "{$hero->name} received a guiding omen and found firmer purpose.";
                break;

            case 'Empower Hero':
                $hero->level = min(100, (int)$hero->level + 1);
                $hero->addFeat("Empowered by divine favor in year " . $this->getCurrentGameYear() . ".");
                $description = "{$hero->name} was empowered by divine favor and grew stronger.";
                break;

            case 'Start Notable Event':
                $hero->addFeat("Became the center of a notable event in year " . $this->getCurrentGameYear() . ".");
                $description = "{$hero->name} was drawn into a notable event by divine interference.";
                break;

            case 'Revive Hero':
                if ($hero->is_alive) {
                    $player->addDivineFavor($cost);
                    return [
                        'success' => false,
                        'message' => "{$hero->name} is already alive",
                        'cost' => $cost
                    ];
                }
                $hero->is_alive = true;
                $hero->status = 'living';
                $hero->death_reason = null;
                $hero->addFeat("Returned from death by divine intervention in year " . $this->getCurrentGameYear() . ".");
                $description = "{$hero->name} returned from death through divine intervention.";
                break;

            default:
                $description = "Divine influence touched {$hero->name}.";
        }

        $hero->influence_last_action = $action;
        $hero->save();

        $this->recordInfluenceEvent(
            $description,
            'hero_influence',
            $hero->region_id,
            [$hero->region_id],
            [$hero->id]
        );

        return [
            'success' => true,
            'message' => "{$action} applied to {$hero->name}",
            'cost' => $cost,
            'remainingDivineFavor' => $player->fresh()->divine_favor,
            'target' => $hero->fresh()->toArray(),
            'before' => $before
        ];
    }

    private function recordInfluenceEvent(
        string $description,
        string $type,
        ?string $regionId,
        array $relatedRegionIds,
        array $relatedHeroIds
    ): void {
        try {
            GameEvent::create([
                'id' => 'event-' . bin2hex(random_bytes(8)),
                'title' => 'Divine Influence',
                'description' => $description,
                'type' => $type,
                'status' => 'completed',
                'region_id' => $regionId,
                'timestamp' => date('c'),
                'related_region_ids' => $relatedRegionIds,
                'related_hero_ids' => $relatedHeroIds,
                'year' => $this->getCurrentGameYear()
            ]);
        } catch (\Exception $error) {
            Logger::error('Error recording influence event: ' . $error->getMessage());
        }
    }

    private function getCurrentGameYear(): int
    {
        return (int)(GameState::getCurrent()->current_year ?? 1);
    }
}

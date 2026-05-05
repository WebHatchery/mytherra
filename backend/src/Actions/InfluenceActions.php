<?php

namespace App\Actions;

use App\Models\Region;
use App\Models\Hero;
use App\Models\Player;
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
}

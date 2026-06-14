<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Request;
use App\Actions\InfluenceActions;
use App\Traits\ApiResponseTrait;
use App\Helpers\Logger;
use Exception;

class InfluenceController
{
    use ApiResponseTrait;

    public function __construct(
        private InfluenceActions $influenceActions
    ) {
    }

    /**
     * Calculate divine influence cost
     */
    public function calculateDivineInfluenceCost(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/influence/divine/calculate-cost endpoint called");

        $body = json_decode((string)$request->getBody(), true);

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->calculateDivineInfluenceCost($body),
            'calculating divine influence cost',
            'Failed to calculate divine influence cost'
        );
    }

    /**
     * Apply divine influence
     */
    public function applyDivineInfluence(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/influence/divine/apply endpoint called");

        $body = json_decode((string)$request->getBody(), true);

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->applyDivineInfluence($body),
            'applying divine influence',
            'Failed to apply divine influence'
        );
    }

    public function applyRegionInfluenceAction(Request $request, Response $response, array $args): Response
    {
        Logger::debug("POST /api/influence/region/{id} endpoint called");

        $body = json_decode((string)$request->getBody(), true) ?: [];
        $action = $body['action'] ?? '';

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->applyFrontendInfluenceAction('region', $args['id'], $action),
            'applying region influence action',
            'Failed to apply region influence action'
        );
    }

    public function applyHeroInfluenceAction(Request $request, Response $response, array $args): Response
    {
        Logger::debug("POST /api/influence/hero/{id} endpoint called");

        $body = json_decode((string)$request->getBody(), true) ?: [];
        $action = $body['action'] ?? '';

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->applyFrontendInfluenceAction('hero', $args['id'], $action),
            'applying hero influence action',
            'Failed to apply hero influence action'
        );
    }

    /**
     * Empower a hero with influence
     */
    public function empowerHero(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/influence/hero/empower endpoint called");

        $body = json_decode((string)$request->getBody(), true);

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->empowerHero($body),
            'empowering hero',
            'Failed to empower hero'
        );
    }

    /**
     * Guide a hero with influence
     */
    public function guideHero(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/influence/hero/guide endpoint called");

        $body = json_decode((string)$request->getBody(), true);

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->guideHero($body),
            'guiding hero',
            'Failed to guide hero'
        );
    }

    /**
     * Guide region research
     */
    public function guideRegionResearch(Request $request, Response $response): Response
    {
        Logger::debug("POST /api/influence/region/guide-research endpoint called");

        $body = json_decode((string)$request->getBody(), true);

        return $this->handleApiAction(
            $response,
            fn() => $this->influenceActions->guideRegionResearch($body),
            'guiding region research',
            'Failed to guide region research'
        );
    }
}

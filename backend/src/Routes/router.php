<?php

use App\Core\Router;
use App\Controllers\AuthController;
use App\Controllers\BettingController;
use App\Controllers\BuildingController;
use App\Controllers\EventController;
use App\Controllers\ExportController;
use App\Controllers\HeroController;
use App\Controllers\InfluenceController;
use App\Controllers\LandmarkController;
use App\Controllers\RegionController;
use App\Controllers\SettlementController;
use App\Controllers\StatisticsController;
use App\Controllers\StatusController;
use App\Middleware\JwtAuthMiddleware;
use App\Middleware\AdminAuthMiddleware;

return function (Router $router): void {
    $api = '/api';

    $router->get($api . '/site-status', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'OK']));
        return $response->withHeader('Content-Type', 'application/json');
    });

    $router->get($api . '/auth/login-info', [AuthController::class, 'getLoginInfo']);
    $router->get($api . '/auth/login-url', [AuthController::class, 'getLoginUrl']);
    $router->get($api . '/auth/register-url', [AuthController::class, 'getRegisterUrl']);
    $router->get($api . '/auth/callback', [AuthController::class, 'callback']);
    $router->post($api . '/auth/guest-session', [AuthController::class, 'createGuestSession']);

    $router->get($api . '/auth/session', [AuthController::class, 'getCurrentUser'], [JwtAuthMiddleware::class]);
    $router->get($api . '/auth/me', [AuthController::class, 'getCurrentUser'], [JwtAuthMiddleware::class]);
    $router->post($api . '/auth/logout', [AuthController::class, 'logout'], [JwtAuthMiddleware::class]);
    $router->put($api . '/auth/preferences', [AuthController::class, 'updatePreferences'], [JwtAuthMiddleware::class]);
    $router->post($api . '/auth/link-guest', [AuthController::class, 'linkGuestAccount'], [JwtAuthMiddleware::class]);

    $router->get($api . '/regions', [RegionController::class, 'getAllRegions'], [JwtAuthMiddleware::class]);
    $router->get($api . '/regions/{id}', [RegionController::class, 'getRegionById'], [JwtAuthMiddleware::class]);
    $router->get($api . '/regions/{id}/landmarks', [RegionController::class, 'getRegionLandmarks'], [JwtAuthMiddleware::class]);
    $router->post($api . '/regions', [RegionController::class, 'createRegion'], [JwtAuthMiddleware::class]);
    $router->post($api . '/regions/{id}/process', [RegionController::class, 'processRegionTick'], [JwtAuthMiddleware::class]);
    $router->get($api . '/heroes', [HeroController::class, 'getAllHeroes'], [JwtAuthMiddleware::class]);
    $router->get($api . '/heroes/{id}', [HeroController::class, 'getHeroById'], [JwtAuthMiddleware::class]);
    $router->get($api . '/settlements', [SettlementController::class, 'getAllSettlements'], [JwtAuthMiddleware::class]);
    $router->get($api . '/settlements/{id}', [SettlementController::class, 'getSettlementById'], [JwtAuthMiddleware::class]);
    $router->get($api . '/settlements/{id}/buildings', [SettlementController::class, 'getSettlementBuildings'], [JwtAuthMiddleware::class]);
    $router->get($api . '/events', [EventController::class, 'getAllEvents'], [JwtAuthMiddleware::class]);
    $router->get($api . '/events/{id}', [EventController::class, 'getEventById'], [JwtAuthMiddleware::class]);
    $router->get($api . '/buildings', [BuildingController::class, 'getAllBuildings'], [JwtAuthMiddleware::class]);
    $router->get($api . '/buildings/{id}', [BuildingController::class, 'getBuildingById'], [JwtAuthMiddleware::class]);
    $router->post($api . '/buildings', [BuildingController::class, 'createBuilding'], [JwtAuthMiddleware::class]);
    $router->put($api . '/buildings/{id}', [BuildingController::class, 'updateBuilding'], [JwtAuthMiddleware::class]);
    $router->delete($api . '/buildings/{id}', [BuildingController::class, 'deleteBuilding'], [JwtAuthMiddleware::class]);
    $router->get($api . '/landmarks', [LandmarkController::class, 'getAllLandmarks'], [JwtAuthMiddleware::class]);
    $router->get($api . '/landmarks/{id}', [LandmarkController::class, 'getLandmarkById'], [JwtAuthMiddleware::class]);
    $router->post($api . '/landmarks', [LandmarkController::class, 'createLandmark'], [JwtAuthMiddleware::class]);
    $router->put($api . '/landmarks/{id}', [LandmarkController::class, 'updateLandmark'], [JwtAuthMiddleware::class]);
    $router->delete($api . '/landmarks/{id}', [LandmarkController::class, 'deleteLandmark'], [JwtAuthMiddleware::class]);
    $router->post($api . '/landmarks/{id}/discover', [LandmarkController::class, 'discoverLandmark'], [JwtAuthMiddleware::class]);
    $router->post($api . '/bets', [BettingController::class, 'placeDivineBet'], [JwtAuthMiddleware::class]);
    $router->get($api . '/bets', [BettingController::class, 'getAllDivineBets'], [JwtAuthMiddleware::class]);
    $router->get($api . '/bets/{id}', [BettingController::class, 'getDivineBetById'], [JwtAuthMiddleware::class]);
    $router->get($api . '/speculation-events', [BettingController::class, 'getSpeculationEvents'], [JwtAuthMiddleware::class]);
    $router->get($api . '/betting-odds', [BettingController::class, 'getBettingOdds'], [JwtAuthMiddleware::class]);
    $router->get($api . '/bet-types', [BettingController::class, 'getBetTypes'], [JwtAuthMiddleware::class]);
    $router->post($api . '/combo-bets', [BettingController::class, 'createComboBet'], [JwtAuthMiddleware::class]);
    $router->post($api . '/combo-bets/preview', [BettingController::class, 'previewComboBet'], [JwtAuthMiddleware::class]);
    $router->get($api . '/export/types', [ExportController::class, 'getExportTypes'], [JwtAuthMiddleware::class]);
    $router->get($api . '/export/full', [ExportController::class, 'exportFull'], [JwtAuthMiddleware::class]);
    $router->get($api . '/export/{type}', [ExportController::class, 'exportByType'], [JwtAuthMiddleware::class]);
    $router->post($api . '/influence/divine/calculate-cost', [InfluenceController::class, 'calculateDivineInfluenceCost'], [JwtAuthMiddleware::class]);
    $router->post($api . '/influence/divine/apply', [InfluenceController::class, 'applyDivineInfluence'], [JwtAuthMiddleware::class]);
    $router->post($api . '/influence/hero/empower', [InfluenceController::class, 'empowerHero'], [JwtAuthMiddleware::class]);
    $router->post($api . '/influence/hero/guide', [InfluenceController::class, 'guideHero'], [JwtAuthMiddleware::class]);
    $router->post($api . '/influence/region/guide-research', [InfluenceController::class, 'guideRegionResearch'], [JwtAuthMiddleware::class]);
    $router->get($api . '/status', [StatusController::class, 'getGameStatus'], [JwtAuthMiddleware::class]);
    $router->get($api . '/statistics/summary', [StatisticsController::class, 'getSummary'], [JwtAuthMiddleware::class]);
    $router->get($api . '/statistics/heroes', [StatisticsController::class, 'getHeroStats'], [JwtAuthMiddleware::class]);
    $router->get($api . '/statistics/regions', [StatisticsController::class, 'getRegionStats'], [JwtAuthMiddleware::class]);
    $router->get($api . '/statistics/financials', [StatisticsController::class, 'getFinancialStats'], [JwtAuthMiddleware::class]);
    $router->post($api . '/admin/process-expired-bets', [BettingController::class, 'processExpiredBets'], [AdminAuthMiddleware::class, JwtAuthMiddleware::class]);
};

<?php

declare(strict_types=1);

namespace Tests\Hero;

use PHPUnit\Framework\TestCase;

final class ChampionWiringTest extends TestCase
{
    public function testChampionServicePersistsRosterAndRecordsEvents(): void
    {
        $service = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/ChampionService.php');

        self::assertStringContainsString('class ChampionService', $service);
        self::assertStringContainsString("'champions'", $service);
        self::assertStringContainsString("'roster'", $service);
        self::assertStringContainsString('designate', $service);
        self::assertStringContainsString('cultivate', $service);
        self::assertStringContainsString('advanceWorld', $service);
        self::assertStringContainsString('resolveChampionOutcome', $service);
        self::assertStringContainsString('bettingHooks', $service);
        self::assertStringContainsString('legacyHooks', $service);
        self::assertStringContainsString('Player::getSinglePlayer', $service);
        self::assertStringContainsString('GameEvent::create', $service);
        self::assertStringContainsString('champion_designated', $service);
        self::assertStringContainsString('champion_cultivated', $service);
        self::assertStringContainsString('champion_quest_completed', $service);
        self::assertStringContainsString('champion_rivalry_resolved', $service);
        self::assertStringContainsString('champion_rivalry_escalated', $service);
    }

    public function testHeroApiAndRoutesExposeChampionActions(): void
    {
        $actions = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/HeroActions.php');
        $controller = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Controllers/HeroController.php');
        $routes = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Routes/router.php');

        self::assertStringContainsString('ChampionService', $actions);
        self::assertStringContainsString("'championStatus' => \$this->championService->championStatusForHero", $actions);
        self::assertStringContainsString('fetchChampionStatus', $actions);
        self::assertStringContainsString('designateChampion', $controller);
        self::assertStringContainsString('cultivateChampion', $controller);
        self::assertStringContainsString("\$router->get(\$api . '/champions'", $routes);
        self::assertStringContainsString("\$router->post(\$api . '/heroes/{id}/champion'", $routes);
        self::assertStringContainsString("\$router->post(\$api . '/heroes/{id}/champion/cultivate'", $routes);
    }

    public function testChampionOutcomesFeedTicksBetsMythsAndEraLegacy(): void
    {
        $loop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');
        $betting = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/BettingActions.php');
        $mythology = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/MythologyService.php');
        $legacy = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/EraLegacyService.php');
        $status = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Actions/StatusActions.php');
        $export = (string)file_get_contents(dirname(__DIR__, 2) . '/src/Services/ExportService.php');

        self::assertStringContainsString("\$result['champions']", $loop);
        self::assertStringContainsString('advanceWorld($tickYear)', $loop);
        self::assertStringContainsString('recentChampionOutcomeForHero', $loop);
        self::assertStringContainsString('recentChampionRivalryForRegion', $loop);
        self::assertStringContainsString('ChampionService', $betting);
        self::assertStringContainsString("'bettingHooks'", $betting);
        self::assertStringContainsString('Completed champion quests can resolve hero milestone wagers.', $betting);
        self::assertStringContainsString('champion_quest_completed', $mythology);
        self::assertStringContainsString('champion_rivalry_resolved', $mythology);
        self::assertStringContainsString('champion_reincarnation_seed', $legacy);
        self::assertStringContainsString('explicit champion legacy', $legacy);
        self::assertStringContainsString("'champions' => \$this->championService->status()", $status);
        self::assertStringContainsString("'champions' => \$champions", $export);
        self::assertStringContainsString("'champions' => 'exportChampions'", $export);
    }
}

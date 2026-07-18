<?php

declare(strict_types=1);

namespace Tests\Civilization;

use PHPUnit\Framework\TestCase;

final class CivilizationForecastingBettingWiringTest extends TestCase
{
    public function testCivilizationAgendaForecastingIsWiredThroughBetting(): void
    {
        $betting = (string)file_get_contents(dirname(__DIR__, 2) . '/src/actions/BettingActions.php');
        $odds = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/OddsCalculationService.php');
        $loop = (string)file_get_contents(dirname(__DIR__, 2) . '/src/services/GameLoopService.php');
        $betModel = (string)file_get_contents(dirname(__DIR__, 2) . '/src/models/DivineBet.php');
        $migration = (string)file_get_contents(dirname(__DIR__, 2) . '/migrations/005_add_civilization_agenda_bet_type.php');
        $initSql = (string)file_get_contents(dirname(__DIR__, 2) . '/migrations/001_database_init_prod.sql');
        $betEntity = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/src/entities/divineBet.ts');
        $smoke = (string)file_get_contents(dirname(__DIR__, 3) . '/frontend/test/smoke.test.ts');

        self::assertStringContainsString('civilizationAgendaCandidates', $betting);
        self::assertStringContainsString('Civilization Agenda:', $betting);
        self::assertStringContainsString("'civilization_agenda'", $betting);
        self::assertStringContainsString('Dominant agenda', $betting);
        self::assertStringContainsString('Agenda pressure', $betting);
        self::assertStringContainsString('Agenda signals', $betting);
        self::assertStringContainsString('calculateCivilizationAgendaModifier', $odds);
        self::assertStringContainsString("case 'civilization_agenda'", $loop);
        self::assertStringContainsString('recentCivilizationAgendaForRegion', $loop);
        self::assertStringContainsString('civilizationAgendaOddsModifier', $loop);
        self::assertStringContainsString("'civilization_agenda'", $betModel);
        self::assertStringContainsString('civilization_agenda', $migration);
        self::assertStringContainsString('civilization_agenda', $initSql);
        self::assertStringContainsString("'civilization_agenda'", $betEntity);
        self::assertStringContainsString("'civilization_agenda'", $smoke);
    }
}

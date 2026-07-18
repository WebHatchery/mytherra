<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\GameState;
use App\Models\Player;
use App\Services\ArtifactService;
use App\Services\EraComparisonService;
use App\Services\EraLegacyService;
use App\Services\EraPressureService;
use App\Services\EraTransitionService;
use App\Services\GameConfigService;
use App\Services\GameLoopService;
use App\Services\MagicDiscoveryService;
use App\Services\MythologyService;
use App\Services\CivilizationBehaviorService;
use App\Services\ChampionService;
use App\Services\PantheonService;
use App\Services\TemporalOmenService;
use App\Services\WeatherInfluenceService;
use App\Utils\Logger;

class StatusActions
{
    private GameConfigService $configService;
    private GameLoopService $gameLoopService;
    private EraPressureService $eraPressureService;
    private EraLegacyService $eraLegacyService;
    private EraTransitionService $eraTransitionService;
    private EraComparisonService $eraComparisonService;
    private ArtifactService $artifactService;
    private TemporalOmenService $temporalOmenService;
    private WeatherInfluenceService $weatherInfluenceService;
    private MagicDiscoveryService $magicDiscoveryService;
    private MythologyService $mythologyService;
    private CivilizationBehaviorService $civilizationBehaviorService;
    private ChampionService $championService;
    private PantheonService $pantheonService;

    public function __construct(GameConfigService $configService)
    {
        $this->configService = $configService;
        $this->eraPressureService = new EraPressureService();
        $this->eraLegacyService = new EraLegacyService($this->eraPressureService);
        $this->eraComparisonService = new EraComparisonService($configService);
        $this->artifactService = new ArtifactService($configService);
        $this->temporalOmenService = new TemporalOmenService($configService);
        $this->weatherInfluenceService = new WeatherInfluenceService($configService);
        $this->magicDiscoveryService = new MagicDiscoveryService($configService);
        $this->mythologyService = new MythologyService($configService);
        $this->civilizationBehaviorService = new CivilizationBehaviorService($configService);
        $this->championService = new ChampionService(null, $configService);
        $this->pantheonService = new PantheonService($configService);
        $this->eraTransitionService = new EraTransitionService(
            $configService,
            $this->eraPressureService,
            $this->eraLegacyService,
            $this->eraComparisonService
        );
        $this->gameLoopService = new GameLoopService(
            $configService,
            $this->eraPressureService,
            $this->eraLegacyService,
            $this->eraTransitionService,
            $this->civilizationBehaviorService,
            $this->championService,
            $this->artifactService,
            $this->weatherInfluenceService,
            $this->temporalOmenService,
            $this->pantheonService,
            $this->magicDiscoveryService,
            $this->mythologyService
        );
    }

    /**
     * Fetch version information from GameConfig
     *
     * @return string API version
     * @throws \RuntimeException if config operation fails
     */
    public function fetchVersionConfig(): string
    {
        try {
            $version = $this->configService->getConfig('system', 'version', '2.0.0');

            if (!$version) {
                // Create version config if it doesn't exist
                $this->configService->setConfig(
                    'system',
                    'version',
                    '2.0.0',
                    'string',
                    'Current version of the game API'
                );
                $version = '2.0.0';
            }

            return $version;
        } catch (\Exception $error) {
            Logger::error('Error fetching version config', [
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch version configuration', 0, $error);
        }
    }

    /**
     * Fetch current game status
     *
     * @return array Game status data including current year and divine favor
     * @throws \RuntimeException if fetching game state fails
     */
    public function fetchGameStatus(): array
    {
        try {
            $gameState = GameState::getCurrent();
            $player = Player::getSinglePlayer();
            $currentYear = (int)$gameState->current_year;
            $eraPressure = $this->eraPressureService->evaluate($currentYear);
            $eraLegacy = $this->eraLegacyService->evaluate($currentYear, $eraPressure);

            return [
                'currentYear' => $gameState->current_year,
                'divineFavor' => $player->divine_favor,
                'eraPressure' => $eraPressure,
                'eraLegacy' => $eraLegacy,
                'eraTransition' => $this->eraTransitionService->status($currentYear, $eraPressure, $eraLegacy),
                'eraComparison' => $this->eraComparisonService->current($currentYear),
                'artifacts' => $this->artifactService->status(),
                'temporalOmens' => $this->temporalOmenService->status(),
                'weather' => $this->weatherInfluenceService->status(),
                'magicDiscovery' => $this->magicDiscoveryService->status(),
                'mythology' => $this->mythologyService->status(),
                'civilization' => $this->civilizationBehaviorService->status(),
                'champions' => $this->championService->status(),
                'pantheon' => $this->pantheonService->status(),
                'simulation' => $this->gameLoopService->getRuntimeStatus()
            ];
        } catch (\Exception $error) {
            Logger::error('Error fetching game status', [
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch game status', 0, $error);
        }
    }

    /**
     * Update the current game year
     *
     * @param int $newYear The new year value
     * @return array Updated game state data
     * @throws \RuntimeException if updating game state fails
     */
    public function updateGameYear(int $newYear): array
    {
        try {
            $gameState = GameState::getCurrent();
            $gameState->current_year = $newYear;
            $gameState->save();

            return [
                'currentYear' => $gameState->current_year,
                'divineFavor' => Player::getSinglePlayer()->divine_favor
            ];
        } catch (\Exception $error) {
            Logger::error('Error updating game year', [
                'newYear' => $newYear,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to update game year', 0, $error);
        }
    }

    /**
     * Update player's divine favor
     *
     * @param int $favorChange The amount to change divine favor by
     * @return array Updated divine favor data
     * @throws \RuntimeException if updating divine favor fails
     */
    public function updateDivineFavor(int $favorChange): array
    {
        try {
            $player = Player::getSinglePlayer();
            $player->divine_favor += $favorChange;
            $player->save();

            return [
                'divineFavor' => $player->divine_favor
            ];
        } catch (\Exception $error) {
            Logger::error('Error updating divine favor', [
                'favorChange' => $favorChange,
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to update divine favor', 0, $error);
        }
    }

    public function runGameTick(bool $advanceYear = true): array
    {
        return $this->gameLoopService->processTick($advanceYear);
    }

    public function runEraTransition(bool $force = false): array
    {
        return $this->eraTransitionService->transition($force);
    }

    public function startGameLoop(): array
    {
        return $this->gameLoopService->setEnabled(true);
    }

    public function stopGameLoop(): array
    {
        return $this->gameLoopService->setEnabled(false);
    }
}

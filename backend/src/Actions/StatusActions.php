<?php

namespace App\Actions;

use App\Models\GameState;
use App\Models\Player;
use App\Models\GameConfig;
use App\Services\GameConfigService;
use App\Utils\Logger;

class StatusActions
{
    private GameConfigService $configService;

    public function __construct(GameConfigService $configService)
    {
        $this->configService = $configService;
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

            return [
                'currentYear' => $gameState->current_year,
                'divineFavor' => $player->divine_favor
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
}

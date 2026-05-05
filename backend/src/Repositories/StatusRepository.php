<?php

namespace App\Repositories;

use App\Models\GameState;
use App\Models\Player;
use App\Utils\Logger;

class StatusRepository
{
    private DatabaseService $db;

    public function __construct(DatabaseService $db)
    {
        $this->db = $db;
    }

    public function getGameStatus(): array
    {
        try {
            // Get current game state
            $stmt = $this->db->prepare("
                SELECT current_year 
                FROM game_states 
                WHERE singleton_id = 'GAME_STATE' 
                LIMIT 1
            ");
            $stmt->execute();
            $gameState = $stmt->fetch(\PDO::FETCH_ASSOC);

    // Get player's divine favor
            $stmt = $this->db->prepare("
                SELECT divine_favor 
                FROM players 
                WHERE id = 'SINGLE_PLAYER' 
                LIMIT 1
            ");
            $stmt->execute();
            $player = $stmt->fetch(\PDO::FETCH_ASSOC);

            return [
                'currentYear' => $gameState ? (int)$gameState['current_year'] : 1,
                'divineFavor' => $player ? (int)$player['divine_favor'] : 0
            ];
        } catch (\Exception $error) {
            Logger::error('Error fetching game status', [
                'error' => $error->getMessage()
            ]);
            throw new \RuntimeException('Failed to fetch game status', 0, $error);
        }
    }
}

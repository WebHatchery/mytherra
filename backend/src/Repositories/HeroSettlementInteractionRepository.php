<?php

declare(strict_types=1);

namespace App\Repositories;

use Exception;
use App\Utils\Logger;
use App\Repositories\DatabaseService;

class HeroSettlementInteractionRepository extends BaseRepository
{
    protected string $table = 'hero_settlement_interactions';

    public function __construct(DatabaseService $db)
    {
        parent::__construct($db);
    }

    /**
     * Record a new interaction between a hero and settlement
     */
    public function recordInteraction($heroId, $settlementId, $interactionType, $gameYear)
    {
        $data = [
            'id' => bin2hex(random_bytes(8)),
            'hero_id' => $heroId,
            'settlement_id' => $settlementId,
            'interaction_type' => $interactionType,
            'game_year' => $gameYear,
            'created_at' => date('Y-m-d H:i:s')
        ];

        return $this->saveEntity($data, ['hero_id', 'settlement_id', 'interaction_type', 'game_year']);
    }

    /**
     * Get recent interactions for a hero
     */
    public function getHeroInteractions($heroId, $limit = 20)
    {
        $sql = "SELECT * FROM {$this->table} WHERE hero_id = :hero_id ORDER BY game_year DESC";
        $params = [':hero_id' => $heroId];

        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get all interactions for a settlement
     */
    public function getSettlementInteractions($settlementId, $limit = 20)
    {
        $sql = "SELECT * FROM {$this->table} WHERE settlement_id = :settlement_id ORDER BY game_year DESC";
        $params = [':settlement_id' => $settlementId];

        $stmt = $this->executeQuery($sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

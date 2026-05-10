<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Environment;
use PDO;
use Illuminate\Database\Capsule\Manager as Capsule;

class DatabaseService
{
    private static $instance = null;
    private $pdo = null;
    private $capsule = null;

    private function __construct()
    {
        $this->initDatabase();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }

    /**
     * Execute a query directly
     */
    public function query(string $query)
    {
        return $this->pdo->query($query);
    }

    public function prepare(string $query)
    {
        return $this->pdo->prepare($query);
    }

    /**
     * Creates the database if it doesn't exist
     */
    public function createDatabaseIfNotExists(): void
    {
        $host = Environment::required('DB_HOST');
        $port = Environment::required('DB_PORT');
        $dbName = Environment::required('DB_NAME');
        $user = Environment::required('DB_USER');
        $password = Environment::required('DB_PASSWORD');
        try {
        // Create initial connection without database
            $pdo = new PDO("mysql:host={$host};port={$port}", $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "Database '{$dbName}' created or already exists.\n";
        } catch (\PDOException $e) {
            echo "Error creating database: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Clear all tables from the database
     * @param bool $dropTables If true, drops tables. If false, just truncates them.     */
    public function clearDatabase(bool $dropTables = false): void
    {
        // First check if we can actually connect to the database
        try {
            $tables = $this->capsule->getConnection()->select('SHOW TABLES');
        } catch (\Exception $e) {
            // If we can't show tables, the database might not exist yet
            if (strpos($e->getMessage(), "Unknown database") !== false) {
                echo "Database does not exist yet, nothing to clear.\n";
                return;
            }
            throw $e;
        }

        $dbName = Environment::required('DB_NAME');
        $tableKey = "Tables_in_{$dbName}";

        if (empty($tables)) {
            echo "No tables found in database, nothing to clear.\n";
            return;
        }

    // Get existing table names
        $existingTables = array_map(function ($table) use ($tableKey) {
            return $table->$tableKey;
        }, $tables);

    // Define table truncation order - child tables first, then parent tables
            $truncateOrder = [
                // Game interactions and records (most dependent)
                'hero_settlement_interactions',  // Must be deleted before heroes and settlements
                'divine_bets',                  // Independent betting records
                'game_events',                  // Event records

                // Core game components (second level dependencies)
                'buildings',                    // Depends on settlements
                'resource_nodes',               // Depends on settlements
                'landmarks',                    // Depends on settlements

                // Core entities (primary tables)
                'heroes',                       // Referenced by hero_settlement_interactions
                'settlements',                  // Referenced by buildings, resource_nodes, landmarks
                'regions',                      // Referenced by settlements

                // Game state and configuration
                'game_configs',
                'game_states',
                'players',

                // Queue tables (if using Laravel queues)
                'failed_jobs',
                'jobs',

                // Reference/lookup tables (can be truncated last as they don't have foreign keys to them)
                'hero_roles',
                'hero_death_reasons',
                'hero_event_messages',
                'settlement_types',
                'settlement_statuses',
                'building_types',
                'building_statuses',
                'building_condition_levels',
                'building_special_properties',
                'landmark_types',
                'landmark_statuses',
                'resource_node_types',
                'resource_node_statuses',
                'bet_type_configs',
                'bet_confidence_configs',
                'bet_timeframe_modifiers',
                'bet_target_modifiers'
            ];            try {
                // Disable foreign key checks on both PDO and Eloquent connections
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                $this->capsule->getConnection()->statement('SET FOREIGN_KEY_CHECKS = 0');

                try {
                    // Process tables in our defined order first - child tables before parent tables
                    foreach ($truncateOrder as $tableName) {
                        if (in_array($tableName, $existingTables)) {
                            if ($dropTables) {
                                echo "Dropping table {$tableName}...\n";

    // Use both connections to ensure the operation succeeds
                                $this->pdo->exec("DROP TABLE IF EXISTS `{$tableName}`");
                                $this->capsule->getConnection()->statement("DROP TABLE IF EXISTS `{$tableName}`");
                            } else {
                                echo "Clearing table {$tableName}...\n";

    // Use both connections to ensure the operation succeeds
                                $this->pdo->exec("TRUNCATE TABLE `{$tableName}`");
                                $this->capsule->getConnection()->statement("TRUNCATE TABLE `{$tableName}`");
                            }
                        }
                    }

    // Process any remaining tables that weren't in our order
                    foreach ($existingTables as $tableName) {
                        if (!in_array($tableName, $truncateOrder)) {
                            if ($dropTables) {
                                echo "Dropping unlisted table {$tableName}...\n";
                                $this->pdo->exec("DROP TABLE IF EXISTS `{$tableName}`");
                                $this->capsule->getConnection()->statement("DROP TABLE IF EXISTS `{$tableName}`");
                            } else {
                                echo "Clearing unlisted table {$tableName}...\n";
                                $this->pdo->exec("TRUNCATE TABLE `{$tableName}`");
                                $this->capsule->getConnection()->statement("TRUNCATE TABLE `{$tableName}`");
                            }
                        }
                    }

                    if ($dropTables) {
                        echo "All tables dropped successfully.\n";
                    } else {
                        echo "All tables cleared successfully.\n";
                    }
                } finally {
                    // Always re-enable foreign key checks on both connections
                    $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                    $this->capsule->getConnection()->statement('SET FOREIGN_KEY_CHECKS = 1');
                }
            } catch (\Exception $e) {
                echo "Error clearing database: " . $e->getMessage() . "\n";
                throw $e;
            }
    }

    /**
     * Clears a single table by either truncating or dropping it
     */
    private function clearTable(string $tableName, bool $dropTables): void
    {
        try {
            if ($dropTables) {
                $this->pdo->exec("DROP TABLE IF EXISTS `$tableName`");
                echo "Dropped table: $tableName\n";
            } else {
                $this->pdo->exec("TRUNCATE TABLE `$tableName`");
                echo "Truncated table: $tableName\n";
            }
        } catch (\PDOException $e) {
            // If table doesn't exist, just continue
            if ($e->getCode() !== '42S02') {
                throw $e;
            }
        }
    }

    private function initDatabase(): void
    {
        // Get required database configuration
        $host = Environment::required('DB_HOST');
        $port = Environment::required('DB_PORT');
        $dbname = Environment::required('DB_NAME');
        $user = Environment::required('DB_USER');
        $password = Environment::required('DB_PASSWORD');

    // Set up PDO connection
        $this->pdo = new PDO(
            "mysql:host={$host};port={$port};dbname={$dbname}",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

    // Set up Eloquent
        $this->capsule = new Capsule();
        $this->capsule->addConnection([
            'driver' => 'mysql',
            'host' => $host,
            'port' => $port,
            'database' => $dbname,
            'username' => $user,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ],
        ]);

    // Make Capsule instance available globally
        $this->capsule->setAsGlobal();
        $this->capsule->bootEloquent();
    }
}

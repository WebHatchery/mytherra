<?php

namespace App\Scripts;

use Dotenv\Dotenv;

/**
 * Handles environment setup and validation
 */
class EnvironmentManager
{
    /**
     * Load and validate environment variables
     */
    public function loadEnvironment(): void
    {
        echo "Loading environment variables...\n";
        
        try {
            $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
            $dotenv->load();
            
            $this->validateEnvironment();
            
            echo "✅ Environment variables loaded\n";
        } catch (\Exception $e) {
            echo "Error loading environment: " . $e->getMessage() . "\n";
            throw $e;
        }
    }

    /**
     * Validate required environment variables are present
     */
    private function validateEnvironment(): void
    {
        $required = [
            'DB_HOST',
            'DB_NAME',
            'DB_USER',
            'DB_PASSWORD'
        ];

        $missing = [];
        foreach ($required as $var) {
            if (empty($_ENV[$var])) {
                $missing[] = $var;
            }
        }

        if (!empty($missing)) {
            throw new \Exception("Missing required environment variables: " . implode(', ', $missing));
        }
    }
}

<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\InitData\ResourceNodeData;
use App\Repositories\DatabaseService;
use App\Scripts\EnvironmentManager;

try {
    (new EnvironmentManager())->loadEnvironment();
    $db = DatabaseService::getInstance();
    $stmt = $db->prepare("
        INSERT INTO resource_nodes
            (id, region_id, settlement_id, type, name, output, status, created_at, updated_at)
        VALUES
            (:id, :region_id, :settlement_id, :type, :name, :output, :status, NOW(), NOW())
        ON DUPLICATE KEY UPDATE
            region_id = VALUES(region_id),
            settlement_id = VALUES(settlement_id),
            type = VALUES(type),
            name = VALUES(name),
            output = VALUES(output),
            status = VALUES(status),
            updated_at = NOW()
    ");

    $count = 0;
    foreach (ResourceNodeData::getData() as $resourceData) {
        $stmt->execute($resourceData);
        $count++;
    }

    echo json_encode([
        'success' => true,
        'seeded' => $count,
    ], JSON_PRETTY_PRINT) . PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'message' => $error->getMessage(),
    ], JSON_PRETTY_PRINT) . PHP_EOL);
    exit(1);
}

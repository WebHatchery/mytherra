<?php

declare(strict_types=1);

$centralAutoload = __DIR__ . '/../../../vendor/autoload.php';
if (!file_exists($centralAutoload)) {
    throw new \RuntimeException('Central vendor autoload not found at ' . $centralAutoload);
}
$loader = require $centralAutoload;
//$loader->addPsr4('App\\', __DIR__ . '/../src/', true); // Disabled to prevent conflicts

// Local autoloader MUST be registered AFTER composer to ensure it prepends successfully
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/../src/';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $parts = explode('\\', $relative);
    // Lowercase all parts except the class name itself
    for ($i = 0; $i < count($parts) - 1; $i++) {
        $parts[$i] = strtolower($parts[$i]);
    }
    $file = $baseDir . implode('/', $parts) . '.php';
    if (file_exists($file)) {
        require $file;
    }
}, true, true);

use Dotenv\Dotenv;
use App\Core\Environment;
use App\Repositories\DatabaseService;
use App\Utils\ContainerConfig;
use App\Core\Router;

// Load environment variables first
$dotenvPath = __DIR__ . '/..';
if (!file_exists($dotenvPath . '/.env')) {
    throw new \RuntimeException('Missing .env at ' . $dotenvPath . '/.env');
}
$dotenv = Dotenv::createImmutable($dotenvPath);
$dotenv->load();

// Add required environment variables
$required_env_vars = [
    'DB_HOST',
    'DB_PORT',
    'DB_CONNECTION',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
    'JWT_SECRET',
    'CORS_ALLOWED_ORIGINS',
    'WEB_HATCHERY_LOGIN_URL',
    'WEB_HATCHERY_REGISTER_URL',
];
foreach ($required_env_vars as $var) {
    Environment::required($var);
}

// Create DI Container
$container = ContainerConfig::createContainer();

// Initialize database service after environment variables are loaded
$db = DatabaseService::getInstance();

// Router (Custom)
$router = new Router($container);

// Set base path for subdirectory deployment
$configuredBasePath = Environment::optional('APP_BASE_PATH');
if ($configuredBasePath !== null) {
    $router->setBasePath(rtrim($configuredBasePath, '/'));
} else {
    // Auto-detect base path
    $requestPath = $_SERVER['REQUEST_URI'] ?? '';
    $requestPath = parse_url($requestPath, PHP_URL_PATH) ?? '';
    
    // Check key segments
    $segments = ['/mytherra'];
    foreach ($segments as $segment) {
        if (strpos($requestPath, $segment) === 0) {
            $router->setBasePath($segment);
            break;
        }
    }
}

// Load routes
(require __DIR__ . '/../src/routes/router.php')($router);

// Run router
$router->handle();

<?php

declare(strict_types=1);

namespace App\Core;

use Psr\Container\ContainerInterface;
use App\Core\Request;
use App\Core\Response;
use ReflectionMethod;
use Throwable;

final class Router
{
    private array $routes = [];
    private string $basePath = '';
    private array $globalMiddleware = [];

    public function __construct(
        private readonly ?ContainerInterface $container = null
    ) {
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function addMiddleware(callable|string $middleware): void
    {
        $this->globalMiddleware[] = $middleware;
    }

    public function post(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('POST', $path, $handler, $middleware);
    }

    public function get(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('GET', $path, $handler, $middleware);
    }

    public function put(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('PUT', $path, $handler, $middleware);
    }

    public function delete(string $path, array|callable $handler, array $middleware = []): void
    {
        $this->addRoute('DELETE', $path, $handler, $middleware);
    }

    private function addRoute(string $method, string $path, array|callable $handler, array $middleware): void
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^" . $pattern . "$#";

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler,
            'middleware' => $middleware
        ];
    }

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = explode('?', $uri)[0];

        if (!empty($this->basePath) && strpos($path, $this->basePath) === 0) {
            $path = substr($path, strlen($this->basePath));
        }

        if ($path === '') {
            $path = '/';
        }

        foreach ($this->routes as $route) {
            if ($route['method'] === $method && preg_match($route['pattern'], $path, $matches)) {
                $routeParams = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

                $request = Request::createFromGlobals();
                $response = new Response();

                $middlewares = array_merge($this->globalMiddleware, $route['middleware']);

    // For native PHP simple router, we handle middleware as a simple chain or loop.
                // Since we dropped PSR-15, we'll assume middlewares are callables: mw($request, $response, $next)
                // OR simply mw($request, $response, $params) and return response.

                // Let's adopt a simple approach: if middleware returns FALSE/null, we stop.
                // If it returns Response, we use it.

                foreach ($middlewares as $mw) {
                    $mwInstance = $mw;
                    if (is_string($mw)) {
                        if ($this->container && $this->container->has($mw)) {
                            $mwInstance = $this->container->get($mw);
                        } else {
                            $mwInstance = new $mw();
                        }
                    }

    // Middleware signature checks
                    if (method_exists($mwInstance, 'process')) {
                        // Adapt "process" style to our simple style if needed,
                        // but better to fix middleware to be simple: run($req, $res)
                        // For now we assume our middlewares are updated to be simple callables.

                        // Wait, JwtAuthMiddleware is a class. We'll call a method on it or invoke.
                        // Let's assume it's callable or has handle/process.
                        // We will refactor JwtAuthMiddleware to have a `run` or `__invoke` method.
                         $result = $mwInstance($request, $response, $routeParams);
                    } elseif (is_callable($mwInstance)) {
                        $result = $mwInstance($request, $response, $routeParams);
                    } else {
                         // Fallback or error
                         continue;
                    }

                    if ($result instanceof Request) {
                        $request = $result;
                        continue;
                    }

                    if ($result instanceof Response) {
                        $this->emit($this->withCors($result));
                        return;
                    }

                    if ($result === false) {
// Legacy BSF style shortcut
                         $this->emit($this->withCors($response->withStatus(401)));
                        return;
                    }
                }
                try {
                    $response = $this->invokeHandler($route['handler'], $request, $response, $routeParams);
                } catch (Throwable $e) {
                    $response = $this->writeJson((new Response())->withStatus(500), [
                        'success' => false,
                        'message' => 'Internal server error: ' . $e->getMessage()
                    ]);
                }

                $this->emit($this->withCors($response));
                return;
            }
        }

        $response = $this->writeJson((new Response())->withStatus(404), [
            'success' => false,
            'message' => 'Route not found: ' . $path
        ]);

        $this->emit($this->withCors($response));
    }

    public function invokeHandler(array|callable $handler, Request $request, Response $response, array $routeParams): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request, $response, $routeParams);
            return $result instanceof Response ? $result : $response;
        }

        $controllerClass = $handler[0];
        $methodName = $handler[1];

        $controller = $this->container ? $this->container->get($controllerClass) : new $controllerClass();

        $method = new ReflectionMethod($controller, $methodName);
        $result = $controller->$methodName($request, $response, $routeParams);

        return $result instanceof Response ? $result : $response;
    }

    private function writeJson(Response $response, array $payload): Response
    {
        $response->getBody()->write(json_encode($payload));
        return $response->withHeader('Content-Type', 'application/json');
    }

    private function withCors(Response $response): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    }

    private function emit(Response $response): void
    {
        if (!headers_sent()) {
            http_response_code($response->getStatusCode());
            foreach ($response->getHeaders() as $name => $values) {
                // $values is simple string in our class
                header(sprintf('%s: %s', $name, $values), false);
            }
        }

        echo (string)$response->getBody();
    }
}

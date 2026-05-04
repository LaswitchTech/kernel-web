<?php

namespace App\Core;

class Router
{
    private array $routes = [];
    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a GET route.
     *
     * @param int $priority Higher values win over lower values.
     *                      Default: 0 (no preference). Use higher values
     *                      for kernel/core routes that must be overridable.
     */
    public function get(string $path, string $handler, array $middleware = [], int $priority = 0): void
    {
        $this->add('GET', $path, $handler, $middleware, $priority);
    }

    /**
     * Register a POST route.
     */
    public function post(string $path, string $handler, array $middleware = [], int $priority = 0): void
    {
        $this->add('POST', $path, $handler, $middleware, $priority);
    }

    /**
     * Register a PUT route.
     */
    public function put(string $path, string $handler, array $middleware = [], int $priority = 0): void
    {
        $this->add('PUT', $path, $handler, $middleware, $priority);
    }

    /**
     * Register a DELETE route.
     */
    public function delete(string $path, string $handler, array $middleware = [], int $priority = 0): void
    {
        $this->add('DELETE', $path, $handler, $middleware, $priority);
    }

    /**
     * Internal route registration with priority support.
     *
     * @param int $priority Higher values win over lower values.
     */
    private function add(string $method, string $path, string $handler, array $middleware = [], int $priority = 0): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'path'       => $path,
            'handler'    => $handler,
            'middleware' => $middleware,
            'priority'   => $priority,
        ];
    }

    /**
     * Register a route definition array declared by a plugin manifest.
     *
     * Format: ['METHOD', '/path', 'Handler@method', ['middleware']]
     *
     * @deprecated Use registerRoute() with explicit priority instead.
     */
    public function registerPluginRoutes(array $routeDef): void
    {
        [$method, $path, $handler, $middleware] = array_pad($routeDef, 4, []);
        $this->add(strtoupper($method), $path, $handler, $middleware);
    }

    /**
     * Register a route with explicit priority.
     *
     * Priority levels (convention):
     *   0 — neutral (no preference)
     *   1 — plugin / extension (overrides kernel)
     *   10 — kernel core (overridable by plugins)
     *
     * Higher values win. Ties broken by insertion order.
     */
    public function registerRoute(
        string $method,
        string $path,
        string $handler,
        array $middleware = [],
        int $priority = 0
    ): void {
        $this->add($method, $path, $handler, $middleware, $priority);
    }

    /**
     * Dispatch the current request.
     *
     * Routes are sorted by priority (descending) before matching,
     * so higher-priority routes always win over lower-priority ones.
     * Ties are broken by insertion order (first registered wins).
     *
     * Handler format: "Controller@method"
     */
    public function dispatch(string $method, string $uri): void
    {
        $uri = strtok($uri, '?'); // strip query string

        // Sort routes by priority (highest first), preserving insertion order for ties.
        $sorted = $this->routes;
        usort($sorted, function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        foreach ($sorted as $route) {
            [$pattern, $paramNames] = $this->compile($route['path']);

            if ($route['method'] !== strtoupper($method)) {
                continue;
            }

            if (!preg_match($pattern, $uri, $matches)) {
                continue;
            }

            // Extract named params
            $params = [];
            foreach ($paramNames as $name) {
                $params[$name] = $matches[$name] ?? null;
            }

            $this->call($route['handler'], $route['middleware'], $params);
            return;
        }

        throw new \OutOfBoundsException("No route matched: {$method} {$uri}");
    }

    /**
     * Convert a route path like /users/{id} into a regex.
     * Returns [pattern, paramNames].
     */
    private function compile(string $path): array
    {
        $paramNames = [];

        $pattern = preg_replace_callback('/\{(\w+)\}/', function ($m) use (&$paramNames) {
            $paramNames[] = $m[1];
            return '(?P<' . $m[1] . '>[^/]+)';
        }, $path);

        $pattern = '#^' . $pattern . '$#';

        return [$pattern, $paramNames];
    }

    /**
     * Redirect to a URL.
     *
     * Sends a 302 redirect and exits. Should only be called from controllers.
     */
    public function redirect(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }

    private function call(string $handler, array $middleware, array $params): void
    {
        $action = function (array $p) use ($handler): void {
            [$className, $method] = explode('@', $handler);

            // Qualified path: 'App\Controllers\HomeController@index'
            //   → 'App\Controllers\HomeController'
            // Bare name:      'HomeController@index'
            //   → 'App\Controllers\HomeController'  (backward-compatible default)
            $fqcn = str_contains($className, '\\')
                ? 'App\\' . $className
                : 'App\\Controllers\\' . $className;

            if (!class_exists($fqcn)) {
                throw new \RuntimeException("Controller not found: {$fqcn}");
            }

            $controller = new $fqcn($this->container);

            if (!method_exists($controller, $method)) {
                throw new \RuntimeException("Method not found: {$fqcn}::{$method}");
            }

            $controller->$method($p);
        };

        if (empty($middleware)) {
            $action($params);
            return;
        }

        $this->runStack($middleware, $params, $action);
    }

    /**
     * Build and execute a middleware chain.
     *
     * Middleware strings may carry an argument after a colon:
     *   'RequirePermission:users.view'  →  new RequirePermission($container, 'users.view')
     *
     * The chain is built right-to-left so the first middleware in the array
     * is the outermost (runs first).
     */
    private function runStack(array $middleware, array $params, callable $action): void
    {
        $chain = $action;

        foreach (array_reverse($middleware) as $mw) {
            $parts    = explode(':', $mw, 2);
            $fqcn     = 'App\\Middleware\\' . $parts[0];
            $arg      = $parts[1] ?? null;

            if (!class_exists($fqcn)) {
                throw new \RuntimeException("Middleware not found: {$fqcn}");
            }

            $instance = new $fqcn($this->container, $arg);
            $inner    = $chain;

            $chain = function (array $p) use ($instance, $inner): void {
                $instance->handle($p, $inner);
            };
        }

        $chain($params);
    }
}

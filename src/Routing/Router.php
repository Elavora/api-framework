<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Routing;

use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\HttpMethod;

/**
 * Registro e busca de rotas HTTP.
 */
final class Router
{
    /** @var array<string, array<string, Route>> */
    private array $routes = [];

    /**
     * Registra uma rota.
     *
     * @param string|HttpMethod $method Metodo HTTP aceito pela rota.
     * @param string $path Path da rota, com ou sem barra inicial.
     * @param mixed $handler Callable ou par [Controller::class, 'metodo'].
     */
    public function add(string|HttpMethod $method, string $path, mixed $handler): void
    {
        $method = HttpMethod::fromValue($method)->value;
        $path = self::normalizePath($path);
        $this->routes[$path][$method] = new Route(method: $method, path: $path, handler: $handler);
    }

    /**
     * Busca a rota exata para method/path da request.
     */
    public function match(Request $request): ?Route
    {
        $routes = $this->routes[$request->path()] ?? [];
        $route = $routes[$request->method()] ?? null;
        if ($route !== null || $request->httpMethod() !== HttpMethod::Head) {
            return $route;
        }

        return $routes[HttpMethod::Get->value] ?? null;
    }

    /**
     * Retorna os metodos permitidos para um path.
     *
     * @return list<string>
     */
    public function allowedMethods(string $path): array
    {
        $methods = array_keys($this->routes[self::normalizePath($path)] ?? []);
        if (!in_array(HttpMethod::Get->value, $methods, true)
            || in_array(HttpMethod::Head->value, $methods, true)
        ) {
            return $methods;
        }

        $getPosition = array_search(HttpMethod::Get->value, $methods, true);
        array_splice($methods, $getPosition + 1, 0, [HttpMethod::Head->value]);

        return $methods;
    }

    /**
     * Retorna a primeira rota registrada para um path, independente do metodo.
     */
    public function firstRouteForPath(string $path): ?Route
    {
        $routes = $this->routes[self::normalizePath($path)] ?? [];

        return $routes === [] ? null : reset($routes);
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return $path !== '/' ? rtrim($path, '/') : $path;
    }
}



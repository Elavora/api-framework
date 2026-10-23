<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Routing;

use Closure;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\HttpMethod;
use InvalidArgumentException;
use ReflectionMethod;

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
     * @param mixed $handler Closure, objeto com __invoke, [Classe::class, 'metodo'] ou [$objeto, 'metodo'].
     * @throws InvalidArgumentException Quando o handler nao e aceito; a rota nao e registrada.
     */
    public function add(string|HttpMethod $method, string $path, mixed $handler): void
    {
        $method = HttpMethod::fromValue($method)->value;
        $path = self::normalizePath($path);
        $problem = self::handlerProblem($handler);
        if ($problem !== null) {
            throw new InvalidArgumentException("Handler invalido para {$method} {$path}: {$problem}.");
        }

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

    /**
     * Retorna o motivo da recusa do handler, ou null quando ele e aceito.
     */
    private static function handlerProblem(mixed $handler): ?string
    {
        if ($handler instanceof Closure) {
            return null;
        }

        if (is_object($handler)) {
            return method_exists($handler, '__invoke') ? null : 'o objeto nao tem __invoke';
        }

        if (!is_array($handler)) {
            return 'use uma Closure, um objeto com __invoke ou um par [Classe::class, \'metodo\']';
        }

        return self::pairProblem($handler);
    }

    /**
     * @param array<mixed> $handler
     */
    private static function pairProblem(array $handler): ?string
    {
        if (count($handler) !== 2 || !array_key_exists(0, $handler) || !array_key_exists(1, $handler)) {
            return 'o par precisa ter exatamente as chaves 0 e 1';
        }

        [$target, $methodName] = $handler;
        if (!is_string($methodName)) {
            return 'o nome do metodo precisa ser texto';
        }

        if (is_object($target)) {
            return self::objectMethodProblem($target, $methodName);
        }

        if (!is_string($target)) {
            return 'o primeiro item precisa ser o nome de uma classe ou um objeto';
        }

        return self::classMethodProblem($target, $methodName);
    }

    private static function objectMethodProblem(object $target, string $methodName): ?string
    {
        $label = $target::class . '::' . $methodName;
        if (!method_exists($target, $methodName)) {
            return "o metodo {$label} nao existe";
        }

        return (new ReflectionMethod($target, $methodName))->isPublic() ? null : "o metodo {$label} nao e publico";
    }

    private static function classMethodProblem(string $className, string $methodName): ?string
    {
        if (!class_exists($className)) {
            return "a classe {$className} nao existe";
        }

        $label = $className . '::' . $methodName;
        if (!method_exists($className, $methodName)) {
            return "o metodo {$label} nao existe";
        }

        $method = new ReflectionMethod($className, $methodName);

        return self::methodRuleProblem($method, $methodName, $label);
    }

    private static function methodRuleProblem(ReflectionMethod $method, string $methodName, string $label): ?string
    {
        return match (true) {
            $method->getName() !== $methodName => "o metodo {$label} nao existe com esse nome exato",
            !$method->isPublic() => "o metodo {$label} nao e publico",
            $method->isStatic() => "o metodo {$label} e estatico",
            $method->isConstructor(), $method->isDestructor() => "o metodo {$label} e construtor ou destrutor",
            str_starts_with($methodName, '__') => "o metodo {$label} comeca com __",
            default => null,
        };
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        return $path !== '/' ? rtrim($path, '/') : $path;
    }
}

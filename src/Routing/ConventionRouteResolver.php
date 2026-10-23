<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Routing;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Logging\Logger;
use ReflectionClass;
use ReflectionMethod;

/**
 * Resolve actions HTTP pela convencao /controller/action.
 *
 * Rotas registradas explicitamente continuam com prioridade. Este resolvedor
 * atua apenas como fallback e limita a busca ao namespace configurado. Toda
 * recusa devolve null; o motivo vai apenas para o log, quando ha Logger no container.
 */
final class ConventionRouteResolver
{
    private const SEGMENT_PATTERN = '/^[a-z][A-Za-z0-9]{0,63}$/';
    private const LOG_MESSAGE = 'Convention route not resolved';
    private const LOG_PATH_LIMIT = 200;

    /**
     * @param string $controllerNamespace Namespace base dos controllers da aplicacao.
     * @param string $controllerSuffix Sufixo usado pelas classes de controller.
     * @param string $defaultAction Action usada quando a URL informa apenas o controller.
     * @param Container|null $container Container usado para criar controllers e achar o Logger.
     */
    public function __construct(
        private readonly string $controllerNamespace = 'App\\Http\\Controller',
        private readonly string $controllerSuffix = 'Controller',
        private readonly string $defaultAction = 'index',
        private readonly ?Container $container = null,
    ) {}

    /**
     * Busca um handler seguindo a convencao /controller/action.
     *
     * Nao cria a classe nem chama a action.
     *
     * @return array{class-string, string}|null Par [Controller::class, 'action'] ou null quando alguma regra falha.
     */
    public function resolve(Request $request): ?array
    {
        $path = $request->path();
        $segments = $this->segments($path);
        if ($segments === null) {
            return $this->refuse($path, 'invalid_path');
        }

        [$controller, $action] = $segments;
        $class = $this->controllerReflection($this->controllerClass($controller));
        if ($class === null) {
            return $this->refuse($path, 'class_not_found');
        }

        if (!$this->canCreate($class)) {
            return $this->refuse($path, 'class_not_instantiable');
        }

        $method = $this->actionReflection($class, $action);
        if ($method === null) {
            return $this->refuse($path, 'action_not_found');
        }

        if (!$this->isAllowedAction($class, $method)) {
            return $this->refuse($path, 'action_not_allowed');
        }

        return [$class->getName(), $method->getName()];
    }

    /**
     * @return array{string, string}|null
     */
    private function segments(string $path): ?array
    {
        $segments = explode('/', substr($path, 1));
        if (count($segments) > 2) {
            return null;
        }

        $controller = $segments[0];
        $action = $segments[1] ?? $this->defaultAction;
        if (!$this->isValidSegment($controller) || !$this->isValidSegment($action)) {
            return null;
        }

        return [$controller, $action];
    }

    private function isValidSegment(string $segment): bool
    {
        return preg_match(self::SEGMENT_PATTERN, $segment) === 1;
    }

    private function controllerClass(string $controller): string
    {
        return rtrim($this->controllerNamespace, '\\')
            . '\\'
            . ucfirst($controller)
            . $this->controllerSuffix;
    }

    /**
     * Procura so o nome montado e exige o mesmo caso no nome declarado.
     *
     * @return ReflectionClass<object>|null
     */
    private function controllerReflection(string $className): ?ReflectionClass
    {
        $exists = class_exists($className)
            || interface_exists($className, false)
            || trait_exists($className, false);
        if (!$exists) {
            return null;
        }

        $class = new ReflectionClass($className);

        return $class->getName() === $className ? $class : null;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function canCreate(ReflectionClass $class): bool
    {
        if (!$class->isInstantiable()) {
            return false;
        }

        if ($this->container?->has($class->getName()) === true) {
            return true;
        }

        $constructor = $class->getConstructor();

        return $constructor === null || $constructor->getNumberOfRequiredParameters() === 0;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function actionReflection(ReflectionClass $class, string $action): ?ReflectionMethod
    {
        if (!$class->hasMethod($action)) {
            return null;
        }

        $method = $class->getMethod($action);

        return $method->getName() === $action ? $method : null;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function isAllowedAction(ReflectionClass $class, ReflectionMethod $method): bool
    {
        return $method->isPublic()
            && !$method->isStatic()
            && !$method->isConstructor()
            && !$method->isDestructor()
            && !str_starts_with($method->getName(), '__')
            && $method->getDeclaringClass()->getName() === $class->getName()
            && $this->isWrittenInClassBody($class, $method)
            && $method->getAttributes(Action::class) !== [];
    }

    /**
     * Metodos vindos de trait informam o arquivo e as linhas do trait.
     *
     * @param ReflectionClass<object> $class
     */
    private function isWrittenInClassBody(ReflectionClass $class, ReflectionMethod $method): bool
    {
        $classFile = $class->getFileName();
        if ($classFile === false || $method->getFileName() !== $classFile) {
            return false;
        }

        return $method->getStartLine() >= $class->getStartLine()
            && $method->getEndLine() <= $class->getEndLine();
    }

    private function refuse(string $path, string $reason): null
    {
        $logger = $this->logger();
        if ($logger !== null) {
            $logger->debug(self::LOG_MESSAGE, [
                'path' => substr($path, 0, self::LOG_PATH_LIMIT),
                'reason' => $reason,
            ]);
        }

        return null;
    }

    /**
     * Procura o Logger no momento da request, para valer um registro feito depois do boot.
     */
    private function logger(): ?Logger
    {
        if ($this->container === null || !$this->container->has(Logger::class)) {
            return null;
        }

        $logger = $this->container->get(Logger::class);

        return $logger instanceof Logger ? $logger : null;
    }
}

<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Routing;

use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\AfterExceptionAttribute;
use Elavora\Api\Framework\Contracts\AfterResponseAttribute;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Contracts\HttpAttribute;
use Elavora\Api\Framework\Contracts\RequestValidatorAttribute;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * Resolve e executa handlers de rota.
 *
 * Tambem aplica validators declarados por attributes antes de chamar a action.
 */
final class ControllerResolver
{
    /**
     * @param Container $container Container usado para resolver controllers registrados.
     */
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * Executa um handler de rota.
     *
     * @param mixed $handler Callable ou par [Controller::class, 'metodo'].
     * @return mixed Resultado bruto da action/callable.
     */
    public function invoke(mixed $handler, Request $request): mixed
    {
        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $controller = $this->container->has($handler[0])
                ? $this->container->get($handler[0])
                : new $handler[0]();

            $method = (string) $handler[1];
            $attributes = $this->attributeInstances($controller, $method);
            $validationResponse = $this->validateAttributes($attributes, $request);
            if ($validationResponse instanceof Response) {
                return $validationResponse;
            }

            $executedAttributes = [];

            try {
                foreach ($attributes as $attribute) {
                    if (!$attribute instanceof BeforeRequestAttribute) {
                        continue;
                    }

                    $response = $attribute->before($request, $this->container);
                    $executedAttributes[] = $attribute;
                    if ($response instanceof Response) {
                        return $this->runAfterAttributes($executedAttributes, $request, $response);
                    }
                }

                $result = $controller->{$method}($request);

                return $this->runAfterAttributes($attributes, $request, $result);
            } catch (Throwable $exception) {
                $this->runAfterExceptionAttributes($executedAttributes, $request, $exception);

                throw $exception;
            }
        }

        if (is_callable($handler)) {
            return $handler($request, $this->container);
        }

        throw new RuntimeException('Handler de rota invalido.');
    }

    /**
     * Retorna metadados dos attributes HTTP de uma action.
     *
     * @param mixed $handler Par [Controller::class, 'metodo'].
     * @return array<string, mixed>
     */
    public function options(mixed $handler): array
    {
        if (!is_array($handler) || !isset($handler[0], $handler[1]) || !is_string($handler[0])) {
            return [];
        }

        $reflection = new ReflectionMethod($handler[0], (string) $handler[1]);
        $options = [];

        foreach ($reflection->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();
            if (!$instance instanceof HttpAttribute) {
                continue;
            }

            $options = array_merge($options, $instance->options());
        }

        return $options;
    }

    /**
     * @param list<object> $attributes
     */
    private function validateAttributes(array $attributes, Request $request): ?Response
    {
        foreach ($attributes as $attribute) {
            if (!$attribute instanceof RequestValidatorAttribute) {
                continue;
            }

            $response = $attribute->validate($request);
            if ($response instanceof Response) {
                return $response;
            }
        }

        return null;
    }

    /**
     * @return list<object>
     */
    private function attributeInstances(object $controller, string $method): array
    {
        $reflection = new ReflectionMethod($controller, $method);

        return array_map(
            static fn ($attribute): object => $attribute->newInstance(),
            $reflection->getAttributes()
        );
    }

    /**
     * @param list<object> $attributes
     */
    private function runAfterAttributes(array $attributes, Request $request, mixed $result): mixed
    {
        $response = null;

        foreach (array_reverse($attributes) as $attribute) {
            if (!$attribute instanceof AfterResponseAttribute) {
                continue;
            }

            $response ??= $this->responseFromResult($result);
            $replacement = $attribute->after($request, $response, $this->container);
            if ($replacement instanceof Response) {
                $response = $replacement;
            }
        }

        return $response ?? $result;
    }

    /**
     * @param list<object> $attributes
     */
    private function runAfterExceptionAttributes(
        array $attributes,
        Request $request,
        Throwable $exception
    ): void {
        foreach (array_reverse($attributes) as $attribute) {
            if (!$attribute instanceof AfterExceptionAttribute) {
                continue;
            }

            try {
                $attribute->afterException($request, $exception, $this->container);
            } catch (Throwable) {
                // A finalizacao nao pode ocultar a excecao que interrompeu a request.
            }
        }
    }

    private function responseFromResult(mixed $result): Response
    {
        return Response::fromResult($result);
    }
}



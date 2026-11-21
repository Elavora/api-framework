<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Logging;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

/**
 * Registra o middleware AccessLog como o mais externo do pipeline (quando registrada antes das rotas e middlewares).
 *
 * O Logger e resolvido no container a cada requisicao, entao pode ser registrado por qualquer extensao, antes ou
 * depois desta. Sem Logger no container, o log de acesso simplesmente nao grava.
 */
final class AccessLogExtension implements Extension
{
    /**
     * @param list<string> $exclude Caminhos ignorados.
     * @param callable(Request, Response): array<string, mixed>|null $context Contexto extra do app.
     */
    public function __construct(
        private readonly array $exclude = AccessLog::DEFAULT_EXCLUDE,
        private readonly mixed $context = null,
    ) {}

    public function register(Application $application): void
    {
        $container = $application->container();
        $context = is_callable($this->context) ? $this->context : null;

        $application->middleware(new AccessLog(
            logger: static fn(): ?Logger => $container->has(Logger::class)
                ? self::logger($container->get(Logger::class))
                : null,
            exclude: $this->exclude,
            context: $context,
        ));
    }

    private static function logger(object $service): ?Logger
    {
        return $service instanceof Logger ? $service : null;
    }
}

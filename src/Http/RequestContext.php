<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Http;

use Closure;

/**
 * Mantem o identificador da request ativa compartilhado pelos servicos do framework.
 */
final class RequestContext
{
    private static ?string $requestId = null;

    private function __construct()
    {
    }

    /**
     * Executa uma operacao usando o request-id informado e restaura o contexto anterior ao final.
     *
     * @template T
     * @param callable(): T $operation
     * @return T
     */
    public static function run(string $requestId, callable $operation): mixed
    {
        $previousRequestId = self::$requestId;
        self::$requestId = $requestId;

        try {
            return Closure::fromCallable($operation)();
        } finally {
            self::$requestId = $previousRequestId;
        }
    }

    /**
     * Retorna o request-id ativo ou cria um contexto para logs fora do lifecycle HTTP.
     */
    public static function requestId(): string
    {
        if (self::$requestId !== null) {
            return self::$requestId;
        }

        $header = $_SERVER['HTTP_X_REQUEST_ID'] ?? null;
        if (is_string($header) && trim($header) !== '') {
            return self::$requestId = trim($header);
        }

        return self::$requestId = bin2hex(random_bytes(16));
    }
}

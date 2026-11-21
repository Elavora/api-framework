<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Logging;

use Elavora\Api\Framework\Exceptions\HttpException;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Closure;
use Throwable;

/**
 * Middleware de log de acesso: grava uma linha `http.request` por requisicao, depois que o pipeline responde.
 *
 * Nunca registra corpo, cookies, Authorization nem valores de query. Qualquer falha do logger e engolida, para que
 * o log de acesso jamais altere a resposta. E opt-in: so age quando registrado (ou com ACCESS_LOG=true no bootstrap).
 */
final class AccessLog
{
    public const MESSAGE = 'http.request';

    /** Caminhos ignorados por padrao. */
    public const DEFAULT_EXCLUDE = ['/health', '/index/health'];

    private const MAX_QUERY_KEYS = 50;

    private const MAX_FIELD_LENGTH = 512;

    /** @var Logger|Closure(): ?Logger */
    private readonly Logger|Closure $logger;

    /** @var list<string> */
    private readonly array $exclude;

    /** @var Closure(Request, Response): array<string, mixed>|null */
    private readonly ?Closure $context;

    /**
     * @param Logger|callable(): ?Logger $logger Logger, ou funcao que o resolve na hora (null desliga o log).
     * @param list<string> $exclude Caminhos ignorados; um item terminado em `*` casa por prefixo.
     * @param callable(Request, Response): array<string, mixed>|null $context Contexto extra definido pelo app
     *        (por exemplo user_id). Suas chaves sao somadas as do framework e podem substitui-las.
     */
    public function __construct(
        Logger|callable $logger,
        array $exclude = self::DEFAULT_EXCLUDE,
        ?callable $context = null,
    ) {
        $this->logger = $logger instanceof Logger ? $logger : Closure::fromCallable($logger);
        $this->exclude = array_values(array_filter(
            array_map(self::normalizePath(...), $exclude),
            static fn(string $path): bool => $path !== '',
        ));
        $this->context = $context === null ? null : Closure::fromCallable($context);
    }

    /**
     * Interpreta uma lista separada por virgula (ACCESS_LOG_EXCLUDE) em caminhos.
     *
     * @return list<string>
     */
    public static function parseExclude(string $list): array
    {
        return array_values(array_filter(
            array_map(trim(...), explode(',', $list)),
            static fn(string $path): bool => $path !== '',
        ));
    }

    /**
     * @param callable(Request): Response $next
     */
    public function __invoke(Request $request, callable $next): Response
    {
        if ($this->isExcluded($request->path())) {
            return $next($request);
        }

        $start = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $status = $exception instanceof HttpException ? $exception->status() : 500;
            $this->record($request, Response::json([], $status), $start, false);

            throw $exception;
        }

        $this->record($request, $response, $start, true);

        return $response;
    }

    private function isExcluded(string $path): bool
    {
        foreach ($this->exclude as $pattern) {
            if (str_ends_with($pattern, '*')) {
                if (str_starts_with($path, substr($pattern, 0, -1))) {
                    return true;
                }
                continue;
            }
            if ($path === $pattern) {
                return true;
            }
        }

        return false;
    }

    private function record(Request $request, Response $response, int $start, bool $sizeKnown): void
    {
        try {
            $logger = $this->logger instanceof Logger ? $this->logger : ($this->logger)();
            if (!$logger instanceof Logger) {
                return;
            }

            $status = $response->status();
            $context = [
                'method' => $request->method(),
                'path' => $request->path(),
            ];

            $queryKeys = self::queryKeys($request);
            if ($queryKeys !== []) {
                $context['query_keys'] = $queryKeys;
            }

            $context['status'] = $status;
            $context['duration_ms'] = round((hrtime(true) - $start) / 1_000_000, 2);
            if ($sizeKnown) {
                $context['bytes'] = strlen($response->body());
            }
            $context['request_id'] = $request->requestId();
            $context['ip'] = self::ip();
            $context['user_agent'] = self::limit($request->header('User-Agent'));

            if ($this->context !== null) {
                $context = array_merge($context, ($this->context)($request, $response));
            }

            $logger->write(self::level($status), self::MESSAGE, $context);
        } catch (Throwable) {
            // o log de acesso nunca altera a resposta.
        }
    }

    private static function level(int $status): string
    {
        return match (true) {
            $status >= 500 => 'error',
            $status >= 400 => 'warning',
            default => 'info',
        };
    }

    /**
     * Apenas os NOMES dos parametros da query, nunca os valores.
     *
     * @return list<string>
     */
    private static function queryKeys(Request $request): array
    {
        $query = $request->query();
        if (!is_array($query)) {
            return [];
        }

        $keys = [];
        foreach (array_slice(array_keys($query), 0, self::MAX_QUERY_KEYS) as $key) {
            $keys[] = substr((string) $key, 0, 64);
        }

        return $keys;
    }

    private static function ip(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        return is_string($ip) && $ip !== '' ? $ip : null;
    }

    private static function limit(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, self::MAX_FIELD_LENGTH);
    }

    private static function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '';
        }
        $path = '/' . ltrim($path, '/');

        return $path !== '/' && !str_ends_with($path, '*') ? rtrim($path, '/') : $path;
    }
}

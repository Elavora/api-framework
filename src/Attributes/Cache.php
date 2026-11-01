<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Attributes;

use Attribute;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\AfterResponseAttribute;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use RuntimeException;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Armazena a resposta de uma action em cache por tempo definido.
 *
 * Usa o CacheStore registrado no container. A chave considera metodo, path,
 * query string, corpo da request e headers opcionais informados em varyByHeaders.
 *
 * Exemplo: #[Cache(seconds: 60, varyByHeaders: ['Authorization'])]
 */
final class Cache implements BeforeRequestAttribute, AfterResponseAttribute
{
    private bool $hit = false;

    /**
     * @param int $seconds Tempo de vida do cache em segundos.
     * @param list<string> $varyByHeaders Headers que devem compor a chave de cache.
     * @param array<string, mixed> $extra Valores adicionais para diferenciar a chave.
     */
    public function __construct(
        private readonly int $seconds,
        private readonly array $varyByHeaders = [],
        private readonly array $extra = [],
    ) {}

    /**
     * Retorna resposta em cache quando a chave existir.
     */
    public function before(Request $request, Container $container): ?Response
    {
        $cached = $this->cacheStore($container)->get($this->key($request));

        if ($cached instanceof Response) {
            $this->hit = true;

            return $cached;
        }

        return null;
    }

    /**
     * Salva respostas de sucesso no cache. Em hit nao grava nada: o TTL e fixo, contado da gravacao.
     */
    public function after(Request $request, Response $response, Container $container): ?Response
    {
        if ($this->hit) {
            return null;
        }

        if ($response->status() >= 200 && $response->status() < 300) {
            $this->cacheStore($container)->set($this->key($request), $response, $this->seconds);
        }

        return null;
    }

    /**
     * @return array{cache: array{seconds: int, varyByHeaders: list<string>}}
     */
    public function options(): array
    {
        return [
            'cache' => [
                'seconds' => $this->seconds,
                'varyByHeaders' => $this->varyByHeaders,
            ],
        ];
    }

    private function cacheStore(Container $container): CacheStore
    {
        if (!$container->has(CacheStore::class)) {
            throw new RuntimeException('CacheStore nao foi registrado no container.');
        }

        $cacheStore = $container->get(CacheStore::class);
        if (!$cacheStore instanceof CacheStore) {
            throw new RuntimeException(
                'CacheStore registrado deve implementar Elavora\\Api\\Framework\\Contracts\\CacheStore.',
            );
        }

        return $cacheStore;
    }

    private function key(Request $request): string
    {
        $headers = [];
        foreach ($this->varyByHeaders as $header) {
            $headers[$header] = $request->header($header);
        }

        return hash('sha256', serialize([
            'method' => $request->method(),
            'path' => $request->path(),
            'query' => $request->query(),
            'body' => $request->input(),
            'headers' => $headers,
            'extra' => $this->extra,
        ]));
    }
}

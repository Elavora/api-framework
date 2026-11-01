<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Attribute;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Attributes\Cache;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

/**
 * Regras da secao "Cache de respostas" de docs/USO.md: TTL fixo contado da gravacao.
 */
final class CacheTtlFixoTest extends TestCase
{
    private Application $application;

    private ClockedCountingCacheStore $cache;

    protected function setUp(): void
    {
        CacheTtlActions::reset();
        $this->cache = new ClockedCountingCacheStore();
        $this->application = Application::create();
        $this->application->container()->instance(CacheStore::class, $this->cache);
        $this->application->get('/task', [CacheTtlActions::class, 'ok']);
        $this->application->get('/created', [CacheTtlActions::class, 'created']);
        $this->application->get('/redirect', [CacheTtlActions::class, 'redirect']);
        $this->application->get('/bad', [CacheTtlActions::class, 'bad']);
        $this->application->get('/error', [CacheTtlActions::class, 'error']);
        $this->application->get('/boom', [CacheTtlActions::class, 'boom']);
        $this->application->get('/marked', [CacheTtlActions::class, 'marked']);
    }

    private function get(string $path): Response
    {
        return $this->application->handle(new Request(method: 'GET', path: $path));
    }

    public function testMissComStatus2xxGravaUmaUnicaVezComOTtlDoAttribute(): void
    {
        $response = $this->get('/task');

        self::assertSame(200, $response->status());
        self::assertSame(1, CacheTtlActions::$runs['ok']);
        self::assertCount(1, $this->cache->sets);
        self::assertSame(10, $this->cache->sets[0]['ttl']);
    }

    public function testMissComOutro2xxTambemGrava(): void
    {
        $response = $this->get('/created');

        self::assertSame(201, $response->status());
        self::assertCount(1, $this->cache->sets);
    }

    public function testHitNaoRodaAActionNemChamaSetNemDelete(): void
    {
        $first = $this->get('/task');
        self::assertCount(1, $this->cache->sets);

        for ($i = 0; $i < 5; $i++) {
            $this->cache->advance(1);
            $hit = $this->get('/task');
            self::assertSame($first->body(), $hit->body());
        }

        self::assertSame(1, CacheTtlActions::$runs['ok']);
        self::assertCount(1, $this->cache->sets, 'hit nao pode chamar set');
        self::assertSame(0, $this->cache->deletes, 'hit nao pode chamar delete');
    }

    public function testHitNaoMudaOTempoDeVidaDaEntrada(): void
    {
        $this->get('/task');
        $expiresAt = $this->cache->expiresAtOfOnlyEntry();

        $this->cache->advance(5);
        $this->get('/task');

        self::assertSame($expiresAt, $this->cache->expiresAtOfOnlyEntry());
    }

    public function testTtlFixoContadoDaGravacaoComUmaRequestPorSegundo(): void
    {
        // Tabela de docs/USO.md: uma request por segundo, de 0 s a 20 s, #[Cache(10)].
        $setsAteAgora = [];
        for ($t = 0; $t <= 20; $t++) {
            $this->get('/task');
            $setsAteAgora[$t] = count($this->cache->sets);
            $this->cache->advance(1);
        }

        self::assertSame(1, $setsAteAgora[0]);
        self::assertSame(1, $setsAteAgora[9]);
        self::assertSame(2, $setsAteAgora[10], 'em 10 s a entrada expira e ha um novo miss');
        self::assertSame(2, $setsAteAgora[19]);
        self::assertSame(3, $setsAteAgora[20], 'em 20 s ha outro miss');
        self::assertSame(3, CacheTtlActions::$runs['ok'], 'a action roda so nos misses (0 s, 10 s, 20 s)');
    }

    public function testStatusNao2xxNaoEGravado(): void
    {
        foreach (['/redirect' => 302, '/bad' => 400, '/error' => 500] as $path => $status) {
            $response = $this->get($path);
            self::assertSame($status, $response->status());
        }

        self::assertSame([], $this->cache->sets);
    }

    public function testExcecaoNaActionNaoGravaNada(): void
    {
        try {
            $this->get('/boom');
        } catch (Throwable) {
            // pode propagar ou virar resposta de erro; o que importa e nao gravar.
        }

        self::assertSame([], $this->cache->sets);
        self::assertSame(0, $this->cache->deletes);
    }

    public function testChavesDiferentesTemEntradasDiferentes(): void
    {
        $this->get('/task');
        $this->get('/created');
        $this->get('/task');
        $this->get('/created');

        self::assertCount(2, $this->cache->sets);
        self::assertSame(1, CacheTtlActions::$runs['ok']);
        self::assertSame(1, CacheTtlActions::$runs['created']);
    }

    public function testOutrosAttributesContinuamRodandoNoHit(): void
    {
        $this->get('/marked');
        $this->get('/marked');
        $this->get('/marked');

        self::assertSame(1, CacheTtlActions::$runs['marked'], 'a action roda so no miss');
        self::assertSame(3, CacheTtlMarker::$calls, 'o attribute proprio roda em todas as requests, hit inclusive');
        self::assertCount(1, $this->cache->sets);
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class CacheTtlMarker implements BeforeRequestAttribute
{
    public static int $calls = 0;

    public function before(Request $request, Container $container): ?Response
    {
        self::$calls++;

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return [];
    }
}

final class CacheTtlActions
{
    /** @var array<string, int> */
    public static array $runs = [];

    public static function reset(): void
    {
        self::$runs = [];
        CacheTtlMarker::$calls = 0;
    }

    private static function count(string $name): int
    {
        self::$runs[$name] = (self::$runs[$name] ?? 0) + 1;

        return self::$runs[$name];
    }

    #[Cache(10)]
    public function ok(Request $request): Response
    {
        return Response::json(['run' => self::count('ok')]);
    }

    #[Cache(10)]
    public function created(Request $request): Response
    {
        return Response::json(['run' => self::count('created')], 201);
    }

    #[Cache(10)]
    public function redirect(Request $request): Response
    {
        return Response::json(['run' => self::count('redirect')], 302);
    }

    #[Cache(10)]
    public function bad(Request $request): Response
    {
        return Response::json(['run' => self::count('bad')], 400);
    }

    #[Cache(10)]
    public function error(Request $request): Response
    {
        return Response::json(['run' => self::count('error')], 500);
    }

    #[Cache(10)]
    public function boom(Request $request): Response
    {
        throw new RuntimeException('falha na action');
    }

    #[CacheTtlMarker]
    #[Cache(10)]
    public function marked(Request $request): Response
    {
        return Response::json(['run' => self::count('marked')]);
    }
}

/**
 * CacheStore falso com relogio virtual: conta set/delete e expira entradas pelo TTL.
 */
final class ClockedCountingCacheStore implements CacheStore
{
    /** @var list<array{key: string, ttl: ?int}> */
    public array $sets = [];

    public int $deletes = 0;

    private int $now = 0;

    /** @var array<string, array{value: mixed, expiresAt: ?int}> */
    private array $items = [];

    public function advance(int $seconds): void
    {
        $this->now += $seconds;
    }

    public function expiresAtOfOnlyEntry(): ?int
    {
        return array_values($this->items)[0]['expiresAt'] ?? null;
    }

    public function get(string $key): mixed
    {
        $item = $this->items[$key] ?? null;
        if ($item === null) {
            return null;
        }
        if ($item['expiresAt'] !== null && $item['expiresAt'] <= $this->now) {
            unset($this->items[$key]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->sets[] = ['key' => $key, 'ttl' => $ttlSeconds];
        $this->items[$key] = [
            'value' => $value,
            'expiresAt' => $ttlSeconds === null ? null : $this->now + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        $this->deletes++;
        unset($this->items[$key]);
    }
}

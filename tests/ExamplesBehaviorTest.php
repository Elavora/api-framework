<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use App\DataTypes\ProjectCode;
use App\Support\InMemoryCacheStore;
use App\Support\InMemoryTransactionManager;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\TransactionManager;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * docs/EXEMPLOS.md, seção "Referência exata". Cada caso usa uma aplicação nova.
 */
final class ExamplesBehaviorTest extends TestCase
{
    private const STORE_BODY = ['code' => 'app-1234', 'title' => 'Revisar'];
    private const TASKS = ['tasks' => [['code' => 'APP-0001', 'title' => 'Escrever a documentação']]];
    private const FORBIDDEN = ['message' => 'Permissão insuficiente.'];
    private const INVALID_CODE = ['message' => 'Invalid fields', 'errors' => ['fields' => ['code' => 'Invalid field type']]];
    private const NOT_FOUND = ['message' => 'Not Found'];

    private static function app(): Application
    {
        $app = require dirname(__DIR__) . '/examples/bootstrap.php';
        self::assertInstanceOf(Application::class, $app, 'examples/bootstrap.php deve devolver a Application');

        return $app;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, string> $headers
     */
    private static function request(string $method, string $path, ?array $body = null, array $headers = []): Request
    {
        return $body === null
            ? new Request(method: $method, path: $path, headers: $headers)
            : new Request(method: $method, path: $path, body: $body, headers: $headers);
    }

    /**
     * Confere os headers comuns e devolve o corpo JSON sem o request_id.
     *
     * @return mixed
     */
    private static function assertCommon(Response $response, int $status): mixed
    {
        self::assertSame($status, $response->status());
        $headers = $response->headers();
        self::assertSame('1', $headers['X-Api-Version'] ?? null, 'header X-Api-Version');
        self::assertArrayHasKey('X-Request-Id', $headers);
        self::assertNotSame('', $headers['X-Request-Id']);

        $body = json_decode($response->body(), true, 512, JSON_THROW_ON_ERROR);
        if ($status >= 400) {
            self::assertIsArray($body);
            self::assertArrayHasKey('request_id', $body);
            self::assertSame($headers['X-Request-Id'], $body['request_id']);
            unset($body['request_id']);
        }

        return $body;
    }

    /**
     * @return list<string>
     */
    private static function transactionLog(Application $app): array
    {
        $manager = $app->container()->get(TransactionManager::class);
        self::assertInstanceOf(InMemoryTransactionManager::class, $manager);

        return $manager->log();
    }

    private static function cacheCount(Application $app): int
    {
        $store = $app->container()->get(CacheStore::class);
        self::assertInstanceOf(InMemoryCacheStore::class, $store);

        return $store->count();
    }

    // ---------------------------------------------------------------
    // Requests
    // ---------------------------------------------------------------

    public function testCase01Health(): void
    {
        $body = self::assertCommon(self::app()->handle(self::request('GET', '/health')), 200);

        self::assertSame(['status' => 'ok'], $body);
    }

    public function testCase02TaskIndexIsCached(): void
    {
        $app = self::app();

        $body = self::assertCommon($app->handle(self::request('GET', '/task')), 200);

        self::assertSame(self::TASKS, $body);
        self::assertSame(1, self::cacheCount($app));
    }

    public function testCase03TaskIndexTwiceKeepsOneCacheItem(): void
    {
        $app = self::app();

        $first = self::assertCommon($app->handle(self::request('GET', '/task')), 200);
        $second = self::assertCommon($app->handle(self::request('GET', '/task')), 200);

        self::assertSame(self::TASKS, $first);
        self::assertSame(self::TASKS, $second);
        self::assertSame(1, self::cacheCount($app));
    }

    public function testCase04TaskIndexExplicitAction(): void
    {
        $body = self::assertCommon(self::app()->handle(self::request('GET', '/task/index')), 200);

        self::assertSame(self::TASKS, $body);
    }

    public function testCase05StoreCreatesAndCommits(): void
    {
        $app = self::app();

        $body = self::assertCommon(
            $app->handle(self::request('POST', '/task/store', self::STORE_BODY, ['X-App-Permission' => 'tasks.write'])),
            201,
        );

        self::assertSame(['code' => 'APP-1234', 'title' => 'Revisar'], $body);
        self::assertSame(['begin', 'commit'], self::transactionLog($app));
    }

    /**
     * @param array<string, mixed> $requestBody
     * @param array<string, string> $headers
     * @param array<string, mixed> $expected
     */
    #[DataProvider('refusedStores')]
    public function testRefusedStoreDoesNotOpenTransaction(array $requestBody, array $headers, int $status, array $expected): void
    {
        $app = self::app();

        $body = self::assertCommon($app->handle(self::request('POST', '/task/store', $requestBody, $headers)), $status);

        self::assertSame($expected, $body);
        self::assertSame([], self::transactionLog($app));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, array<string, string>, int, array<string, mixed>}>
     */
    public static function refusedStores(): iterable
    {
        yield 'caso 6: sem X-App-Permission' => [self::STORE_BODY, [], 403, self::FORBIDDEN];
        yield 'caso 7: permissão tasks.read' => [self::STORE_BODY, ['X-App-Permission' => 'tasks.read'], 403, self::FORBIDDEN];
        yield 'caso 8: code inválido' => [
            ['code' => '1234', 'title' => 'Revisar'],
            ['X-App-Permission' => 'tasks.write'],
            400,
            self::INVALID_CODE,
        ];
        yield 'caso 9: sem title' => [
            ['code' => 'app-1234'],
            ['X-App-Permission' => 'tasks.write'],
            400,
            ['message' => 'Invalid fields', 'errors' => ['fields' => ['title' => 'Field not found']]],
        ];
        yield 'caso 10: code inválido e sem permissão (validação antes do Permission)' => [
            ['code' => '1234', 'title' => 'Revisar'],
            [],
            400,
            self::INVALID_CODE,
        ];
    }

    public function testCase11StoreWithGetIsMethodNotAllowed(): void
    {
        $response = self::app()->handle(self::request('GET', '/task/store'));

        $body = self::assertCommon($response, 405);

        self::assertSame(['message' => 'Method GET is not allowed for this endpoint.'], $body);
        self::assertSame('POST', $response->headers()['Allow'] ?? null);
    }

    public function testCase12ExplicitArchiveRoute(): void
    {
        $body = self::assertCommon(self::app()->handle(self::request('POST', '/tasks/archive')), 200);

        self::assertSame(['archived' => true], $body);
    }

    public function testCase13ArchiveIsNotExposedByConvention(): void
    {
        $body = self::assertCommon(self::app()->handle(self::request('POST', '/task/archive')), 404);

        self::assertSame(self::NOT_FOUND, $body);
    }

    public function testCase14UnknownControllerIsNotFound(): void
    {
        $body = self::assertCommon(self::app()->handle(self::request('GET', '/tasks')), 404);

        self::assertSame(self::NOT_FOUND, $body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeConventionPaths(): array
    {
        return [
            'caso 15: controller com maiúscula' => ['/Task'],
            'caso 16: action com maiúscula' => ['/task/Index'],
            'caso 17: três segmentos' => ['/task/index/1'],
        ];
    }

    #[DataProvider('unsafeConventionPaths')]
    public function testUnsafeConventionPathIsNotFound(string $path): void
    {
        $body = self::assertCommon(self::app()->handle(self::request('GET', $path)), 404);

        self::assertSame(self::NOT_FOUND, $body);
    }

    public function testCase18StringHandlerIsRefusedOnRegistration(): void
    {
        $app = self::app();

        $this->expectException(InvalidArgumentException::class);
        $app->get('/x', 'App\Http\Controller\TaskController::index');
    }

    public function testEachRequireReturnsANewApplication(): void
    {
        self::assertNotSame(self::app(), self::app());
    }

    // ---------------------------------------------------------------
    // ProjectCode
    // ---------------------------------------------------------------

    #[DataProvider('projectCodeValidity')]
    public function testProjectCodeIsValid(mixed $value, bool $expected): void
    {
        self::assertSame($expected, ProjectCode::isValid($value));
    }

    /**
     * @return iterable<string, array{mixed, bool}>
     */
    public static function projectCodeValidity(): iterable
    {
        yield "'APP-1234'" => ['APP-1234', true];
        yield "'app-1234'" => ['app-1234', true];
        yield "'APP-123'" => ['APP-123', false];
        yield "'APP1234'" => ['APP1234', false];
        yield "'1234'" => ['1234', false];
        yield '1234 (int)' => [1234, false];
        yield 'null' => [null, false];
    }

    public function testProjectCodeFromNormalizesToUppercase(): void
    {
        self::assertSame('APP-1234', ProjectCode::from('app-1234')->value());
    }

    public function testProjectCodeSerializesAsString(): void
    {
        self::assertSame('"ABC-0001"', json_encode(ProjectCode::from('abc-0001')));
    }

    public function testProjectCodeFromInvalidThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('ProjectCode deve usar o formato APP-1234.');

        ProjectCode::from('APP-12');
    }

    // ---------------------------------------------------------------
    // InMemoryCacheStore
    // ---------------------------------------------------------------

    public function testCacheGetOnEmptyIsNull(): void
    {
        self::assertNull((new InMemoryCacheStore())->get('a'));
    }

    public function testCacheSetWithoutTtl(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set('a', 1);

        self::assertSame(1, $cache->get('a'));
    }

    public function testCacheSetWithTtl(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set('a', 1, 60);

        self::assertSame(1, $cache->get('a'));
    }

    public function testCacheSetWithZeroTtlExpiresAndIsRemoved(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set('a', 1, 0);

        self::assertNull($cache->get('a'));
        self::assertSame(0, $cache->count());
    }

    public function testCacheDelete(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set('a', 1);
        $cache->delete('a');

        self::assertNull($cache->get('a'));
    }

    public function testCacheCount(): void
    {
        $cache = new InMemoryCacheStore();
        $cache->set('a', 1);
        $cache->set('b', 2);

        self::assertSame(2, $cache->count());
    }

    // ---------------------------------------------------------------
    // InMemoryTransactionManager
    // ---------------------------------------------------------------

    public function testTransactionLogStartsEmpty(): void
    {
        self::assertSame([], (new InMemoryTransactionManager())->log());
    }

    public function testTransactionBeginAndRollback(): void
    {
        $manager = new InMemoryTransactionManager();

        self::assertTrue($manager->begin());
        self::assertTrue($manager->rollback());
        self::assertSame(['begin', 'rollback'], $manager->log());
    }
}

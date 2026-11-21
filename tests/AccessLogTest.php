<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\LogWriter;
use Elavora\Api\Framework\Exceptions\HttpException;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Elavora\Api\Framework\Logging\AccessLog;
use Elavora\Api\Framework\Logging\AccessLogExtension;
use Elavora\Api\Framework\Logging\Logger;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * @see docs/BOOTSTRAP.md#log-de-acesso
 */
final class AccessLogTest extends TestCase
{
    private AccessLogMemoryWriter $writer;

    protected function setUp(): void
    {
        $this->writer = new AccessLogMemoryWriter();
        $_SERVER['REMOTE_ADDR'] = '203.0.113.9';
    }

    protected function tearDown(): void
    {
        unset($_SERVER['REMOTE_ADDR']);
    }

    public function testSuccessLogsInfoWithAllFields(): void
    {
        $app = $this->app(static fn(Application $app) => $app->get('/ok', static fn(): Response => Response::text('abcde')));

        $response = $app->handle(new Request(
            'GET',
            '/ok',
            query: ['page' => '2', 'token' => 'segredo'],
            headers: ['User-Agent' => 'teste/1.0', 'X-Request-Id' => 'req-1'],
        ));

        self::assertSame(200, $response->status());
        self::assertCount(1, $this->writer->entries);
        $entry = $this->writer->entries[0];
        self::assertSame('info', $entry['level']);
        self::assertSame('http.request', $entry['message']);
        $context = $entry['context'];
        self::assertSame('GET', $context['method']);
        self::assertSame('/ok', $context['path']);
        self::assertSame(['page', 'token'], $context['query_keys']);
        self::assertSame(200, $context['status']);
        self::assertSame(5, $context['bytes']);
        self::assertSame('req-1', $context['request_id']);
        self::assertSame('203.0.113.9', $context['ip']);
        self::assertSame('teste/1.0', $context['user_agent']);
        self::assertIsFloat($context['duration_ms']);
        self::assertGreaterThanOrEqual(0.0, $context['duration_ms']);
    }

    public function testNotFoundLogsWarning(): void
    {
        $this->app()->handle(new Request('GET', '/nao-existe'));

        self::assertSame('warning', $this->writer->entries[0]['level']);
        self::assertSame(404, $this->writer->entries[0]['context']['status']);
    }

    public function testUnhandledExceptionLogsErrorWith500AndStillReturns500(): void
    {
        $app = $this->app(static fn(Application $app) => $app->get('/boom', static function (): never {
            throw new RuntimeException('detalhe privado');
        }));

        $response = $app->handle(new Request('GET', '/boom'));

        self::assertSame(500, $response->status());
        self::assertSame('error', $this->writer->entries[0]['level']);
        self::assertSame(500, $this->writer->entries[0]['context']['status']);
        self::assertArrayNotHasKey('bytes', $this->writer->entries[0]['context']);
        self::assertStringNotContainsString('detalhe privado', json_encode($this->writer->entries, JSON_THROW_ON_ERROR));
    }

    public function testHttpExceptionUsesItsStatusForTheLevel(): void
    {
        $app = $this->app(static fn(Application $app) => $app->get('/forbidden', static function (): never {
            throw new HttpException('Proibido', 403);
        }));

        $response = $app->handle(new Request('GET', '/forbidden'));

        self::assertSame(403, $response->status());
        self::assertSame('warning', $this->writer->entries[0]['level']);
        self::assertSame(403, $this->writer->entries[0]['context']['status']);
    }

    public function testExcludedPathsAreNotLogged(): void
    {
        $app = $this->app(static fn(Application $app) => $app->get('/health', static fn(): Response => Response::text('ok')));

        $app->handle(new Request('GET', '/health'));
        $app->handle(new Request('GET', '/health/'));
        $app->handle(new Request('GET', '/index/health'));

        self::assertSame([], $this->writer->entries);
    }

    public function testExcludeListIsConfigurableAndSupportsPrefix(): void
    {
        $app = Application::create();
        $app->container()->instance(Logger::class, $this->logger());
        $app->extend(new AccessLogExtension(['/ping', 'interno/*']));
        $app->get('/ping', static fn(): Response => Response::text('pong'));
        $app->get('/health', static fn(): Response => Response::text('ok'));
        $app->get('/interno/a', static fn(): Response => Response::text('a'));

        $app->handle(new Request('GET', '/ping'));
        $app->handle(new Request('GET', '/interno/a'));
        $app->handle(new Request('GET', '/health'));

        self::assertCount(1, $this->writer->entries);
        self::assertSame('/health', $this->writer->entries[0]['context']['path']);
    }

    public function testNeverLogsSensitiveData(): void
    {
        $app = $this->app(static fn(Application $app) => $app->post('/login', static fn(): Response => Response::json(['jwt' => 'resposta-secreta'])));

        $app->handle(new Request(
            'POST',
            '/login',
            query: ['senha' => 'valor-query-secreto'],
            body: ['password' => 'corpo-secreto'],
            headers: ['Authorization' => 'Bearer token-secreto', 'Cookie' => 'sid=cookie-secreto'],
        ));

        $json = json_encode($this->writer->entries, JSON_THROW_ON_ERROR);
        foreach (['valor-query-secreto', 'corpo-secreto', 'token-secreto', 'cookie-secreto', 'resposta-secreta'] as $secret) {
            self::assertStringNotContainsString($secret, $json);
        }
        self::assertSame(['senha'], $this->writer->entries[0]['context']['query_keys']);
    }

    public function testAppContextIsMergedIntoTheEntry(): void
    {
        $app = Application::create();
        $app->container()->instance(Logger::class, $this->logger());
        $app->extend(new AccessLogExtension(
            context: static fn(Request $request, Response $response): array => [
                'user_id' => 42,
                'status_family' => intdiv($response->status(), 100),
            ],
        ));
        $app->get('/x', static fn(): Response => Response::text('x'));

        $app->handle(new Request('GET', '/x'));

        self::assertSame(42, $this->writer->entries[0]['context']['user_id']);
        self::assertSame(2, $this->writer->entries[0]['context']['status_family']);
        self::assertSame('/x', $this->writer->entries[0]['context']['path']);
    }

    public function testFailingLoggerOrContextNeverChangesTheResponse(): void
    {
        $throwing = new class implements LogWriter {
            public function write(array $entry): void
            {
                throw new RuntimeException('mongo fora do ar');
            }
        };
        $app = Application::create();
        $app->container()->instance(Logger::class, new Logger($throwing));
        $app->extend(new AccessLogExtension());
        $app->get('/ok', static fn(): Response => Response::text('corpo'));
        $app->get('/boom', static function (): never {
            throw new RuntimeException('x');
        });

        $ok = $app->handle(new Request('GET', '/ok'));
        $boom = $app->handle(new Request('GET', '/boom'));

        self::assertSame(200, $ok->status());
        self::assertSame('corpo', $ok->body());
        self::assertSame(500, $boom->status());

        $app = $this->app(null, static function (): array {
            throw new RuntimeException('contexto quebrado');
        });
        $app->get('/ok', static fn(): Response => Response::text('corpo'));

        self::assertSame('corpo', $app->handle(new Request('GET', '/ok'))->body());
        self::assertSame([], $this->writer->entries);
    }

    public function testWithoutLoggerInContainerNothingIsWrittenAndResponseIsUntouched(): void
    {
        $app = Application::create();
        $app->extend(new AccessLogExtension());
        $app->get('/ok', static fn(): Response => Response::text('corpo'));

        self::assertSame('corpo', $app->handle(new Request('GET', '/ok'))->body());
    }

    public function testIsOffByDefaultInTheEnvironmentBootstrap(): void
    {
        foreach ([[], ['ACCESS_LOG' => 'false'], ['ACCESS_LOG' => ''], ['ACCESS_LOG' => '0']] as $environment) {
            self::assertSame([], EnvironmentExtensions::load('/project', $environment));
        }
    }

    public function testEnvironmentTurnsItOnWithDefaultAndCustomExclusions(): void
    {
        $extensions = EnvironmentExtensions::load('/project', ['ACCESS_LOG' => 'true']);

        self::assertCount(1, $extensions);
        self::assertInstanceOf(AccessLogExtension::class, $extensions[0]);

        $app = ApplicationBootstrap::create('/project', environment: ['ACCESS_LOG' => 'true', 'ACCESS_LOG_EXCLUDE' => '/ping, /status']);
        $app->container()->instance(Logger::class, $this->logger());
        foreach (['/ping', '/status', '/health', '/real'] as $path) {
            $app->get($path, static fn(): Response => Response::text('ok'));
            $app->handle(new Request('GET', $path));
        }

        self::assertSame(['/health', '/real'], array_column(array_column($this->writer->entries, 'context'), 'path'));
    }

    public function testEmptyExcludeListLogsEverything(): void
    {
        $app = ApplicationBootstrap::create('/project', environment: ['ACCESS_LOG' => '1', 'ACCESS_LOG_EXCLUDE' => '']);
        $app->container()->instance(Logger::class, $this->logger());
        $app->get('/health', static fn(): Response => Response::text('ok'));

        $app->handle(new Request('GET', '/health'));

        self::assertCount(1, $this->writer->entries);
    }

    public function testDefaultExcludeWhenVariableIsMissing(): void
    {
        $app = ApplicationBootstrap::create('/project', environment: ['ACCESS_LOG' => 'true']);
        $app->container()->instance(Logger::class, $this->logger());
        $app->get('/health', static fn(): Response => Response::text('ok'));

        $app->handle(new Request('GET', '/health'));

        self::assertSame([], $this->writer->entries);
    }

    public function testLoggerCanComeFromAnExtensionRegisteredAfterTheAccessLog(): void
    {
        $writer = $this->writer;
        $late = new class ($writer) implements Extension {
            public function __construct(private readonly LogWriter $writer) {}

            public function register(Application $application): void
            {
                $application->container()->instance(Logger::class, new Logger($this->writer));
            }
        };
        $app = ApplicationBootstrap::create(
            '/project',
            extensions: [$late],
            environment: ['ACCESS_LOG' => 'true'],
        );
        $app->get('/x', static fn(): Response => Response::text('x'));

        $app->handle(new Request('GET', '/x'));

        self::assertCount(1, $this->writer->entries);
    }

    private function logger(): Logger
    {
        return new Logger($this->writer);
    }

    /**
     * @param (callable(Application): mixed)|null $routes
     * @param (callable(Request, Response): array<string, mixed>)|null $context
     */
    private function app(?callable $routes = null, ?callable $context = null): Application
    {
        $app = Application::create();
        $app->container()->instance(Logger::class, $this->logger());
        $app->extend(new AccessLogExtension(context: $context));
        if ($routes !== null) {
            $routes($app);
        }

        return $app;
    }
}

final class AccessLogMemoryWriter implements LogWriter
{
    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function write(array $entry): void
    {
        if (($entry['message'] ?? null) === 'http.request') {
            $this->entries[] = $entry;
        }
    }
}

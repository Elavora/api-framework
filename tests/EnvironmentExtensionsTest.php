<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\Extension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class InvalidExtensionFixture { public function __construct(array $config) {} }

final class ConfiguredExtensionSpy implements Extension
{
    public function __construct(public array $config) {}

    public function register(Application $application): void
    {
        $application->container()->instance('official.config', (object) $this->config);
    }
}

final class EnvironmentExtensionsTest extends TestCase
{
    public function testEmptyEnvironmentNeedsNoOptionalPackages(): void
    {
        self::assertSame([], EnvironmentExtensions::load('/project', []));
    }

    public static function drivers(): iterable
    {
        yield 'cache apcu' => ['CACHE_DRIVER', 'apcu', 'CacheApcu\\ApcuCacheExtension', 'api-cache-apcu', ['prefix' => 'api:cache:']];
        yield 'cache redis' => ['CACHE_DRIVER', 'redis', 'CacheRedis\\RedisCacheExtension', 'api-cache-redis', ['host' => 'redis', 'port' => 6379, 'password' => null, 'database' => null, 'prefix' => 'api:cache:']];
        yield 'mysql' => ['DB_DRIVER', 'mysql', 'DatabaseMySql\\MySqlExtension', 'api-database-mysql', ['host' => 'mysql', 'port' => 3306, 'database' => 'app', 'username' => 'app', 'password' => '']];
        yield 'postgresql' => ['DB_DRIVER', 'postgresql', 'DatabasePostgreSql\\PostgreSqlExtension', 'api-database-postgresql', ['host' => 'postgresql', 'port' => 5432, 'database' => 'app', 'username' => 'app', 'password' => '']];
        yield 'stdout' => ['LOG_DRIVER', 'stdout', 'LogStdout\\StdoutLogExtension', 'api-log-stdout', ['stream' => 'stdout']];
        yield 'file' => ['LOG_DRIVER', 'file', 'LogFile\\FileLogExtension', 'api-log-file', ['path' => '/project/storage/logs/app.log']];
        yield 'mongodb' => ['LOG_DRIVER', 'mongodb', 'LogMongoDb\\MongoLogExtension', 'api-log-mongodb', ['uri' => null, 'host' => 'mongo', 'port' => '27017', 'database' => 'api_logs', 'collection' => 'logs', 'username' => null, 'password' => null]];
    }

    #[DataProvider('drivers')]
    public function testSelectedPackageMustBeInstalled(string $key, string $driver, string $class, string $package, array $expected): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Instale elavora/' . $package);
        EnvironmentExtensions::load('/project', [$key => $driver]);
    }

    #[DataProvider('drivers')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testOfficialDefaultsAndProjectOverrides(string $key, string $driver, string $class, string $package, array $expected): void
    {
        class_alias(ConfiguredExtensionSpy::class, 'Elavora\\Api\\Extension\\' . $class);
        $app = ApplicationBootstrap::create(
            '/project/',
            configure: static function (Application $app) use ($expected): void {
                self::assertSame($expected, (array) $app->container()->get('official.config'));
                $app->container()->instance('official.config', (object) ['custom' => true]);
            },
            environment: [$key => $driver],
        );
        self::assertSame(['custom' => true], (array) $app->container()->get('official.config'));
    }

    public function testUnsupportedDriversFailExplicitly(): void
    {
        foreach (['CACHE_DRIVER', 'DB_DRIVER', 'LOG_DRIVER'] as $key) {
            try {
                EnvironmentExtensions::load('/project', [$key => 'unsupported']);
                self::fail('Expected invalid driver rejection');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString($key . ' deve ser ', $exception->getMessage());
            }
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRejectsInstalledClassThatDoesNotImplementExtension(): void
    {
        class_alias(InvalidExtensionFixture::class, 'Elavora\\Api\\Extension\\CacheApcu\\ApcuCacheExtension');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('deve implementar Extension');
        EnvironmentExtensions::load('/project', ['CACHE_DRIVER' => 'apcu']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testExplicitCacheValuesArePreserved(): void
    {
        class_alias(ConfiguredExtensionSpy::class, 'Elavora\\Api\\Extension\\CacheApcu\\ApcuCacheExtension');
        $extensions = EnvironmentExtensions::load('/project', ['CACHE_DRIVER' => 'apcu', 'CACHE_TTL' => '0', 'CACHE_PREFIX' => 'custom:']);
        self::assertSame(['prefix' => 'custom:', 'ttl' => 0], $extensions[0]->config);
    }
}

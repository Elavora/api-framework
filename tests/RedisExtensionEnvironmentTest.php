<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\EnvironmentExtensions;
use Elavora\Api\Framework\Contracts\Extension;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class RedisExtensionConfigurationSpy implements Extension
{
    /** @var array<string, array<string, mixed>> */
    public static array $configs = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        self::$configs[(string) $config['prefix']] = $config;
    }

    public function register(Application $application): void {}
}

final class RedisExtensionEnvironmentTest extends TestCase
{
    /** @var array<string, string|false> */
    private array $previousEnvironment = [];

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'CACHE_DRIVER', 'CACHE_PREFIX', 'CACHE_TTL', 'QUEUE_PREFIX', 'QUEUE_VISIBILITY_TIMEOUT',
            'REDIS_HOST', 'REDIS_PORT', 'REDIS_PASSWORD', 'REDIS_DATABASE',
            'DB_DRIVER', 'LOG_DRIVER',
        ] as $variable) {
            $this->previousEnvironment[$variable] = getenv($variable);
            putenv($variable);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->previousEnvironment as $variable => $value) {
            putenv($value === false ? $variable : $variable . '=' . $value);
        }
        RedisExtensionConfigurationSpy::$configs = [];
        parent::tearDown();
    }
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testRedisCredentialsArePropagatedToCacheAndQueue(): void
    {
        self::assertTrue(class_alias(
            RedisExtensionConfigurationSpy::class,
            'Elavora\Api\Extension\CacheRedis\RedisCacheExtension',
        ));
        self::assertTrue(class_alias(
            RedisExtensionConfigurationSpy::class,
            'Elavora\Api\Extension\QueueRedis\RedisQueueExtension',
        ));

        putenv('CACHE_DRIVER=redis');
        putenv('CACHE_PREFIX=cache:test:');
        putenv('QUEUE_PREFIX=queue:test:');
        putenv('REDIS_PASSWORD=secret');
        putenv('REDIS_DATABASE=7');

        EnvironmentExtensions::load('/project');

        self::assertSame('secret', RedisExtensionConfigurationSpy::$configs['cache:test:']['password']);
        self::assertSame('7', RedisExtensionConfigurationSpy::$configs['cache:test:']['database']);
        self::assertSame('secret', RedisExtensionConfigurationSpy::$configs['queue:test:']['password']);
        self::assertSame('7', RedisExtensionConfigurationSpy::$configs['queue:test:']['database']);

        RedisExtensionConfigurationSpy::$configs = [];
        putenv('REDIS_PASSWORD=');
        putenv('REDIS_DATABASE=');

        EnvironmentExtensions::load('/project');

        self::assertNull(RedisExtensionConfigurationSpy::$configs['cache:test:']['password']);
        self::assertNull(RedisExtensionConfigurationSpy::$configs['cache:test:']['database']);
        self::assertNull(RedisExtensionConfigurationSpy::$configs['queue:test:']['password']);
        self::assertNull(RedisExtensionConfigurationSpy::$configs['queue:test:']['database']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testVisibilityTimeoutDefaultsTo300AndIsPassedAsInt(): void
    {
        self::assertTrue(class_alias(
            RedisExtensionConfigurationSpy::class,
            'Elavora\Api\Extension\QueueRedis\RedisQueueExtension',
        ));

        EnvironmentExtensions::load('/project');
        self::assertSame(300, RedisExtensionConfigurationSpy::$configs['api:queue:']['visibility_timeout']);

        putenv('QUEUE_VISIBILITY_TIMEOUT=');
        EnvironmentExtensions::load('/project');
        self::assertSame(300, RedisExtensionConfigurationSpy::$configs['api:queue:']['visibility_timeout']);

        putenv('QUEUE_VISIBILITY_TIMEOUT=3600');
        EnvironmentExtensions::load('/project');
        self::assertSame(3600, RedisExtensionConfigurationSpy::$configs['api:queue:']['visibility_timeout']);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    #[\PHPUnit\Framework\Attributes\DataProvider('invalidVisibilityTimeouts')]
    public function testInvalidVisibilityTimeoutThrows(string $value): void
    {
        self::assertTrue(class_alias(
            RedisExtensionConfigurationSpy::class,
            'Elavora\Api\Extension\QueueRedis\RedisQueueExtension',
        ));

        putenv('QUEUE_VISIBILITY_TIMEOUT=' . $value);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('QUEUE_VISIBILITY_TIMEOUT deve ser um inteiro maior que zero.');

        EnvironmentExtensions::load('/project');
    }

    /** @return array<string, array{string}> */
    public static function invalidVisibilityTimeouts(): array
    {
        return ['zero' => ['0'], 'negativo' => ['-5'], 'texto' => ['abc'], 'decimal' => ['1.5']];
    }
}

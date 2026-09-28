<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Bootstrap;

use Elavora\Api\Framework\Contracts\Extension;
use RuntimeException;

/** Defaults de ambiente dos modulos oficiais; nenhum pacote opcional e obrigatorio. */
final class EnvironmentExtensions
{
    /**
     * Um mapa explicito substitui o ambiente do processo, inclusive quando vazio.
     *
     * @param array<string, string>|null $environment
     * @return list<Extension>
     */
    public static function load(string $basePath, ?array $environment = null): array
    {
        $read = static fn (string $name): string|false => $environment === null
            ? getenv($name)
            : ($environment[$name] ?? false);

        /** @var list<Extension> $extensions */
        $extensions = [];

        /**
         * Pacotes opcionais nao entram nas dependencias do framework. A resolucao
         * dinamica evita referenciar classes ausentes durante o boot e a analise.
         *
         * @param array<string, mixed> $config
         */
        $createExtension = static function (string $class, string $missingMessage, array $config): Extension {
            if (!class_exists($class)) {
                throw new RuntimeException($missingMessage);
            }

            $extension = new $class($config);
            if (!$extension instanceof Extension) {
                throw new RuntimeException(sprintf('%s deve implementar Extension.', $class));
            }

            return $extension;
        };

        // Pacotes opcionais nao fazem parte do framework base. Instale apenas o pacote
        // usado pelo projeto e selecione o driver correspondente no ambiente.
        $cacheDriver = $read('CACHE_DRIVER') ?: '';
        if ($cacheDriver !== '' && !in_array($cacheDriver, ['apcu', 'redis'], true)) {
            throw new RuntimeException('CACHE_DRIVER deve ser apcu ou redis.');
        }

        $redisPassword = $read('REDIS_PASSWORD');
        $redisPassword = $redisPassword === false || $redisPassword === '' ? null : $redisPassword;
        $redisDatabase = $read('REDIS_DATABASE');
        $redisDatabase = $redisDatabase === false || $redisDatabase === '' ? null : $redisDatabase;

        if ($cacheDriver === 'redis') {
            $extensions[] = $createExtension(
                'Elavora\Api\Extension\CacheRedis\RedisCacheExtension',
                'Instale elavora/api-cache-redis para usar CACHE_DRIVER=redis.',
                [
                    'host' => $read('REDIS_HOST') ?: 'redis',
                    'port' => (int) ($read('REDIS_PORT') ?: 6379),
                    'password' => $redisPassword,
                    'database' => $redisDatabase,
                    'prefix' => $read('CACHE_PREFIX') ?: 'api:cache:',
                ]
            );
        }

        if ($cacheDriver === 'apcu') {
            $cacheConfig = [
                'prefix' => $read('CACHE_PREFIX') ?: 'api:cache:',
            ];
            $cacheTtl = $read('CACHE_TTL');
            if ($cacheTtl !== false && $cacheTtl !== '') {
                $cacheConfig['ttl'] = (int) $cacheTtl;
            }

            $extensions[] = $createExtension(
                'Elavora\Api\Extension\CacheApcu\ApcuCacheExtension',
                'Instale elavora/api-cache-apcu para usar CACHE_DRIVER=apcu.',
                $cacheConfig
            );
        }

        $redisQueueExtension = 'Elavora\Api\Extension\QueueRedis\RedisQueueExtension';
        if (class_exists($redisQueueExtension)) {
            $extensions[] = $createExtension(
                $redisQueueExtension,
                'Instale elavora/api-queue-redis para registrar a fila Redis.',
                [
                    'host' => $read('REDIS_HOST') ?: 'redis',
                    'port' => (int) ($read('REDIS_PORT') ?: 6379),
                    'password' => $redisPassword,
                    'database' => $redisDatabase,
                    'prefix' => $read('QUEUE_PREFIX') ?: 'api:queue:',
                ]
            );
        }

        $databaseDriver = $read('DB_DRIVER') ?: '';
        if ($databaseDriver !== '' && !in_array($databaseDriver, ['mysql', 'postgresql'], true)) {
            throw new RuntimeException('DB_DRIVER deve ser mysql ou postgresql.');
        }

        $databaseConfig = [
            'host' => $read('DB_HOST') ?: $databaseDriver,
            'port' => (int) ($read('DB_PORT') ?: ($databaseDriver === 'mysql' ? 3306 : 5432)),
            'database' => $read('DB_DATABASE') ?: 'app',
            'username' => $read('DB_USERNAME') ?: 'app',
            'password' => $read('DB_PASSWORD') ?: '',
        ];

        if ($databaseDriver === 'mysql') {
            $extensions[] = $createExtension(
                'Elavora\Api\Extension\DatabaseMySql\MySqlExtension',
                'Instale elavora/api-database-mysql para usar DB_DRIVER=mysql.',
                $databaseConfig
            );
        }

        if ($databaseDriver === 'postgresql') {
            $extensions[] = $createExtension(
                'Elavora\Api\Extension\DatabasePostgreSql\PostgreSqlExtension',
                'Instale elavora/api-database-postgresql para usar DB_DRIVER=postgresql.',
                $databaseConfig
            );
        }

        $logDriver = $read('LOG_DRIVER') ?: '';
        if ($logDriver !== '' && !in_array($logDriver, ['stdout', 'file', 'mongodb'], true)) {
            throw new RuntimeException('LOG_DRIVER deve ser stdout, file ou mongodb.');
        }

        if ($logDriver === 'stdout') {
            $extensions[] = $createExtension(
                'Elavora\Api\Extension\LogStdout\StdoutLogExtension',
                'Instale elavora/api-log-stdout para usar LOG_DRIVER=stdout.',
                [
                    'stream' => $read('LOG_STREAM') ?: 'stdout',
                ]
            );
        }

        if ($logDriver === 'file') {
            $extensions[] = $createExtension(
                'Elavora\Api\Extension\LogFile\FileLogExtension',
                'Instale elavora/api-log-file para usar LOG_DRIVER=file.',
                [
                    'path' => $read('LOG_FILE') ?: rtrim($basePath, '/\\') . '/storage/logs/app.log',
                ]
            );
        }

        if ($logDriver === 'mongodb') {
            $extensions[] = $createExtension(
                'Elavora\Api\Extension\LogMongoDb\MongoLogExtension',
                'Instale elavora/api-log-mongodb para usar LOG_DRIVER=mongodb.',
                [
                    'uri' => $read('MONGO_LOG_URI') ?: null,
                    'host' => $read('MONGO_LOG_HOST') ?: 'mongo',
                    'port' => $read('MONGO_LOG_PORT') ?: '27017',
                    'database' => $read('MONGO_LOG_DATABASE') ?: 'api_logs',
                    'collection' => $read('MONGO_LOG_COLLECTION') ?: 'logs',
                    'username' => $read('MONGO_LOG_USERNAME') ?: null,
                    'password' => $read('MONGO_LOG_PASSWORD') ?: null,
                ]
            );
        }

        return $extensions;
    }
}

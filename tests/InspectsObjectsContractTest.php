<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use DateTimeImmutable;
use Elavora\Api\Framework\Contracts\InspectsObjects;
use Elavora\Api\Framework\Contracts\Storage;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Contrato opcional InspectsObjects (exists e size).
 *
 * @see docs/USO.md#storage-e-inspectsobjects
 */
final class InspectsObjectsContractTest extends TestCase
{
    public function testIsAnInterfaceSeparateFromStorage(): void
    {
        $reflection = new ReflectionClass(InspectsObjects::class);

        self::assertTrue($reflection->isInterface());
        self::assertSame([], $reflection->getInterfaceNames());
        self::assertFalse(
            (new ReflectionClass(Storage::class))->hasMethod('exists'),
            'O contrato Storage não pode ganhar métodos novos (mudança incompatível).',
        );
    }

    public function testDefinesExistsAndSizeOnly(): void
    {
        $methods = array_map(
            static fn($method): string => $method->getName(),
            (new ReflectionClass(InspectsObjects::class))->getMethods(),
        );
        sort($methods);

        self::assertSame(['exists', 'size'], $methods);

        $exists = new \ReflectionMethod(InspectsObjects::class, 'exists');
        self::assertSame('bool', (string) $exists->getReturnType());

        $size = new \ReflectionMethod(InspectsObjects::class, 'size');
        self::assertSame('?int', (string) $size->getReturnType());
    }

    public function testStorageCanImplementBothContracts(): void
    {
        $storage = new class implements Storage, InspectsObjects {
            /** @var array<string, string> */
            private array $objects = ['reports/file.txt' => 'content', 'empty.txt' => ''];

            public function put(string $key, string $body, array $options = []): array
            {
                return [];
            }

            public function get(string $key, array $options = []): array
            {
                return [];
            }

            public function delete(string $key, array $options = []): array
            {
                return [];
            }

            public function temporaryUrl(
                string $key,
                ?DateTimeImmutable $expiresAt = null,
                array $options = [],
            ): string {
                return '';
            }

            public function exists(string $key): bool
            {
                return isset($this->objects[$key]);
            }

            public function size(string $key): ?int
            {
                return isset($this->objects[$key]) ? strlen($this->objects[$key]) : null;
            }
        };

        self::assertInstanceOf(Storage::class, $storage);
        self::assertInstanceOf(InspectsObjects::class, $storage);
        self::assertTrue($storage->exists('reports/file.txt'));
        self::assertSame(7, $storage->size('reports/file.txt'));
        self::assertSame(0, $storage->size('empty.txt'));
        self::assertFalse($storage->exists('missing.txt'));
        self::assertNull($storage->size('missing.txt'));
    }

    public function testPlainStorageDoesNotNeedToImplementIt(): void
    {
        $storage = new class implements Storage {
            public function put(string $key, string $body, array $options = []): array
            {
                return [];
            }

            public function get(string $key, array $options = []): array
            {
                return [];
            }

            public function delete(string $key, array $options = []): array
            {
                return [];
            }

            public function temporaryUrl(
                string $key,
                ?DateTimeImmutable $expiresAt = null,
                array $options = [],
            ): string {
                return '';
            }
        };

        self::assertNotInstanceOf(InspectsObjects::class, $storage);
    }
}

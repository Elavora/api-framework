<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * docs/EXEMPLOS.md, seção "Configuração".
 */
final class ExamplesConfigTest extends TestCase
{
    private static function root(): string
    {
        return dirname(__DIR__);
    }

    /**
     * @return array<string, mixed>
     */
    private static function composer(): array
    {
        return json_decode((string) file_get_contents(self::root() . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testAutoloadDevMapsAppToExamples(): void
    {
        $psr4 = self::composer()['autoload-dev']['psr-4'] ?? [];

        self::assertSame('examples/app/', $psr4['App\\'] ?? null);
        self::assertSame('tests/', $psr4['Elavora\\Api\\Framework\\Tests\\'] ?? null);
    }

    public function testAppNamespaceIsNotInProductionAutoload(): void
    {
        $autoload = self::composer()['autoload'] ?? [];

        foreach (['psr-4', 'psr-0'] as $type) {
            self::assertArrayNotHasKey('App\\', $autoload[$type] ?? [], $type);
        }
        foreach (['classmap', 'files'] as $type) {
            foreach ($autoload[$type] ?? [] as $path) {
                self::assertStringStartsNotWith('examples', ltrim((string) $path, './'), $type);
            }
        }
    }

    public function testPhpstanAnalysesExamplesBesidesSrc(): void
    {
        $neon = str_replace("\r\n", "\n", (string) file_get_contents(self::root() . '/phpstan.neon'));

        self::assertMatchesRegularExpression('/^\s+paths:\s*$/m', $neon);
        $paths = [];
        if (preg_match('/^(\s+)paths:\s*\n((?:\1\s+-\s*.+\n?)+)/m', $neon, $match) === 1) {
            preg_match_all('/-\s*(\S+)/', $match[2], $items);
            $paths = array_map(static fn(string $p): string => rtrim(trim($p, "'\""), '/'), $items[1]);
        }

        self::assertContains('src', $paths);
        self::assertContains('examples', $paths);
    }

    public function testNoExampleClassHasTheSameNameAsAFixture(): void
    {
        $examples = self::declaredClasses(self::root() . '/examples');
        $fixtures = self::declaredClasses(self::root() . '/tests/Fixtures');

        self::assertNotSame([], $examples, 'examples/ deveria declarar classes');
        self::assertSame([], array_values(array_intersect($examples, $fixtures)));
    }

    /**
     * @return list<string> nomes completos (minúsculos) declarados nos .php da pasta
     */
    private static function declaredClasses(string $dir): array
    {
        if (!is_dir($dir)) {
            return [];
        }

        $names = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $code = (string) file_get_contents($file->getPathname());
            $namespace = preg_match('/^namespace\s+([^;]+);/m', $code, $ns) === 1 ? $ns[1] . '\\' : '';
            preg_match_all(
                '/^\s*(?:(?:final|abstract|readonly)\s+)*(?:class|interface|trait|enum)\s+([A-Za-z_][A-Za-z0-9_]*)/m',
                $code,
                $matches,
            );
            foreach ($matches[1] as $class) {
                $names[] = strtolower($namespace . $class);
            }
        }

        sort($names);

        return array_values(array_unique($names));
    }
}

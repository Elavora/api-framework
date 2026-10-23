<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Sincronia entre docs/EXEMPLOS.md e examples/ (seção "Como os exemplos são testados").
 */
final class ExamplesDocSyncTest extends TestCase
{
    private const DOC = 'docs/EXEMPLOS.md';
    private const MARKER = '/^<!-- exemplo: (.+) -->$/';

    private static function root(): string
    {
        return dirname(__DIR__);
    }

    public function testDocBlocksMatchExampleFiles(): void
    {
        $markdown = (string) file_get_contents(self::root() . '/' . self::DOC);

        self::assertSame([], self::problems($markdown, self::root()));
    }

    public function testDocDocumentsTheEightExampleFiles(): void
    {
        $markdown = (string) file_get_contents(self::root() . '/' . self::DOC);

        self::assertSame([
            'examples/app/Attributes/Permission.php',
            'examples/app/DataTypes/ProjectCode.php',
            'examples/app/Extensions/InMemoryExtension.php',
            'examples/app/Http/Controller/TaskController.php',
            'examples/app/Http/Middleware/ApiVersionHeader.php',
            'examples/app/Support/InMemoryCacheStore.php',
            'examples/app/Support/InMemoryTransactionManager.php',
            'examples/bootstrap.php',
        ], self::sorted(array_keys(self::markers($markdown))));
    }

    public function testExampleFilesExist(): void
    {
        self::assertSame([
            'examples/app/Attributes/Permission.php',
            'examples/app/DataTypes/ProjectCode.php',
            'examples/app/Extensions/InMemoryExtension.php',
            'examples/app/Http/Controller/TaskController.php',
            'examples/app/Http/Middleware/ApiVersionHeader.php',
            'examples/app/Support/InMemoryCacheStore.php',
            'examples/app/Support/InMemoryTransactionManager.php',
            'examples/bootstrap.php',
        ], self::phpFiles(self::root()));
    }

    // ---------------------------------------------------------------
    // O próprio verificador, com os exemplos de falha da doc
    // ---------------------------------------------------------------

    public function testCheckerAcceptsSyncedBlock(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n\necho 1;\n"]);
        $doc = "# Doc\n\n<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n\necho 1;\n```\n";

        self::assertSame([], self::problems($doc, $root));
    }

    public function testCheckerNormalizesCrlfOnBothSides(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\r\n\r\necho 1;\r\n"]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\r\n```php\r\n<?php\r\n\r\necho 1;\r\n```\r\n";

        self::assertSame([], self::problems($doc, $root));
    }

    public function testCheckerReportsBlockThatDiffersFromFile(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n\necho 2;\n"]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n\necho 1;\n```\n";

        self::assertSame(['bloco difere do arquivo: examples/app/Foo.php'], self::problems($doc, $root));
    }

    public function testCheckerReportsMissingTrailingNewlineDifference(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n\necho 1;"]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n\necho 1;\n```\n";

        self::assertSame(['bloco difere do arquivo: examples/app/Foo.php'], self::problems($doc, $root));
    }

    public function testCheckerReportsFileWithoutMarker(): void
    {
        $root = self::fixtureRoot([
            'examples/app/Foo.php' => "<?php\n",
            'examples/app/Support/Clock.php' => "<?php\n",
        ]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n```\n";

        self::assertSame(['arquivo sem marcador: examples/app/Support/Clock.php'], self::problems($doc, $root));
    }

    public function testCheckerReportsBlockWithoutMarker(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n"]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n```\n\nTexto.\n\n```php\n<?php\n```\n";

        self::assertSame(['bloco sem marcador na linha 8'], self::problems($doc, $root));
    }

    public function testCheckerReportsMarkerToMissingFile(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n"]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n```\n"
            . "<!-- exemplo: examples/app/Nada.php -->\n```php\n<?php\n```\n";

        self::assertSame(['arquivo inexistente: examples/app/Nada.php'], self::problems($doc, $root));
    }

    public function testCheckerReportsFileWithTwoMarkers(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n"]);
        $doc = "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n```\n"
            . "<!-- exemplo: examples/app/Foo.php -->\n```php\n<?php\n```\n";

        self::assertSame(['arquivo com 2 marcadores: examples/app/Foo.php'], self::problems($doc, $root));
    }

    public function testInlineMarkerDoesNotCount(): void
    {
        $root = self::fixtureRoot(['examples/app/Foo.php' => "<?php\n"]);
        $doc = "Use `<!-- exemplo: examples/app/Foo.php -->` antes do bloco.\n";

        self::assertSame(['arquivo sem marcador: examples/app/Foo.php'], self::problems($doc, $root));
    }

    public function testBlocksWithoutPhpLanguageAreIgnored(): void
    {
        $root = self::fixtureRoot([]);
        $doc = "```\nexamples/\n└── bootstrap.php\n```\n\n```yaml\na: 1\n```\n";

        self::assertSame([], self::problems($doc, $root));
    }

    // ---------------------------------------------------------------

    /**
     * Confere as quatro regras da seção "Marcador" e devolve os problemas encontrados.
     *
     * @return list<string>
     */
    private static function problems(string $markdown, string $root): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
        $problems = [];
        $count = count($lines);

        for ($i = 0; $i < $count; $i++) {
            if ($lines[$i] !== '```php') {
                continue;
            }

            $block = [];
            $j = $i + 1;
            while ($j < $count && $lines[$j] !== '```') {
                $block[] = $lines[$j];
                $j++;
            }

            $previous = $i > 0 ? $lines[$i - 1] : '';
            if (preg_match(self::MARKER, $previous, $match) !== 1) {
                $problems[] = 'bloco sem marcador na linha ' . ($i + 1);
                $i = $j;
                continue;
            }

            $path = $match[1];
            $file = $root . '/' . $path;
            if (!is_file($file)) {
                $problems[] = 'arquivo inexistente: ' . $path;
                $i = $j;
                continue;
            }

            $expected = str_replace("\r\n", "\n", (string) file_get_contents($file));
            $actual = implode("\n", $block) . "\n";
            if ($actual !== $expected) {
                $problems[] = 'bloco difere do arquivo: ' . $path;
            }

            $i = $j;
        }

        $markers = self::markers($markdown);
        foreach (self::phpFiles($root) as $path) {
            $n = $markers[$path] ?? 0;
            if ($n === 0) {
                $problems[] = 'arquivo sem marcador: ' . $path;
            } elseif ($n > 1) {
                $problems[] = sprintf('arquivo com %d marcadores: %s', $n, $path);
            }
        }

        return $problems;
    }

    /**
     * @return array<string, int> caminho => quantidade de marcadores sozinhos na linha
     */
    private static function markers(string $markdown): array
    {
        $markers = [];
        foreach (explode("\n", str_replace("\r\n", "\n", $markdown)) as $line) {
            if (preg_match(self::MARKER, $line, $match) === 1) {
                $markers[$match[1]] = ($markers[$match[1]] ?? 0) + 1;
            }
        }

        return $markers;
    }

    /**
     * @return list<string> arquivos .php de examples/, relativos à raiz, com /
     */
    private static function phpFiles(string $root): array
    {
        $base = $root . '/examples';
        if (!is_dir($base)) {
            return [];
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $files[] = str_replace('\\', '/', $relative);
            }
        }

        return self::sorted($files);
    }

    /**
     * @param list<string> $list
     * @return list<string>
     */
    private static function sorted(array $list): array
    {
        sort($list, SORT_STRING);

        return $list;
    }

    /**
     * @param array<string, string> $files
     */
    private static function fixtureRoot(array $files): string
    {
        $root = sys_get_temp_dir() . '/elavora-examples-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        foreach ($files as $path => $content) {
            $full = $root . '/' . $path;
            if (!is_dir(dirname($full))) {
                mkdir(dirname($full), 0777, true);
            }
            file_put_contents($full, $content);
        }

        return $root;
    }
}

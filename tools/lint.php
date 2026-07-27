<?php

declare(strict_types=1);

$roots = [__DIR__ . '/../src', __DIR__ . '/../tests'];
$failed = false;

foreach ($roots as $root) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $command = escapeshellarg(PHP_BINARY)
            . ' -l '
            . escapeshellarg($file->getPathname());
        passthru($command, $exitCode);
        $failed = $failed || $exitCode !== 0;
    }
}

exit($failed ? 1 : 0);

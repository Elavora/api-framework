<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Bootstrap;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;

/** Monta a aplicacao sem conhecer classes ou arquivos do projeto consumidor. */
final class ApplicationBootstrap
{
    /**
     * Extensoes do ambiente sao registradas antes das extensoes do projeto.
     * O callback final pode registrar rotas, middlewares e substituir servicos.
     *
     * @param iterable<Extension> $extensions
     * @param callable(Application): void|null $configure
     * @param array<string, string>|null $environment
     */
    public static function create(
        string $basePath,
        iterable $extensions = [],
        ?callable $configure = null,
        ?array $environment = null,
        bool $loadEnvironmentExtensions = true,
    ): Application {
        $debug = $environment === null ? getenv('APP_DEBUG') : ($environment['APP_DEBUG'] ?? false);
        $app = Application::create(debug: filter_var($debug ?: false, FILTER_VALIDATE_BOOLEAN));

        if ($loadEnvironmentExtensions) {
            foreach (EnvironmentExtensions::load($basePath, $environment) as $extension) {
                $app->extend($extension);
            }
        }
        foreach ($extensions as $extension) {
            $app->extend($extension);
        }
        if ($configure !== null) {
            $configure($app);
        }

        return $app;
    }
}

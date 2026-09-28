<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use PHPUnit\Framework\TestCase;

final class ApplicationBootstrapTest extends TestCase
{
    public function testProjectCanRegisterExtensionsRoutesAndOverrideServices(): void
    {
        $extension = new class implements Extension {
            public function register(Application $application): void
            {
                $application->container()->instance('message', (object) ['text' => 'extension']);
            }
        };
        $app = ApplicationBootstrap::create(
            basePath: '/project',
            extensions: [$extension],
            configure: static function (Application $app): void {
                self::assertSame('extension', $app->container()->get('message')->text);
                $app->container()->instance('message', (object) ['text' => 'project']);
                $app->get('/custom', static fn (): Response => Response::text($app->container()->get('message')->text));
            },
            environment: [],
        );
        self::assertSame('project', $app->handle(new Request('GET', '/custom'))->body());
    }

    public function testExplicitEnvironmentIsIsolatedFromProcessAndControlsDebug(): void
    {
        $previous = getenv('APP_DEBUG');
        putenv('APP_DEBUG=true');
        try {
            foreach ([null, [], ['APP_DEBUG' => 'true'], ['APP_DEBUG' => 'false']] as $environment) {
                $app = ApplicationBootstrap::create('/project', environment: $environment, loadEnvironmentExtensions: false);
                $app->get('/error', static function (): never {
                    throw new \RuntimeException('private detail');
                });
                $response = $app->handle(new Request('GET', '/error'));
                self::assertSame(500, $response->status());
                self::assertSame(
                    $environment === null || ($environment['APP_DEBUG'] ?? '') === 'true',
                    str_contains($response->body(), 'private detail'),
                );
            }
        } finally {
            putenv($previous === false ? 'APP_DEBUG' : 'APP_DEBUG=' . $previous);
        }
    }

    public function testCanDisableOfficialExtensionsForCustomConfiguration(): void
    {
        $app = ApplicationBootstrap::create(
            basePath: '/project',
            environment: ['CACHE_DRIVER' => 'custom'],
            loadEnvironmentExtensions: false,
        );
        self::assertSame(404, $app->handle(new Request('GET', '/missing'))->status());
    }

    public function testLegacyFactoryRemainsAvailable(): void
    {
        $app = Application::create();
        $app->get('/legacy', static fn (): Response => Response::text('ok'));
        self::assertSame('ok', $app->handle(new Request('GET', '/legacy'))->body());
    }
}

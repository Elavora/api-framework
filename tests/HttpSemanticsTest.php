<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Elavora\Api\Framework\Http\ResponseEmitter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HttpSemanticsTest extends TestCase
{
    public function testHeadFallsBackToGetWithoutResponseBody(): void
    {
        $application = Application::create();
        $application->get(
            '/health',
            fn (): Response => Response::json(['status' => 'healthy'])
                ->withHeader('X-Resource', 'health')
        );

        $response = $application->handle(new Request(method: 'HEAD', path: '/health'));

        self::assertSame(200, $response->status());
        self::assertSame('health', $response->headers()['X-Resource']);
        self::assertSame('application/json; charset=utf-8', $response->headers()['Content-Type']);
        self::assertSame('', $response->body());
    }

    public function testExplicitHeadRouteHasPriorityOverGetFallback(): void
    {
        $application = Application::create();
        $application->get('/resource', fn (): Response => Response::text('get'));
        $application->route(
            'HEAD',
            '/resource',
            fn (): Response => Response::text('head', status: 202)->withHeader('X-Route', 'head')
        );

        $response = $application->handle(new Request(method: 'HEAD', path: '/resource'));

        self::assertSame(202, $response->status());
        self::assertSame('head', $response->headers()['X-Route']);
        self::assertSame('', $response->body());
    }

    public function testAllowIncludesHeadWhenGetRouteExists(): void
    {
        $application = Application::create();
        $application->get('/health', fn (): Response => Response::text('ok'));

        $response = $application->handle(new Request(method: 'POST', path: '/health'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    #[DataProvider('statusWithoutBodyProvider')]
    public function testEmitterDoesNotOutputBodyForStatusThatForbidsIt(int $status): void
    {
        $emitter = new ResponseEmitter();

        ob_start();
        $emitter->emit(new Response(body: 'must not be emitted', status: $status));
        $output = ob_get_clean();

        self::assertSame('', $output);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function statusWithoutBodyProvider(): iterable
    {
        yield 'informational' => [103];
        yield 'no content' => [204];
        yield 'not modified' => [304];
    }
}

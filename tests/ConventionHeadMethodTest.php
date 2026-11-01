<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use App\Http\Controller\HeadProbeController;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/App/Http/Controller/HeadProbeController.php';

/**
 * Spec: docs/ROTEAMENTO.md, subsecao "HEAD e #[Method]".
 */
final class ConventionHeadMethodTest extends TestCase
{
    public function testHeadOnGetActionRunsActionWithSameStatusAndHeadersAndEmptyBody(): void
    {
        $application = Application::create();

        $get = $application->handle(new Request(method: 'GET', path: '/headProbe'));
        HeadProbeController::$executions = 0;
        $head = $application->handle(new Request(method: 'HEAD', path: '/headProbe'));

        self::assertSame(200, $get->status());
        self::assertNotSame('', $get->body());
        self::assertSame(200, $head->status());
        self::assertSame('', $head->body());
        self::assertSame('task', $head->headers()['X-Resource']);
        self::assertSame($get->headers()['Content-Type'], $head->headers()['Content-Type']);
        self::assertSame(1, HeadProbeController::$executions);
    }

    public function testHeadOnExplicitGetAndHeadListWorksTheSame(): void
    {
        $head = Application::create()->handle(new Request(method: 'HEAD', path: '/headProbe/explicit'));

        self::assertSame(200, $head->status());
        self::assertSame('', $head->body());
        self::assertSame('explicit', $head->headers()['X-Resource']);
    }

    public function testHeadOnPostOnlyActionIsMethodNotAllowed(): void
    {
        $response = Application::create()->handle(new Request(method: 'HEAD', path: '/headProbe/store'));

        self::assertSame(405, $response->status());
        self::assertSame('POST', $response->headers()['Allow']);
    }

    public function testMethodNotAllowedAllowListsHeadAfterGet(): void
    {
        $response = Application::create()->handle(new Request(method: 'POST', path: '/headProbe'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    public function testOptionsAllowListsHeadAfterGet(): void
    {
        $response = Application::create()->handle(new Request(method: 'OPTIONS', path: '/headProbe'));

        self::assertSame(200, $response->status());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    public function testHeadOnPathRefusedByConventionStaysNotFound(): void
    {
        $application = Application::create();

        self::assertSame(404, $application->handle(new Request(method: 'HEAD', path: '/headProbe/missing'))->status());
        self::assertSame(404, $application->handle(new Request(method: 'HEAD', path: '/headProbe/index/extra'))->status());
    }
}

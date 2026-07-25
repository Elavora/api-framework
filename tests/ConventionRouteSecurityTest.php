<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use App\Http\Controller\SecuredController;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/App/Http/Controller/SecuredController.php';

final class ConventionRouteSecurityTest extends TestCase
{
    public function testDispatchesOnlyActionWithExplicitOptIn(): void
    {
        $application = Application::create();

        $response = $application->handle(new Request(method: 'GET', path: '/secured/exposed'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"action":"exposed"}', $response->body());
    }

    public function testDoesNotExecutePublicMethodWithoutOptIn(): void
    {
        $application = Application::create();

        $response = $application->handle(new Request(method: 'POST', path: '/secured/helper'));

        self::assertSame(404, $response->status());
    }

    #[DataProvider('inaccessibleActions')]
    public function testDoesNotExposeInaccessibleAction(string $path): void
    {
        $application = Application::create();

        $response = $application->handle(new Request(method: 'GET', path: $path));

        self::assertSame(404, $response->status());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function inaccessibleActions(): iterable
    {
        yield 'inherited action' => ['/secured/inherited'];
        yield 'static action' => ['/secured/staticAction'];
        yield 'protected action' => ['/secured/protectedAction'];
        yield 'constructor' => ['/secured/__construct'];
        yield 'destructor' => ['/secured/__destruct'];
    }

    public function testExplicitRouteDoesNotRequireActionAttribute(): void
    {
        $application = Application::create();
        $application->get('/explicit-helper', [SecuredController::class, 'helper']);

        $response = $application->handle(new Request(method: 'GET', path: '/explicit-helper'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"action":"helper"}', $response->body());
    }

    public function testOptionsReturnsConventionMetadataWithoutExecutingAction(): void
    {
        SecuredController::$executions = 0;
        SecuredController::$instances = 0;
        $application = Application::create();

        $response = $application->handle(new Request(method: 'OPTIONS', path: '/secured/mutating'));

        self::assertSame(200, $response->status());
        self::assertSame('POST', $response->headers()['Allow']);
        self::assertJsonStringEqualsJsonString(
            '{"attributes":{"methods":["POST"],"summary":"Executa operacao segura"}}',
            $response->body()
        );
        self::assertSame(0, SecuredController::$executions);
        self::assertSame(0, SecuredController::$instances);
    }

    public function testOptionsReturnsNotFoundForMissingConventionAction(): void
    {
        $application = Application::create();

        $response = $application->handle(new Request(method: 'OPTIONS', path: '/secured/missing'));

        self::assertSame(404, $response->status());
    }

    public function testExplicitOptionsRouteKeepsCurrentBehavior(): void
    {
        $application = Application::create();
        $application->route(
            'OPTIONS',
            '/explicit-options',
            fn (): Response => Response::json(['explicit' => true])
        );

        $response = $application->handle(new Request(method: 'OPTIONS', path: '/explicit-options'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"explicit":true}', $response->body());
    }
}

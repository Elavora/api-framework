<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use App\Http\Controller\QueryProbeController;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Http\HttpMethod;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/App/Http/Controller/QueryProbeController.php';

/**
 * Spec: docs/ROTEAMENTO.md, secao "Metodo QUERY".
 */
final class QueryMethodTest extends TestCase
{
    private array $server;
    private array $get;
    private array $post;

    protected function setUp(): void
    {
        $this->server = $_SERVER;
        $this->get = $_GET;
        $this->post = $_POST;
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        $_GET = $this->get;
        $_POST = $this->post;
    }

    public function testQueryIsAValidHttpMethodAndUnknownOnesStayInvalid(): void
    {
        self::assertSame(HttpMethod::Query, HttpMethod::fromValue('query'));
        self::assertSame('QUERY', (new Request(method: 'QUERY', path: '/x'))->method());

        $this->expectException(InvalidArgumentException::class);
        HttpMethod::fromValue('PROPFIND');
    }

    public function testShortcutRegistersQueryRoute(): void
    {
        $application = Application::create();
        $application->query('/search', fn(): Response => Response::json(['ok' => true]));

        $response = $application->handle(new Request(method: 'QUERY', path: '/search'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"ok":true}', $response->body());
    }

    public function testRouteAcceptsQueryAsStringOrEnum(): void
    {
        $application = Application::create();
        $application->route('QUERY', '/a', fn(): Response => Response::json(['a' => 1]));
        $application->route(HttpMethod::Query, '/b', fn(): Response => Response::json(['b' => 1]));

        self::assertSame(200, $application->handle(new Request(method: 'QUERY', path: '/a'))->status());
        self::assertSame(200, $application->handle(new Request(method: 'QUERY', path: '/b'))->status());
    }

    public function testRequestFromGlobalsReadsJsonBodyOfQuery(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'QUERY', 'REQUEST_URI' => '/search?page=2'];
        $_GET = ['page' => '2'];
        $_POST = [];

        $application = Application::create();
        $application->query('/search', static fn(Request $request): Response => Response::json([
            'filter' => $request->input('filter'),
            'page' => $request->query('page'),
        ]));

        $response = $application->handle(Request::fromGlobals('{"filter":"abc"}'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"filter":"abc","page":"2"}', $response->body());
    }

    public function testMalformedJsonBodyOfQueryIsBadRequest(): void
    {
        $_SERVER = ['REQUEST_METHOD' => 'QUERY', 'REQUEST_URI' => '/search'];
        $_GET = [];
        $_POST = [];

        $application = Application::create();
        $application->query('/search', fn(): Response => Response::json([]));

        self::assertSame(400, $application->handle(Request::fromGlobals('{"filter":'))->status());
    }

    public function testExplicitRouteOtherMethodAnswers405WithAllowListingQuery(): void
    {
        $application = Application::create();
        $application->get('/search', fn(): Response => Response::json([]));
        $application->query('/search', fn(): Response => Response::json([]));

        $response = $application->handle(new Request(method: 'POST', path: '/search'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD, QUERY', $response->headers()['Allow']);
    }

    public function testQueryOnPathWithoutQueryRouteAnswers405(): void
    {
        $application = Application::create();
        $application->get('/search', fn(): Response => Response::json([]));

        $response = $application->handle(new Request(method: 'QUERY', path: '/search'));

        self::assertSame(405, $response->status());
        self::assertSame('GET, HEAD', $response->headers()['Allow']);
    }

    public function testOptionsListsQueryInMethodsOfTheAction(): void
    {
        $application = Application::create();
        $application->route('QUERY', '/q', [QueryProbeController::class, 'search']);

        $response = $application->handle(new Request(method: 'OPTIONS', path: '/q'));

        self::assertSame(200, $response->status());
        $payload = json_decode($response->body(), true);
        self::assertSame(['QUERY'], $payload['attributes']['methods']);
    }

    public function testMethodAttributeAcceptsQuery(): void
    {
        $attribute = new Method('QUERY');

        self::assertNull($attribute->validate(new Request(method: 'QUERY', path: '/x')));
        $response = $attribute->validate(new Request(method: 'POST', path: '/x'));
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('QUERY', $response->headers()['Allow']);
        self::assertSame(['methods' => ['QUERY']], $attribute->options());
    }

    public function testConventionActionWithMethodQueryValidatesBodyAndQueryString(): void
    {
        $application = Application::create();

        $ok = $application->handle(new Request(
            method: 'QUERY',
            path: '/queryProbe/search',
            query: ['page' => '1'],
            body: ['filter' => 'x'],
        ));
        $missingField = $application->handle(new Request(
            method: 'QUERY',
            path: '/queryProbe/search',
            query: ['page' => '1'],
        ));
        $missingParam = $application->handle(new Request(
            method: 'QUERY',
            path: '/queryProbe/search',
            body: ['filter' => 'x'],
        ));

        self::assertSame(200, $ok->status());
        self::assertJsonStringEqualsJsonString('{"filter":"x","page":"1"}', $ok->body());
        self::assertGreaterThanOrEqual(400, $missingField->status());
        self::assertStringContainsString('filter', $missingField->body());
        self::assertGreaterThanOrEqual(400, $missingParam->status());
        self::assertStringContainsString('page', $missingParam->body());
    }

    public function testConventionActionWithMethodQueryRejectsPostWithAllow(): void
    {
        $response = Application::create()->handle(new Request(method: 'POST', path: '/queryProbe/search'));

        self::assertSame(405, $response->status());
        self::assertSame('QUERY', $response->headers()['Allow']);
    }

    public function testConventionActionListingGetAndQueryAcceptsBoth(): void
    {
        $application = Application::create();

        self::assertSame(200, $application->handle(new Request(method: 'QUERY', path: '/queryProbe/both'))->status());
        self::assertSame(200, $application->handle(new Request(method: 'GET', path: '/queryProbe/both'))->status());
        $options = $application->handle(new Request(method: 'OPTIONS', path: '/queryProbe/both'));
        self::assertSame('GET, HEAD, QUERY', $options->headers()['Allow']);
    }

    public function testConventionActionWithoutMethodRefusesQueryButKeepsOtherMethods(): void
    {
        $application = Application::create();

        $query = $application->handle(new Request(method: 'QUERY', path: '/queryProbe/open'));

        self::assertSame(405, $query->status());
        self::assertSame('GET, POST, PUT, PATCH, DELETE, HEAD', $query->headers()['Allow']);
        self::assertSame(200, $application->handle(new Request(method: 'GET', path: '/queryProbe/open'))->status());
        self::assertSame(200, $application->handle(new Request(method: 'POST', path: '/queryProbe/open'))->status());
    }

    public function testGetAndPostRoutesKeepWorking(): void
    {
        $application = Application::create();
        $application->get('/r', fn(): Response => Response::json(['m' => 'get']));
        $application->post('/r', fn(): Response => Response::json(['m' => 'post']));

        self::assertSame('{"m":"get"}', $application->handle(new Request(method: 'GET', path: '/r'))->body());
        self::assertSame('{"m":"post"}', $application->handle(new Request(method: 'POST', path: '/r'))->body());
        $put = $application->handle(new Request(method: 'PUT', path: '/r'));
        self::assertSame('GET, HEAD, POST', $put->headers()['Allow']);
    }
}

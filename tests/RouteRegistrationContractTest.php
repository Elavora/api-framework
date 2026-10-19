<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use App\Http\Controller\UserController;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\HttpMethod;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

require_once __DIR__ . '/Fixtures/App/Http/Controller/RoutingFixtures.php';

/**
 * Contrato de docs/ROTEAMENTO.md, seção "Registro de rotas explícitas".
 */
final class RouteRegistrationContractTest extends TestCase
{
    #[DataProvider('acceptedHandlers')]
    public function testAcceptsHandler(mixed $handler): void
    {
        $application = Application::create();

        $application->get('/accepted', $handler);

        $this->addToAssertionCount(1);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function acceptedHandlers(): iterable
    {
        yield 'Closure' => [static fn(Request $r): array => []];
        yield 'objeto com __invoke' => [new HealthCheck()];
        yield '[Classe::class, metodo]' => [[UserController::class, 'show']];
        yield 'sem #[Action]' => [[UserController::class, 'helper']];
        yield 'herdado' => [[UserController::class, 'inherited']];
        yield 'de trait' => [[UserController::class, 'export']];
        yield '[objeto, metodo]' => [[new UserController(), 'show']];
        yield 'classe fora do namespace dos controllers' => [[HealthCheck::class, 'status']];
    }

    #[DataProvider('rejectedHandlers')]
    public function testRejectsHandlerAtRegistration(mixed $handler): void
    {
        $application = Application::create();

        $this->expectException(InvalidArgumentException::class);

        $application->get('/users', $handler);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function rejectedHandlers(): iterable
    {
        yield 'nome de função em texto' => ['phpinfo'];
        yield 'Classe::metodo em texto' => ['App\\Http\\Controller\\UserController::show'];
        yield 'falta o método' => [[UserController::class]];
        yield 'três itens' => [[UserController::class, 'show', 'extra']];
        yield 'chaves erradas' => [['class' => UserController::class, 'method' => 'show']];
        yield 'chaves 1 e 2' => [[1 => UserController::class, 2 => 'show']];
        yield 'método não é texto' => [[UserController::class, 42]];
        yield 'classe não existe' => [['App\\Http\\Controller\\MissingController', 'show']];
        yield 'método não existe' => [[UserController::class, 'missing']];
        yield 'privado' => [[UserController::class, 'secret']];
        yield 'protegido' => [[UserController::class, 'audit']];
        yield 'estático' => [[UserController::class, 'build']];
        yield 'construtor' => [[UserController::class, '__construct']];
        yield '__invoke' => [[UserController::class, '__invoke']];
        yield 'nome sem o mesmo caso' => [[UserController::class, 'listactive']];
        yield '[objeto, privado]' => [[new UserController(), 'secret']];
        yield 'null' => [null];
        yield 'inteiro' => [42];
        yield 'booleano' => [true];
        yield 'stdClass' => [new stdClass()];
    }

    #[DataProvider('registrationShortcuts')]
    public function testShortcutsAndRouteForwardTheException(string $shortcut): void
    {
        $application = Application::create();

        $this->expectException(InvalidArgumentException::class);

        match ($shortcut) {
            'route' => $application->route('GET', '/users', [UserController::class, 'secret']),
            'route-enum' => $application->route(HttpMethod::Get, '/users', [UserController::class, 'secret']),
            'get' => $application->get('/users', [UserController::class, 'secret']),
            'post' => $application->post('/users', [UserController::class, 'secret']),
            'put' => $application->put('/users', [UserController::class, 'secret']),
            'patch' => $application->patch('/users', [UserController::class, 'secret']),
            'delete' => $application->delete('/users', [UserController::class, 'secret']),
        };
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function registrationShortcuts(): iterable
    {
        foreach (['route', 'route-enum', 'get', 'post', 'put', 'patch', 'delete'] as $shortcut) {
            yield $shortcut => [$shortcut];
        }
    }

    public function testExceptionMessageNamesHttpMethodAndPath(): void
    {
        $application = Application::create();

        try {
            $application->get('/users', [UserController::class, 'secret']);
            self::fail('InvalidArgumentException esperada');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('GET', $exception->getMessage());
            self::assertStringContainsString('/users', $exception->getMessage());
        }
    }

    public function testRefusedHandlerDoesNotRegisterRoute(): void
    {
        $application = Application::create();

        try {
            $application->get('/user/show', [UserController::class, 'secret']);
        } catch (InvalidArgumentException) {
        }

        // Sem a rota explícita, a request segue para a convenção e executa UserController::show.
        $response = $application->handle(new Request(method: 'GET', path: '/user/show'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"id":1}', $response->body());
    }

    public function testRefusedHandlerLeavesExistingRoutesUntouched(): void
    {
        $application = Application::create();
        $application->get('/users', static fn(): Response => Response::json(['ok' => true]));

        try {
            $application->post('/users', 'phpinfo');
        } catch (InvalidArgumentException) {
        }

        self::assertSame(200, $application->handle(new Request(method: 'GET', path: '/users'))->status());
        self::assertSame(405, $application->handle(new Request(method: 'POST', path: '/users'))->status());
    }

    public function testExplicitRouteDoesNotRequireActionAttributeAndRunsInheritedMethod(): void
    {
        $application = Application::create();
        $application->get('/explicit-inherited', [UserController::class, 'inherited']);
        $application->get('/explicit-export', [UserController::class, 'export']);

        $inherited = $application->handle(new Request(method: 'GET', path: '/explicit-inherited'));
        $export = $application->handle(new Request(method: 'GET', path: '/explicit-export'));

        self::assertSame(200, $inherited->status());
        self::assertJsonStringEqualsJsonString('{"action":"inherited"}', $inherited->body());
        self::assertSame(200, $export->status());
        self::assertJsonStringEqualsJsonString('{"action":"export"}', $export->body());
    }

    public function testInvokableObjectHandlerRuns(): void
    {
        $application = Application::create();
        $application->get('/health-check', new HealthCheck());

        $response = $application->handle(new Request(method: 'GET', path: '/health-check'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"status":"ok"}', $response->body());
    }

    public function testRegistrationDoesNotCheckWhetherClassCanBeCreated(): void
    {
        $application = Application::create();

        $application->get('/service', ['App\\Http\\Controller\\ServiceController', 'show']);

        $this->addToAssertionCount(1);
    }
}

final class HealthCheck
{
    public function __invoke(Request $request): Response
    {
        return Response::json(['status' => 'ok']);
    }

    public function status(Request $request): Response
    {
        return Response::json(['status' => 'ok']);
    }
}

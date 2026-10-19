<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use App\Http\Controller\ExternalChildController;
use App\Http\Controller\Mailer;
use App\Http\Controller\PrivateController;
use App\Http\Controller\Report2024Controller;
use App\Http\Controller\SecuredController;
use App\Http\Controller\ServiceController;
use App\Http\Controller\UseradminController;
use App\Http\Controller\UserController;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\LogWriter;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Logging\Logger;
use Elavora\Api\Framework\Routing\ConventionRouteResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/Fixtures/App/Http/Controller/RoutingFixtures.php';
require_once __DIR__ . '/Fixtures/App/Http/Controller/SecuredController.php';

/**
 * Contrato de docs/ROTEAMENTO.md, seção "Roteamento por convenção".
 */
final class ConventionRouteContractTest extends TestCase
{
    private const LONG_64 = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789ab';

    protected function setUp(): void
    {
        UserController::$instances = 0;
        UserController::$executions = 0;
        PrivateController::$instances = 0;
    }

    // ---------------------------------------------------------------
    // ConventionRouteResolver: API pública
    // ---------------------------------------------------------------

    public function testResolverReturnsClassAndActionWhenAllRulesPass(): void
    {
        $resolver = new ConventionRouteResolver();

        self::assertSame(
            [UserController::class, 'show'],
            $resolver->resolve(new Request(method: 'GET', path: '/user/show')),
        );
    }

    public function testResolverDoesNotCreateClassNorCallAction(): void
    {
        $resolver = new ConventionRouteResolver();

        $resolver->resolve(new Request(method: 'GET', path: '/user/show'));

        self::assertSame(0, UserController::$instances);
        self::assertSame(0, UserController::$executions);
    }

    public function testResolverAcceptsOptionalContainerAsFourthParameter(): void
    {
        $container = Application::create()->container();
        $resolver = new ConventionRouteResolver('App\\Http\\Controller', 'Controller', 'index', $container);

        self::assertSame(
            [UserController::class, 'show'],
            $resolver->resolve(new Request(method: 'GET', path: '/user/show')),
        );
    }

    public function testResolverUsesConfiguredDefaultAction(): void
    {
        $resolver = new ConventionRouteResolver('App\\Http\\Controller', 'Controller', 'show');

        self::assertSame(
            [UserController::class, 'show'],
            $resolver->resolve(new Request(method: 'GET', path: '/user')),
        );
    }

    // ---------------------------------------------------------------
    // Regras do path
    // ---------------------------------------------------------------

    /**
     * @param array{class-string, string} $expected
     */
    #[DataProvider('resolvedPaths')]
    public function testResolvesValidPaths(string $path, array $expected): void
    {
        $resolver = new ConventionRouteResolver();

        self::assertSame($expected, $resolver->resolve(new Request(method: 'GET', path: $path)));
    }

    /**
     * @return iterable<string, array{string, array{class-string, string}}>
     */
    public static function resolvedPaths(): iterable
    {
        yield '/user/show' => ['/user/show', [UserController::class, 'show']];
        yield '/user/show/ (barra final ignorada)' => ['/user/show/', [UserController::class, 'show']];
        yield '/user/listActive' => ['/user/listActive', [UserController::class, 'listActive']];
        yield '/report2024/show' => ['/report2024/show', [Report2024Controller::class, 'show']];
        yield '/useradmin/show (nome exato)' => ['/useradmin/show', [UseradminController::class, 'show']];
        yield 'segmento com 64 caracteres' => ['/user/' . self::LONG_64, [UserController::class, self::LONG_64]];
    }

    #[DataProvider('invalidPaths')]
    public function testRejectsInvalidPathWithInvalidPathReason(string $path): void
    {
        [$resolver, $writer] = $this->resolverWithLogger();

        self::assertNull($resolver->resolve(new Request(method: 'GET', path: $path)));
        self::assertSame(['invalid_path'], self::reasons($writer), $path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPaths(): iterable
    {
        yield '/ (nenhum segmento)' => ['/'];
        yield 'três segmentos' => ['/user/show/1'];
        yield 'segmento vazio' => ['/user//show'];
        yield 'controller com maiúscula' => ['/User/show'];
        yield 'action com maiúscula' => ['/user/Show'];
        yield 'action toda maiúscula' => ['/user/SHOW'];
        yield 'underscore' => ['/user_admin/show'];
        yield 'hífen' => ['/user-admin/show'];
        yield '__construct' => ['/user/__construct'];
        yield '__invoke' => ['/user/__invoke'];
        yield '.. no início' => ['/../user/show'];
        yield '.. como action' => ['/user/..'];
        yield 'ponto' => ['/user/./show'];
        yield '%5C codificado' => ['/admin%5Cuser/show'];
        yield '%2F codificado' => ['/user%2Fshow'];
        yield '%2E codificado' => ['/user/%2E%2E'];
        yield 'barra invertida' => ['/admin\\user/show'];
        yield 'começa com número' => ['/2fa/show'];
        yield 'letra com acento' => ['/usuário/show'];
        yield 'espaço' => ['/user/sh ow'];
        yield 'segmento com 65 caracteres' => ['/user/' . self::LONG_64 . 'c'];
        yield 'controller com 65 caracteres' => ['/' . self::LONG_64 . 'c/show'];
    }

    #[DataProvider('pathsThatPassPathRules')]
    public function testPathsThatPassPathRulesFollowToClassRules(string $path): void
    {
        [$resolver, $writer] = $this->resolverWithLogger();

        $resolver->resolve(new Request(method: 'GET', path: $path));

        self::assertNotContains('invalid_path', self::reasons($writer), $path);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pathsThatPassPathRules(): iterable
    {
        yield '/user (action padrão)' => ['/user'];
        yield '/report/show (classe ausente)' => ['/report/show'];
        yield '/userAdmin/show' => ['/userAdmin/show'];
        yield '/user/listactive' => ['/user/listactive'];
    }

    public function testUppercaseVariantsDoNotReachTheSameAction(): void
    {
        $application = Application::create();

        self::assertSame(200, $application->handle(new Request(method: 'GET', path: '/user/show'))->status());
        self::assertSame(404, $application->handle(new Request(method: 'GET', path: '/User/show'))->status());
        self::assertSame(404, $application->handle(new Request(method: 'GET', path: '/user/SHOW'))->status());
    }

    public function testEmptySegmentPathIsNotFoundInApplication(): void
    {
        $response = Application::create()->handle(new Request(method: 'GET', path: '/user//show'));

        self::assertSame(404, $response->status());
        self::assertSame(0, UserController::$executions);
    }

    // ---------------------------------------------------------------
    // Regras da classe
    // ---------------------------------------------------------------

    /**
     * @param 'class_not_found'|'class_not_instantiable' $reason
     */
    #[DataProvider('rejectedClasses')]
    public function testRejectsClass(string $path, string $reason): void
    {
        [$resolver, $writer] = $this->resolverWithLogger();

        self::assertNull($resolver->resolve(new Request(method: 'GET', path: $path)));
        self::assertSame([$reason], self::reasons($writer), $path);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedClasses(): iterable
    {
        yield 'classe ausente' => ['/report/show', 'class_not_found'];
        yield 'nome sem o mesmo caso (UseradminController)' => ['/userAdmin/show', 'class_not_found'];
        yield 'abstrata' => ['/base/show', 'class_not_instantiable'];
        yield 'interface' => ['/audit/show', 'class_not_instantiable'];
        yield 'trait' => ['/shared/show', 'class_not_instantiable'];
        yield 'enum' => ['/status/show', 'class_not_instantiable'];
        yield 'construtor privado' => ['/private/show', 'class_not_instantiable'];
        yield 'construtor com dependência fora do container' => ['/service/show', 'class_not_instantiable'];
    }

    public function testClassWithRequiredDependencyResolvesWhenRegisteredInContainer(): void
    {
        $container = Application::create()->container();
        $container->instance(ServiceController::class, new ServiceController(new Mailer()));
        $resolver = new ConventionRouteResolver(container: $container);

        self::assertSame(
            [ServiceController::class, 'show'],
            $resolver->resolve(new Request(method: 'GET', path: '/service/show')),
        );
    }

    public function testClassWithRequiredDependencyIsRejectedWithoutContainer(): void
    {
        $resolver = new ConventionRouteResolver();

        self::assertNull($resolver->resolve(new Request(method: 'GET', path: '/service/show')));
    }

    public function testApplicationRunsControllerRegisteredInContainer(): void
    {
        $application = Application::create();
        $application->container()->instance(ServiceController::class, new ServiceController(new Mailer()));

        $response = $application->handle(new Request(method: 'GET', path: '/service/show'));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString('{"action":"service-show","from":"mailer"}', $response->body());
    }

    public function testControllerWithRequiredDependencyOutsideContainerIsNotFoundInsteadOfServerError(): void
    {
        $response = Application::create()->handle(new Request(method: 'GET', path: '/service/show'));

        self::assertSame(404, $response->status());
    }

    public function testPrivateConstructorIsNeverCalled(): void
    {
        $response = Application::create()->handle(new Request(method: 'GET', path: '/private/show'));

        self::assertSame(404, $response->status());
        self::assertSame(0, PrivateController::$instances);
    }

    public function testAutoloadIsOnlyAskedForTheBuiltClassName(): void
    {
        $asked = [];
        $spy = static function (string $class) use (&$asked): void {
            $asked[] = $class;
        };
        spl_autoload_register($spy, true, true);

        try {
            $resolver = new ConventionRouteResolver();
            $resolver->resolve(new Request(method: 'GET', path: '/report/show'));
            $resolver->resolve(new Request(method: 'GET', path: '/admin\\user/show'));
            $resolver->resolve(new Request(method: 'GET', path: '/admin%5Cuser/show'));
            $resolver->resolve(new Request(method: 'GET', path: '/../user/show'));
        } finally {
            spl_autoload_unregister($spy);
        }

        foreach ($asked as $class) {
            if (str_starts_with($class, 'Elavora\\Api\\Framework\\')) {
                continue; // classes do próprio framework, carregadas sob demanda
            }
            self::assertSame('App\\Http\\Controller\\ReportController', $class);
        }
    }

    // ---------------------------------------------------------------
    // Regras da action
    // ---------------------------------------------------------------

    /**
     * @param 'action_not_found'|'action_not_allowed' $reason
     */
    #[DataProvider('rejectedActions')]
    public function testRejectsAction(string $path, string $reason): void
    {
        [$resolver, $writer] = $this->resolverWithLogger();

        self::assertNull($resolver->resolve(new Request(method: 'GET', path: $path)));
        self::assertSame([$reason], self::reasons($writer), $path);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedActions(): iterable
    {
        yield 'nome sem o mesmo caso' => ['/user/listactive', 'action_not_found'];
        yield 'método ausente' => ['/user/missing', 'action_not_found'];
        yield 'sem index (action padrão)' => ['/user', 'action_not_found'];
        yield 'sem #[Action]' => ['/user/helper', 'action_not_allowed'];
        yield 'estático' => ['/user/build', 'action_not_allowed'];
        yield 'protegido' => ['/user/audit', 'action_not_allowed'];
        yield 'privado' => ['/user/secret', 'action_not_allowed'];
        yield 'herdado de classe pai no namespace' => ['/user/inherited', 'action_not_allowed'];
        yield 'herdado de classe pai fora do namespace' => ['/externalChild/external', 'action_not_allowed'];
        yield 'vem de um trait' => ['/user/export', 'action_not_allowed'];
    }

    #[DataProvider('executedActions')]
    public function testExecutesAllowedActions(string $path, string $body): void
    {
        $response = Application::create()->handle(new Request(method: 'GET', path: $path));

        self::assertSame(200, $response->status());
        self::assertJsonStringEqualsJsonString($body, $response->body());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function executedActions(): iterable
    {
        yield '/user/show' => ['/user/show', '{"id":1}'];
        yield '/user/listActive' => ['/user/listActive', '[]'];
        yield '/report2024/show' => ['/report2024/show', '{"action":"report2024-show"}'];
        yield '/useradmin/show' => ['/useradmin/show', '{"action":"useradmin-show"}'];
        yield '/externalChild/show' => ['/externalChild/show', '{"action":"external-child-show"}'];
    }

    public function testExternalChildOwnActionResolves(): void
    {
        self::assertSame(
            [ExternalChildController::class, 'show'],
            (new ConventionRouteResolver())->resolve(new Request(method: 'GET', path: '/externalChild/show')),
        );
    }

    public function testAttributeRulesStillApplyAfterResolution(): void
    {
        $response = Application::create()->handle(new Request(method: 'POST', path: '/secured/exposed'));

        self::assertSame(405, $response->status());
    }

    // ---------------------------------------------------------------
    // Quando uma regra falha
    // ---------------------------------------------------------------

    #[DataProvider('refusedPaths')]
    public function testRefusalIsPlainNotFoundWithoutReason(string $path): void
    {
        $response = Application::create()->handle(new Request(
            method: 'GET',
            path: $path,
            headers: ['X-Request-Id' => 'req-refused'],
        ));

        self::assertSame(404, $response->status());
        self::assertSame('req-refused', $response->headers()['X-Request-Id']);
        self::assertJsonStringEqualsJsonString(
            '{"message":"Not Found","request_id":"req-refused"}',
            $response->body(),
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedPaths(): iterable
    {
        foreach (self::invalidPaths() as $name => $case) {
            yield 'path: ' . $name => $case;
        }
        foreach (self::rejectedClasses() as $name => [$path]) {
            yield 'classe: ' . $name => [$path];
        }
        foreach (self::rejectedActions() as $name => [$path]) {
            yield 'action: ' . $name => [$path];
        }
    }

    #[DataProvider('refusedSample')]
    public function testOptionsOnRefusedPathIsNotFoundWithoutMetadata(string $path): void
    {
        $response = Application::create()->handle(new Request(
            method: 'OPTIONS',
            path: $path,
            headers: ['X-Request-Id' => 'req-options'],
        ));

        self::assertSame(404, $response->status());
        self::assertArrayNotHasKey('Allow', $response->headers());
        self::assertJsonStringEqualsJsonString(
            '{"message":"Not Found","request_id":"req-options"}',
            $response->body(),
        );
    }

    #[DataProvider('refusedSample')]
    public function testHeadOnRefusedPathIsNotFound(string $path): void
    {
        $response = Application::create()->handle(new Request(method: 'HEAD', path: $path));

        self::assertSame(404, $response->status());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedSample(): iterable
    {
        yield 'path inválido' => ['/User/show'];
        yield 'classe abstrata' => ['/base/show'];
        yield 'sem #[Action]' => ['/user/helper'];
        yield 'herdada' => ['/user/inherited'];
        yield 'trait' => ['/user/export'];
    }

    public function testNoProjectCodeRunsOnRefusal(): void
    {
        $application = Application::create();

        foreach (['/user/helper', '/user/build', '/user/audit', '/user/secret', '/user/export', '/user/inherited', '/user/listactive', '/user'] as $path) {
            self::assertSame(404, $application->handle(new Request(method: 'GET', path: $path))->status(), $path);
            self::assertSame(404, $application->handle(new Request(method: 'OPTIONS', path: $path))->status(), $path);
        }

        self::assertSame(0, UserController::$instances);
        self::assertSame(0, UserController::$executions);
    }

    // ---------------------------------------------------------------
    // O que vai para o log
    // ---------------------------------------------------------------

    public function testRefusalWritesExactlyOneDebugEntry(): void
    {
        $writer = new RoutingLogWriter();
        $application = Application::create();
        $application->container()->instance(Logger::class, new Logger($writer));

        $application->handle(new Request(
            method: 'GET',
            path: '/user/helper',
            headers: ['X-Request-Id' => 'req-log', 'Authorization' => 'Bearer segredo-header'],
            query: ['token' => 'segredo-query'],
            body: ['password' => 'segredo-body'],
        ));

        self::assertCount(1, $writer->entries);
        $entry = $writer->entries[0];
        self::assertSame('debug', $entry['level']);
        self::assertSame('Convention route not resolved', $entry['message']);
        self::assertSame('req-log', $entry['request_id']);
        self::assertSame(['path' => '/user/helper', 'reason' => 'action_not_allowed'], $entry['context']);

        $json = json_encode($writer->entries, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString('segredo', $json);
    }

    public function testResolvedRequestWritesNoLog(): void
    {
        $writer = new RoutingLogWriter();
        $application = Application::create();
        $application->container()->instance(Logger::class, new Logger($writer));

        $response = $application->handle(new Request(method: 'GET', path: '/user/show'));

        self::assertSame(200, $response->status());
        self::assertSame([], $writer->entries);
    }

    public function testLoggedPathIsCutAt200Characters(): void
    {
        [$resolver, $writer] = $this->resolverWithLogger();
        $path = '/' . str_repeat('a', 250);

        $resolver->resolve(new Request(method: 'GET', path: $path));

        self::assertCount(1, $writer->entries);
        self::assertSame(substr($path, 0, 200), $writer->entries[0]['context']['path']);
        self::assertSame(200, strlen($writer->entries[0]['context']['path']));
    }

    public function testShortPathIsLoggedWhole(): void
    {
        [$resolver, $writer] = $this->resolverWithLogger();

        $resolver->resolve(new Request(method: 'GET', path: '/report/show'));

        self::assertSame(['path' => '/report/show', 'reason' => 'class_not_found'], $writer->entries[0]['context']);
    }

    public function testRefusalWithoutLoggerDoesNotFail(): void
    {
        $response = Application::create()->handle(new Request(method: 'GET', path: '/user/helper'));

        self::assertSame(404, $response->status());
    }

    public function testResolverWithoutContainerDoesNotLogNorFail(): void
    {
        self::assertNull((new ConventionRouteResolver())->resolve(new Request(method: 'GET', path: '/user/helper')));
    }

    public function testLoggerRegisteredUnderAnotherNameIsIgnored(): void
    {
        $writer = new RoutingLogWriter();
        $application = Application::create();
        $application->container()->instance('logger', new Logger($writer));

        $application->handle(new Request(method: 'GET', path: '/user/helper'));

        self::assertSame([], $writer->entries);
    }

    public function testLoggerIsLookedUpAtRequestTime(): void
    {
        $writer = new RoutingLogWriter();
        $application = Application::create();
        $application->handle(new Request(method: 'GET', path: '/user/helper'));

        $application->container()->instance(Logger::class, new Logger($writer));
        $application->handle(new Request(method: 'GET', path: '/user/secret'));

        self::assertCount(1, $writer->entries);
        self::assertSame(['path' => '/user/secret', 'reason' => 'action_not_allowed'], $writer->entries[0]['context']);
    }

    // ---------------------------------------------------------------

    /**
     * @return array{ConventionRouteResolver, RoutingLogWriter}
     */
    private function resolverWithLogger(): array
    {
        $writer = new RoutingLogWriter();
        $container = Application::create()->container();
        $container->instance(Logger::class, new Logger($writer));

        return [new ConventionRouteResolver(container: $container), $writer];
    }

    /**
     * @return list<mixed>
     */
    private static function reasons(RoutingLogWriter $writer): array
    {
        $reasons = [];
        foreach ($writer->entries as $entry) {
            self::assertSame('debug', $entry['level']);
            self::assertSame('Convention route not resolved', $entry['message']);
            self::assertSame(['path', 'reason'], array_keys($entry['context']));
            $reasons[] = $entry['context']['reason'];
        }

        return $reasons;
    }
}

final class RoutingLogWriter implements LogWriter
{
    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function write(array $entry): void
    {
        $this->entries[] = $entry;
    }
}

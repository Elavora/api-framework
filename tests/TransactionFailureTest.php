<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Attribute;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Attributes\Transaction;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Contracts\TransactionManager;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TransactionFailureTest extends TestCase
{
    public function testRollsBackWhenActionThrows(): void
    {
        [$application, $transactionManager] = $this->application();
        $application->post('/throws', [TransactionFailureControllerStub::class, 'throws']);

        $response = $application->handle(new Request(method: 'POST', path: '/throws'));

        self::assertSame(500, $response->status());
        self::assertStringContainsString('action failed', $response->body());
        self::assertSame(['begin', 'rollback'], $transactionManager->events);
    }

    public function testRollsBackWhenLaterBeforeHookReturnsResponse(): void
    {
        [$application, $transactionManager] = $this->application();
        $application->post('/rejected', [TransactionFailureControllerStub::class, 'rejected']);

        $response = $application->handle(new Request(method: 'POST', path: '/rejected'));

        self::assertSame(400, $response->status());
        self::assertSame(['begin', 'rollback'], $transactionManager->events);
    }

    public function testDoesNotFinalizeWhenBeginReturnsFalse(): void
    {
        [$application, $transactionManager] = $this->application(beginResult: false);
        $application->post('/success', [TransactionFailureControllerStub::class, 'success']);

        $response = $application->handle(new Request(method: 'POST', path: '/success'));

        self::assertSame(201, $response->status());
        self::assertSame(['begin'], $transactionManager->events);
    }

    public function testRollbackFailureDoesNotHideOriginalException(): void
    {
        [$application, $transactionManager] = $this->application(rollbackThrows: true);
        $application->post('/throws', [TransactionFailureControllerStub::class, 'throws']);

        $response = $application->handle(new Request(method: 'POST', path: '/throws'));

        self::assertSame(500, $response->status());
        self::assertStringContainsString('action failed', $response->body());
        self::assertStringNotContainsString('rollback failed', $response->body());
        self::assertSame(['begin', 'rollback'], $transactionManager->events);
    }

    public function testRollbackFailureDoesNotHideEarlyResponse(): void
    {
        [$application, $transactionManager] = $this->application(rollbackThrows: true);
        $application->post('/rejected', [TransactionFailureControllerStub::class, 'rejected']);

        $response = $application->handle(new Request(method: 'POST', path: '/rejected'));

        self::assertSame(400, $response->status());
        self::assertStringContainsString('request rejected', $response->body());
        self::assertSame(['begin', 'rollback'], $transactionManager->events);
    }

    /**
     * @return array{Application, FailureRecordingTransactionManager}
     */
    private function application(
        bool $beginResult = true,
        bool $rollbackThrows = false
    ): array {
        $application = Application::create(debug: true);
        $transactionManager = new FailureRecordingTransactionManager($beginResult, $rollbackThrows);
        $application->container()->instance(TransactionManager::class, $transactionManager);

        return [$application, $transactionManager];
    }
}

final class TransactionFailureControllerStub
{
    #[Transaction]
    public function throws(Request $request): Response
    {
        throw new RuntimeException('action failed');
    }

    #[Transaction]
    #[RejectRequestAfterTransaction]
    public function rejected(Request $request): Response
    {
        return Response::created();
    }

    #[Transaction]
    public function success(Request $request): Response
    {
        return Response::created();
    }
}

#[Attribute(Attribute::TARGET_METHOD)]
final class RejectRequestAfterTransaction implements BeforeRequestAttribute
{
    public function before(Request $request, Container $container): ?Response
    {
        return Response::badRequest('request rejected');
    }

    public function options(): array
    {
        return [];
    }
}

final class FailureRecordingTransactionManager implements TransactionManager
{
    /** @var list<string> */
    public array $events = [];

    public function __construct(
        private readonly bool $beginResult,
        private readonly bool $rollbackThrows
    ) {
    }

    public function begin(): bool
    {
        $this->events[] = 'begin';

        return $this->beginResult;
    }

    public function commit(): bool
    {
        $this->events[] = 'commit';

        return true;
    }

    public function rollback(): bool
    {
        $this->events[] = 'rollback';
        if ($this->rollbackThrows) {
            throw new RuntimeException('rollback failed');
        }

        return true;
    }
}

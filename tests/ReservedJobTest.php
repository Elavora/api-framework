<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Contracts\AcknowledgesJobs;
use Elavora\Api\Framework\Contracts\Queue;
use Elavora\Api\Framework\Queue\ReservedJob;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ReservedJobTest extends TestCase
{
    public function testExposesQueuePayloadAndToken(): void
    {
        $job = new ReservedJob(queue: 'conteudo', payload: ['task' => 'resumir'], token: 'abc');

        self::assertSame('conteudo', $job->queue());
        self::assertSame(['task' => 'resumir'], $job->payload());
        self::assertSame('abc', $job->token());
    }

    public function testAcceptsEmptyPayload(): void
    {
        self::assertSame([], (new ReservedJob('fila', [], 't'))->payload());
    }

    public function testRejectsEmptyQueue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A fila da reserva nao pode ser vazia.');

        new ReservedJob('', [], 't');
    }

    public function testRejectsEmptyToken(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('O token da reserva nao pode ser vazio.');

        new ReservedJob('fila', [], '');
    }

    public function testAcknowledgesJobsIsSeparateFromQueue(): void
    {
        $queue = new class implements Queue {
            public function push(string $queue, array $payload): void {}

            public function pop(string $queue): ?array
            {
                return null;
            }
        };

        self::assertNotInstanceOf(AcknowledgesJobs::class, $queue);
    }

    public function testContractCanBeImplementedAlongsideQueue(): void
    {
        $queue = new class implements Queue, AcknowledgesJobs {
            public function push(string $queue, array $payload): void {}

            public function pop(string $queue): ?array
            {
                return null;
            }

            public function reserve(array $queues): ?ReservedJob
            {
                return new ReservedJob($queues[0], ['x' => 1], 'tok');
            }

            public function acknowledge(ReservedJob $job): void {}

            public function extend(ReservedJob $job, ?int $seconds = null): void {}

            public function recoverExpired(string $queue): int
            {
                return 0;
            }
        };

        self::assertInstanceOf(AcknowledgesJobs::class, $queue);
        self::assertSame('tok', $queue->reserve(['a', 'b'])?->token());
    }
}

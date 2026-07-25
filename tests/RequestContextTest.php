<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\LogWriter;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Logging\Logger;
use PHPUnit\Framework\TestCase;

final class RequestContextTest extends TestCase
{
    public function testSharesIncomingRequestIdBetweenResponseAndLog(): void
    {
        $writer = new RequestContextLogWriter();
        $logger = new Logger($writer);
        $application = Application::create();
        $application->get('/log', static function () use ($logger): string {
            $logger->info('Request recebida');

            return 'ok';
        });

        $response = $application->handle(new Request(
            method: 'GET',
            path: '/log',
            headers: ['X-Request-Id' => 'req-upstream']
        ));

        self::assertSame('req-upstream', $response->headers()['X-Request-Id']);
        self::assertSame('req-upstream', $writer->entries[0]['request_id']);
    }

    public function testUsesDistinctRequestIdsForSequentialRequestsWithSameLogger(): void
    {
        $writer = new RequestContextLogWriter();
        $logger = new Logger($writer);
        $application = Application::create();
        $application->get('/log', static function () use ($logger): string {
            $logger->info('Request recebida');

            return 'ok';
        });
        $firstRequest = new Request(method: 'GET', path: '/log');
        $secondRequest = new Request(method: 'GET', path: '/log');

        $firstResponse = $application->handle($firstRequest);
        $secondResponse = $application->handle($secondRequest);

        self::assertNotSame(
            $firstResponse->headers()['X-Request-Id'],
            $secondResponse->headers()['X-Request-Id']
        );
        self::assertSame($firstResponse->headers()['X-Request-Id'], $writer->entries[0]['request_id']);
        self::assertSame($secondResponse->headers()['X-Request-Id'], $writer->entries[1]['request_id']);
    }
}

final class RequestContextLogWriter implements LogWriter
{
    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function write(array $entry): void
    {
        $this->entries[] = $entry;
    }
}

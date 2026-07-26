<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseHeaderTest extends TestCase
{
    public function testConstructorRemovesCaseInsensitiveDuplicates(): void
    {
        $response = new Response(headers: [
            'Content-Type' => 'text/plain',
            'content-type' => 'application/problem+json',
        ]);

        self::assertSame(['content-type' => 'application/problem+json'], $response->headers());
    }

    public function testJsonFactoryOverridesContentTypeRegardlessOfCase(): void
    {
        $response = Response::json(
            payload: ['ok' => true],
            headers: ['content-type' => 'application/problem+json']
        );

        self::assertSame(
            ['Content-Type' => 'application/json; charset=utf-8'],
            $response->headers()
        );
    }

    public function testTextFactoryPreservesCustomContentTypeRegardlessOfCase(): void
    {
        $response = Response::text('ok', headers: ['content-type' => 'text/csv']);

        self::assertSame(['content-type' => 'text/csv'], $response->headers());
    }

    public function testWithHeaderReplacesExistingHeaderRegardlessOfCase(): void
    {
        $response = (new Response(headers: [
            'X-Request-Id' => 'old',
            'X-Framework' => 'Elavora',
        ]))->withHeader('x-request-id', 'new');

        self::assertSame([
            'X-Framework' => 'Elavora',
            'x-request-id' => 'new',
        ], $response->headers());
    }
}

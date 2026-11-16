<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Tests;

use Elavora\Api\Framework\Exceptions\HttpException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * As fabricas de HttpException montam status, mensagem padrao, erros e headers.
 */
final class HttpExceptionTest extends TestCase
{
    /**
     * @return iterable<string, array{HttpException, int, string}>
     */
    public static function defaults(): iterable
    {
        yield 'badRequest' => [HttpException::badRequest(), 400, 'Bad Request'];
        yield 'unauthorized' => [HttpException::unauthorized(), 401, 'Unauthorized'];
        yield 'forbidden' => [HttpException::forbidden(), 403, 'Forbidden'];
        yield 'notFound' => [HttpException::notFound(), 404, 'Not Found'];
        yield 'conflict' => [HttpException::conflict(), 409, 'Conflict'];
        yield 'unprocessable' => [HttpException::unprocessable(), 422, 'Unprocessable Entity'];
        yield 'internalServerError' => [HttpException::internalServerError(), 500, 'Internal Server Error'];
    }

    #[DataProvider('defaults')]
    public function testFactoryUsesTheStatusAndDefaultMessage(HttpException $exception, int $status, string $message): void
    {
        self::assertSame($status, $exception->status());
        self::assertSame($message, $exception->getMessage());
        self::assertSame([], $exception->errors());
        self::assertSame([], $exception->headers());
    }

    public function testConflictAndUnprocessableCarryErrors(): void
    {
        $conflict = HttpException::conflict('E-mail ja cadastrado', ['email' => 'E-mail ja cadastrado.']);
        $unprocessable = HttpException::unprocessable('Regra violada', ['quantity' => 'Estoque insuficiente.']);

        self::assertSame(409, $conflict->status());
        self::assertSame(['email' => 'E-mail ja cadastrado.'], $conflict->errors());
        self::assertSame(422, $unprocessable->status());
        self::assertSame(['quantity' => 'Estoque insuficiente.'], $unprocessable->errors());
    }

    public function testUnauthorizedAndForbiddenCarryHeaders(): void
    {
        $unauthorized = HttpException::unauthorized('Login obrigatorio', ['WWW-Authenticate' => 'Bearer']);
        $forbidden = HttpException::forbidden('Sem permissao', ['X-Reason' => 'role']);

        self::assertSame(['WWW-Authenticate' => 'Bearer'], $unauthorized->headers());
        self::assertSame('Sem permissao', $forbidden->getMessage());
        self::assertSame(['X-Reason' => 'role'], $forbidden->headers());
    }
}

<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Exceptions;

use RuntimeException;

/**
 * Excecao HTTP padronizada para respostas JSON.
 *
 * Use quando o dominio ou uma camada de aplicacao precisa interromper o fluxo
 * com status, erros e headers controlados.
 */
final class HttpException extends RuntimeException
{
    /**
     * @param array<string, mixed> $errors Erros seguros para retornar ao cliente.
     * @param array<string, string> $headers Headers adicionais da resposta.
     */
    public function __construct(
        string $message,
        private readonly int $status = 500,
        private readonly array $errors = [],
        private readonly array $headers = [],
    ) {
        parent::__construct($message);
    }

    /**
     * Cria uma excecao 400 Bad Request.
     *
     * @param array<string, mixed> $errors Erros seguros para retornar ao cliente.
     * @param array<string, string> $headers Headers adicionais.
     */
    public static function badRequest(string $message = 'Bad Request', array $errors = [], array $headers = []): self
    {
        return new self(message: $message, status: 400, errors: $errors, headers: $headers);
    }

    /**
     * Cria uma excecao 401 Unauthorized.
     *
     * @param array<string, string> $headers Headers adicionais (por exemplo, WWW-Authenticate).
     */
    public static function unauthorized(string $message = 'Unauthorized', array $headers = []): self
    {
        return new self(message: $message, status: 401, headers: $headers);
    }

    /**
     * Cria uma excecao 403 Forbidden.
     *
     * @param array<string, string> $headers Headers adicionais.
     */
    public static function forbidden(string $message = 'Forbidden', array $headers = []): self
    {
        return new self(message: $message, status: 403, headers: $headers);
    }

    /**
     * Cria uma excecao 404 Not Found.
     *
     * @param array<string, string> $headers Headers adicionais.
     */
    public static function notFound(string $message = 'Not Found', array $headers = []): self
    {
        return new self(message: $message, status: 404, headers: $headers);
    }

    /**
     * Cria uma excecao 409 Conflict, quando o pedido conflita com o estado atual (por exemplo, e-mail ja cadastrado).
     *
     * @param array<string, mixed> $errors Erros seguros para retornar ao cliente.
     * @param array<string, string> $headers Headers adicionais.
     */
    public static function conflict(string $message = 'Conflict', array $errors = [], array $headers = []): self
    {
        return new self(message: $message, status: 409, errors: $errors, headers: $headers);
    }

    /**
     * Cria uma excecao 422 Unprocessable Entity, quando o pedido esta bem formado mas viola uma regra de negocio.
     *
     * @param array<string, mixed> $errors Erros seguros para retornar ao cliente.
     * @param array<string, string> $headers Headers adicionais.
     */
    public static function unprocessable(string $message = 'Unprocessable Entity', array $errors = [], array $headers = []): self
    {
        return new self(message: $message, status: 422, errors: $errors, headers: $headers);
    }

    /**
     * Cria uma excecao 500 Internal Server Error.
     *
     * @param array<string, string> $headers Headers adicionais.
     */
    public static function internalServerError(string $message = 'Internal Server Error', array $headers = []): self
    {
        return new self(message: $message, status: 500, headers: $headers);
    }

    /**
     * Retorna o status HTTP que sera usado na resposta.
     */
    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, mixed> Erros seguros para retornar ao cliente.
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return array<string, string> Headers adicionais da resposta.
     */
    public function headers(): array
    {
        return $this->headers;
    }
}

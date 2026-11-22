<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Queue;

use InvalidArgumentException;

/**
 * Mensagem reservada por uma fila {@see \Elavora\Api\Framework\Contracts\AcknowledgesJobs}.
 */
final class ReservedJob
{
    /**
     * @param string $queue Fila de onde a mensagem saiu.
     * @param array<string, mixed> $payload Dados da mensagem.
     * @param string $token Identificador opaco, interpretado so pelo adaptador.
     */
    public function __construct(
        private readonly string $queue,
        private readonly array $payload,
        private readonly string $token,
    ) {
        if ($queue === '') {
            throw new InvalidArgumentException('A fila da reserva nao pode ser vazia.');
        }

        if ($token === '') {
            throw new InvalidArgumentException('O token da reserva nao pode ser vazio.');
        }
    }

    public function queue(): string
    {
        return $this->queue;
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    public function token(): string
    {
        return $this->token;
    }
}

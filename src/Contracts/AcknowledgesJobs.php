<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

use Elavora\Api\Framework\Queue\ReservedJob;

/**
 * Contrato opcional para filas que confirmam o processamento (ack).
 *
 * Complementa {@see Queue}: a mensagem e reservada em vez de removida e so e descartada no
 * acknowledge(). Reservas sem ack dentro do prazo de visibilidade voltam a fila (pelo menos uma vez).
 */
interface AcknowledgesJobs
{
    /**
     * Reserva a proxima mensagem da fila mais prioritaria que tiver trabalho.
     *
     * @param list<string> $queues Filas em ordem de prioridade (a primeira e a mais prioritaria).
     * @return ReservedJob|null Reserva ou null quando todas as filas estiverem vazias.
     */
    public function reserve(array $queues): ?ReservedJob;

    /**
     * Confirma o processamento e descarta a reserva. Reserva que nao existe mais e ignorada.
     */
    public function acknowledge(ReservedJob $job): void;

    /**
     * Renova o prazo de visibilidade da reserva (heartbeat).
     *
     * @param int|null $seconds Novo prazo a partir de agora; null usa o prazo padrao do adaptador.
     */
    public function extend(ReservedJob $job, ?int $seconds = null): void;

    /**
     * Devolve ao inicio da fila as reservas expiradas sem ack.
     *
     * @return int Quantidade de mensagens devolvidas.
     */
    public function recoverExpired(string $queue): int;
}

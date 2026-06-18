<?php

declare(strict_types=1);

namespace Elavora\Api\Contracts;

/**
 * Contrato minimo para controle transacional usado por attributes e extensoes.
 */
interface TransactionManager
{
    /**
     * Inicia uma transacao.
     */
    public function begin(): bool;

    /**
     * Confirma a transacao aberta.
     */
    public function commit(): bool;

    /**
     * Desfaz a transacao aberta.
     */
    public function rollback(): bool;
}

<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Attributes;

use Attribute;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\AfterExceptionAttribute;
use Elavora\Api\Framework\Contracts\AfterResponseAttribute;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Contracts\TransactionManager;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use RuntimeException;
use Throwable;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Envolve a action em uma transacao.
 *
 * Usa o TransactionManager registrado no container. Respostas 2xx confirmam a
 * transacao; outras respostas fazem rollback.
 */
final class Transaction implements BeforeRequestAttribute, AfterResponseAttribute, AfterExceptionAttribute
{
    private ?TransactionManager $activeTransactionManager = null;

    /**
     * Inicia a transacao antes da action.
     */
    public function before(Request $request, Container $container): ?Response
    {
        $transactionManager = $this->transactionManager($container);
        if ($transactionManager->begin()) {
            $this->activeTransactionManager = $transactionManager;
        }

        return null;
    }

    /**
     * Confirma ou desfaz a transacao conforme o status da resposta.
     */
    public function after(Request $request, Response $response, Container $container): ?Response
    {
        if ($this->activeTransactionManager === null) {
            return null;
        }

        if ($response->status() >= 200 && $response->status() < 300) {
            $this->activeTransactionManager->commit();
            $this->activeTransactionManager = null;

            return null;
        }

        $this->rollback();

        return null;
    }

    /**
     * Desfaz a transacao quando a action ou outro hook falha.
     */
    public function afterException(Request $request, Throwable $exception, Container $container): void
    {
        $this->rollback();
    }

    /**
     * @return array{transaction: true}
     */
    public function options(): array
    {
        return ['transaction' => true];
    }

    private function transactionManager(Container $container): TransactionManager
    {
        if (!$container->has(TransactionManager::class)) {
            throw new RuntimeException('TransactionManager nao foi registrado no container.');
        }

        $transactionManager = $container->get(TransactionManager::class);
        if (!$transactionManager instanceof TransactionManager) {
            throw new RuntimeException('TransactionManager registrado deve implementar Elavora\\Api\\Contracts\\TransactionManager.');
        }

        return $transactionManager;
    }

    private function rollback(): void
    {
        if ($this->activeTransactionManager === null) {
            return;
        }

        $transactionManager = $this->activeTransactionManager;
        $this->activeTransactionManager = null;

        try {
            $transactionManager->rollback();
        } catch (Throwable) {
            // A falha de limpeza nao pode substituir a resposta ou excecao original.
        }
    }
}



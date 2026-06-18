<?php

declare(strict_types=1);

namespace Elavora\Api\Attributes;

use Attribute;
use Elavora\Api\Container;
use Elavora\Api\Contracts\AfterResponseAttribute;
use Elavora\Api\Contracts\BeforeRequestAttribute;
use Elavora\Api\Contracts\TransactionManager;
use Elavora\Api\Http\Request;
use Elavora\Api\Http\Response;
use RuntimeException;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Envolve a action em uma transacao.
 *
 * Usa o TransactionManager registrado no container. Respostas 2xx confirmam a
 * transacao; outras respostas fazem rollback.
 */
final class Transaction implements BeforeRequestAttribute, AfterResponseAttribute
{
    /**
     * Inicia a transacao antes da action.
     */
    public function before(Request $request, Container $container): ?Response
    {
        $this->transactionManager($container)->begin();

        return null;
    }

    /**
     * Confirma ou desfaz a transacao conforme o status da resposta.
     */
    public function after(Request $request, Response $response, Container $container): ?Response
    {
        $transactionManager = $this->transactionManager($container);

        if ($response->status() >= 200 && $response->status() < 300) {
            $transactionManager->commit();
            return null;
        }

        $transactionManager->rollback();

        return null;
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
}

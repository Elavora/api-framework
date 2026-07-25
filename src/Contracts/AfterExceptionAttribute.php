<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Http\Request;
use Throwable;

/**
 * Contrato para attributes que precisam finalizar recursos apos uma excecao.
 */
interface AfterExceptionAttribute extends HttpAttribute
{
    /**
     * Executa a finalizacao sem substituir a excecao original.
     */
    public function afterException(Request $request, Throwable $exception, Container $container): void;
}

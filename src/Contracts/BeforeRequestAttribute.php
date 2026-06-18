<?php

declare(strict_types=1);

namespace Elavora\Framework\Contracts;

use Elavora\Framework\Container;
use Elavora\Framework\Http\Request;
use Elavora\Framework\Http\Response;

/**
 * Contrato para attributes executados antes da action do controller.
 */
interface BeforeRequestAttribute extends HttpAttribute
{
    /**
     * Executa logica antes da action.
     *
     * @return Response|null Retorne Response para interromper o fluxo, ou null para continuar.
     */
    public function before(Request $request, Container $container): ?Response;
}



<?php

declare(strict_types=1);

namespace Elavora\Api\Contracts;

use Elavora\Api\Container;
use Elavora\Api\Http\Request;
use Elavora\Api\Http\Response;

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

<?php

declare(strict_types=1);

namespace Elavora\Api\Contracts;

use Elavora\Api\Container;
use Elavora\Api\Http\Request;
use Elavora\Api\Http\Response;

/**
 * Contrato para attributes executados depois da action do controller.
 */
interface AfterResponseAttribute extends HttpAttribute
{
    /**
     * Executa logica apos a action.
     *
     * Retorne uma nova Response quando precisar substituir a resposta original.
     */
    public function after(Request $request, Response $response, Container $container): ?Response;
}

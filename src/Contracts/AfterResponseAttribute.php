<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

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



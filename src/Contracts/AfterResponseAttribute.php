<?php

declare(strict_types=1);

namespace Elavora\Framework\Contracts;

use Elavora\Framework\Container;
use Elavora\Framework\Http\Request;
use Elavora\Framework\Http\Response;

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



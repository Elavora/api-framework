<?php

declare(strict_types=1);

namespace Elavora\Api\Contracts;

use Elavora\Api\Application;

/**
 * Contrato para pacotes que registram servicos, rotas ou configuracoes na aplicacao.
 */
interface Extension
{
    /**
     * Registra a extensao na aplicacao Elavora API.
     */
    public function register(Application $application): void;
}

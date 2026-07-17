<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

use Elavora\Api\Framework\Application;

/**
 * Contrato para pacotes que registram servicos, rotas ou configuracoes na aplicacao.
 */
interface Extension
{
    /**
     * Registra a extensao na aplicacao Elavora.
     */
    public function register(Application $application): void;
}



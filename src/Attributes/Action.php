<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
/**
 * Autoriza a exposicao de um metodo pelo roteamento por convencao.
 *
 * Rotas registradas explicitamente nao exigem este atributo.
 */
final class Action
{
}

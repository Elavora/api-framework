<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

/**
 * Contrato opcional para uma classe dizer como o tipo dela aparece na documentacao de `OPTIONS`.
 *
 * Quem consome a API nao conhece classes PHP. Por isso a documentacao nunca mostra o nome de uma classe: um
 * DataType aparece como o primitivo que ele substitui (`string`, `int`, ...). O framework descobre esse
 * primitivo pelo tipo de retorno de `value()`. Implemente este contrato quando isso nao basta, ou quando o
 * tipo tem mais a informar.
 *
 * O retorno e uma string com o nome do tipo, ou um array. As chaves abaixo sao as unicas aceitas (qualquer
 * outra e descartada) e so `type` e obrigatoria:
 *
 * - `type`: um primitivo (`string`, `int`, `float`, `bool`) ou uma regra nomeada do framework (`email`,
 *   `uuid`, `url`, `base64`, `json`). Nunca o nome de uma classe;
 * - `format`: formato semantico, como `email`, `date` ou `uuid`;
 * - `mask`: como o valor e digitado ou exibido. `0` e um digito, `A` e uma letra e qualquer outro caractere
 *   e literal: `000.000.000-00`;
 * - `pattern`: expressao regular sem delimitadores, num subconjunto que PCRE e JavaScript entendem;
 * - `enum`: lista fechada de opcoes (valores escalares);
 * - `example`: um valor de exemplo;
 * - `description`: texto curto.
 *
 * `mask` e `pattern` so descrevem: quem valida de verdade e o `isValid()` do DataType.
 *
 * Na resposta de `OPTIONS`, o tipo continua sendo uma string. Ele so vira objeto quando declara algo alem do
 * `type`. Para nao haver varios formatos, o conjunto de chaves e fixo: precisando de outra, altere este
 * pacote.
 */
interface DescribesType
{
    /**
     * Tipo na documentacao.
     *
     * @return string|array<string, mixed>
     */
    public static function documentedType(): string|array;
}

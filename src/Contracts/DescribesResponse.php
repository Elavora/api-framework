<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

/**
 * Contrato opcional para um objeto descrever o formato que ele tem na resposta HTTP.
 *
 * Complementa `Responseable`: o `Responseable` define como o objeto vira JSON (`jsonSerialize()`), e o
 * `DescribesResponse` define como esse JSON e documentado em `OPTIONS`. Com `#[Response(Usuario::class)]`, o
 * framework chama `responseSchema()`, entao o schema acompanha a classe em vez de ser repetido em cada action
 * que a devolve.
 *
 * O schema e livre, como o de `#[Response]`: chave => tipo. O tipo e o nome de um tipo primitivo (`'string'`,
 * `'int'`), o nome de uma classe de DataType (`Email::class`, como em `#[RequiredFields]`) ou o nome de outra
 * classe que implemente este contrato, que o framework expande no lugar.
 *
 * O contrato e independente de `Responseable`: nem todo objeto devolvido por uma action precisa descrever a si
 * mesmo.
 */
interface DescribesResponse
{
    /**
     * Formato do objeto na resposta. Deve acompanhar o que `jsonSerialize()` devolve.
     *
     * @return array<string, mixed>
     */
    public static function responseSchema(): array;
}

<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

/**
 * Contrato para objetos de valor com validacao propria.
 *
 * Use DataTypes quando um valor representa conceito do dominio e precisa de
 * validacao, normalizacao ou tipagem forte em mais de um fluxo.
 *
 * O construtor recebe o valor bruto e ja valida — nao ha um `from()` separado.
 * Isso mantem uma unica forma de criar o DataType (`new Email($valor)`), em vez
 * de duas (`new` e `from()`) para o mesmo efeito. Tipos com mais de um formato
 * legitimo de entrada (ex.: um valor que ja vem hasheado e outro que precisa
 * ser hasheado antes) continuam livres para oferecer metodos nomeados
 * adicionais (`fromPlainText()` etc.) ao lado do construtor.
 */
interface DataType
{
    /**
     * Cria e valida o DataType a partir de um valor bruto.
     */
    public function __construct(mixed $value);

    /**
     * Verifica se um valor bruto pode ser aceito pelo DataType.
     */
    public static function isValid(mixed $value): bool;

    /**
     * Retorna o valor primitivo armazenado.
     */
    public function value(): mixed;
}

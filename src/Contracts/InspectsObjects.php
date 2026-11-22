<?php

declare(strict_types=1);

namespace Elavora\Api\Framework\Contracts;

/**
 * Contrato opcional para consultar objetos de um storage sem baixar o conteudo.
 *
 * Complementa o Storage: uma implementacao pode implementar os dois contratos.
 * Serve, por exemplo, para conferir um upload direto (URL pre-assinada).
 */
interface InspectsObjects
{
    /**
     * Informa se o objeto existe.
     *
     * So a ausencia do objeto resulta em false; outras falhas do provider sao propagadas.
     *
     * @throws \InvalidArgumentException Quando a chave e invalida.
     */
    public function exists(string $key): bool;

    /**
     * Retorna o tamanho do objeto em bytes, ou null se ele nao existe.
     *
     * @throws \InvalidArgumentException Quando a chave e invalida.
     */
    public function size(string $key): ?int;
}

# Publicacao Composer

Este documento registra o minimo operacional para publicar e validar o pacote $package.

## Pacote

- Repositorio: $repoUrl
- Packagist: $packagistUrl
- Tipo Composer: $type
- Descricao: Nucleo HTTP modular do framework Elavora
- PHP: $php

## Instalacao

``bash
composer require elavora/api-framework
``

## Dependencias de runtime

- `ext-json` `*`

## Release

- Tags devem seguir SemVer no formato X.Y.Z.
- O workflow de merge em main cria a tag e publica a release.
- Use a label elease quando a release deve ser estavel.
- Use enhancement para incremento minor e upgrade para incremento major.
- Sem enhancement, dependencies ou upgrade, o workflow incrementa patch.

## Validacao local em container

Execute a partir da raiz do checkout local dos repositorios Elavora:

``bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-framework" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-framework" composer:2 sh -lc "find . \\( -path ./.git -o -path ./vendor \\) -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l"
``

Scripts Composer disponiveis:

- `composer lint`
- `composer test`
- `composer check`

## Packagist

Ao cadastrar no Packagist, use a URL do repositorio GitHub:

``text
https://github.com/Elavora/api-framework
``

Depois do cadastro, confirme se o Packagist mostra a ultima tag estavel e teste a instalacao em um container limpo.
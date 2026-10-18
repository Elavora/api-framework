# Guia de uso

Nucleo HTTP modular do framework Elavora

## Instalacao

```bash
composer require elavora/api-framework
```

## Quando usar

- Criar APIs HTTP pequenas e modulares.
- Registrar rotas, middlewares e extensoes.
- Usar contratos compartilhados por pacotes opcionais.

## Exemplo rapido

```php
<?php

declare(strict_types=1);

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

require __DIR__ . '/vendor/autoload.php';

$app = Application::create();

$app->get('/health', static fn (): Response => Response::json([
    'status' => 'ok',
]));

$response = $app->handle(Request::fromGlobals());
$app->emit($response);
```

## Principais pontos de entrada

- `Elavora\Api\Framework\Application`
- `Elavora\Api\Framework\Container`
- `Elavora\Api\Framework\Attributes\Cache`
- `Elavora\Api\Framework\Attributes\Details`
- `Elavora\Api\Framework\Attributes\Method`
- `Elavora\Api\Framework\Http\Request`
- `Elavora\Api\Framework\Http\Response`

## Dependencias de runtime

- `ext-json` `*`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use a imagem de desenvolvimento da Elavora (PowerShell, na pasta do pacote):

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace,readonly" ghcr.io/elavora/php-dev:8.3-latest sh -lc 'cp -R /workspace /tmp/package && cd /tmp/package && git config --global --add safe.directory /tmp/package && composer install --no-interaction --no-progress && composer check'
```

Mais detalhes em [Ambiente de desenvolvimento e testes](DESENVOLVIMENTO.md).

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.

---

[README](../README.md) · Próximo: [Bootstrap compartilhado](BOOTSTRAP.md)

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

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace:ro" composer:2 sh -lc "cp -R /workspace /tmp/package && cd /tmp/package && composer install --no-interaction --no-progress && composer check"
```

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.

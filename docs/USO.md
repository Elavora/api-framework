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
use Elavora\Api\Application;
use Elavora\Api\Http\Request;
use Elavora\Api\Http\Response;

$app = new Application();

$app->get('/health', static fn (): Response => Response::json([
    'status' => 'ok',
]));

$app->run(Request::fromGlobals());
```

## Principais pontos de entrada

- `Elavora\Api\Application`
- `Elavora\Api\Container`
- `Elavora\Api\Attributes\Cache`
- `Elavora\Api\Attributes\Details`
- `Elavora\Api\Attributes\Method`

## Dependencias de runtime

- `ext-json` `*`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use container:

```bash
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-framework" composer:2 composer validate --strict --no-check-publish
docker run --rm -v "${PWD}:/workspace" -w "/workspace/api-framework" composer:2 sh -lc "find . \\( -path ./.git -o -path ./vendor \\) -prune -o -name '*.php' -print0 | xargs -0 -r -n1 php -l"
```

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.
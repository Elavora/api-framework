# Elavora API Framework

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-framework.svg?style=flat-square)](https://packagist.org/packages/elavora/api-framework)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-framework.svg?style=flat-square)](https://packagist.org/packages/elavora/api-framework)
[![Composer Quality](https://github.com/Elavora/api-framework/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-framework/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-framework/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-framework/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-framework.svg?style=flat-square)](LICENSE)
Nucleo HTTP modular do framework Elavora para criar APIs pequenas, testaveis e extensiveis.

O pacote fornece roteamento, request/response, middleware, container simples, atributos de controller e contratos para modulos opcionais como cache, banco, filas, logs e storage.

## Instalacao

```bash
composer require elavora/api-framework
```

Requisitos:

- PHP 8.1 ou superior
- extensao `json`

## Aplicacao minima

```php
<?php

declare(strict_types=1);

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

require __DIR__ . '/vendor/autoload.php';

$app = Application::create();

$app->get('/health', static fn (): Response => Response::json([
    'status' => 'healthy',
]));

$response = $app->handle(Request::fromGlobals());
$app->emit($response);
```

## Rotas

Use os atalhos HTTP para registrar rotas:

```php
$app->get('/users', [UserController::class, 'index']);
$app->post('/users', [UserController::class, 'store']);
$app->put('/users', [UserController::class, 'update']);
$app->patch('/users', [UserController::class, 'patch']);
$app->delete('/users', [UserController::class, 'destroy']);
```

Tambem e possivel registrar uma rota com metodo dinamico:

```php
$app->route('POST', '/sessions', [SessionController::class, 'store']);
```

Handlers podem retornar `Response`, `array`, `string` ou objetos que implementam `Responseable`.

```php
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class UserController
{
    public function store(Request $request): Response
    {
        return Response::created([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
        ]);
    }
}
```

## Request

`Request` normaliza metodo, path, query string, corpo, headers e request id.

```php
$request->method();              // GET, POST, PUT, PATCH, DELETE...
$request->path();                // /users
$request->query('page', '1');    // parametro de query string
$request->input('email');        // campo do corpo JSON/form
$request->header('X-Request-Id');
$request->requestId();
```

Quando `X-Request-Id` nao e informado, o framework gera um identificador e o devolve na resposta.

## Response

Use os factories para manter respostas consistentes:

```php
Response::json(['ok' => true]);
Response::created(['id' => 123]);
Response::badRequest('Invalid payload', ['email' => 'Invalid field type']);
Response::notFound();
Response::internalServerError();
Response::text('ok');
```

Respostas sao imutaveis. Para ajustar headers ou corpo, use:

```php
$response = Response::json(['ok' => true])
    ->withHeader('X-App', 'Elavora');
```

## Middleware

Middlewares recebem a request e um callable `$next`, e devem retornar uma `Response`.

```php
$app->middleware(
    static fn (Request $request, callable $next): Response =>
        $next($request)->withHeader('X-Framework', 'Elavora')
);
```

## Atributos de controller

O framework inclui atributos para validar a request antes da action e para expor metadados em `OPTIONS`.

```php
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\RequiredParams;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class UserController
{
    #[Method('POST')]
    #[RequiredParams(['page' => 'int-string'])]
    #[RequiredFields(['name' => 'string', 'email' => 'email'])]
    public function store(Request $request): Response
    {
        return Response::created(['created' => true]);
    }
}
```

Atributos disponiveis:

- `Method`: restringe o metodo HTTP aceito.
- `RequiredFields`: valida campos obrigatorios do corpo.
- `OptionalFields`: descreve campos opcionais do corpo.
- `RequiredParams`: valida parametros obrigatorios da query string.
- `OptionalParams`: descreve parametros opcionais da query string.
- `Details`: adiciona metadados de descricao.
- `Response`: adiciona metadados de resposta esperada.
- `Cache`: usa um `CacheStore` registrado no container.
- `Transaction`: usa um `TransactionManager` registrado no container.

## Container e extensoes

O container permite registrar instancias usadas por controllers, atributos e extensoes.

```php
$app->container()->instance(LoggerInterface::class, $logger);
```

Pacotes opcionais podem implementar `Extension` para registrar dependencias:

```php
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\Extension;

final class ExampleExtension implements Extension
{
    public function register(Application $application): void
    {
        $application->container()->instance('example', new ExampleService());
    }
}

$app->extend(new ExampleExtension());
```

## Erros HTTP

Use `HttpException` quando precisar interromper o fluxo com uma resposta HTTP controlada.

```php
use Elavora\Api\Framework\Exceptions\HttpException;

throw HttpException::badRequest('Invalid payload', [
    'email' => 'Invalid field type',
]);
```

Erros inesperados retornam `500 Internal Server Error` sem expor detalhes sensiveis por padrao. Para desenvolvimento, crie a aplicacao com debug:

```php
$app = Application::create(debug: true);
```

## Testes

O pacote possui scripts Composer para validar sintaxe e executar a suite:

```bash
composer check
composer test
composer lint
```

## Licenca

MIT

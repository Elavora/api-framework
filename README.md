# Elavora API Framework

[![Packagist Version](https://img.shields.io/packagist/v/elavora/api-framework.svg?style=flat-square)](https://packagist.org/packages/elavora/api-framework)
[![PHP Version](https://img.shields.io/packagist/php-v/elavora/api-framework.svg?style=flat-square)](https://packagist.org/packages/elavora/api-framework)
[![Qualidade](https://github.com/Elavora/api-framework/actions/workflows/quality.yml/badge.svg?branch=main)](https://github.com/Elavora/api-framework/actions/workflows/quality.yml)
[![CodeQL](https://github.com/Elavora/api-framework/actions/workflows/codeql.yml/badge.svg?branch=main)](https://github.com/Elavora/api-framework/actions/workflows/codeql.yml)
[![License](https://img.shields.io/packagist/l/elavora/api-framework.svg?style=flat-square)](LICENSE)
Nucleo HTTP modular do framework Elavora para criar APIs pequenas, testaveis e extensiveis.

O pacote fornece roteamento, request/response, middleware, container simples, atributos de controller e contratos para modulos opcionais como cache, banco, filas, logs e storage.

## Instalacao

```bash
composer require elavora/api-framework
```

Requisitos:

- PHP 8.4 ou superior
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
$app->query('/users', [UserController::class, 'search']);
```

Tambem e possivel registrar uma rota com metodo dinamico:

```php
$app->route('POST', '/sessions', [SessionController::class, 'store']);
```

### Consultas com corpo (QUERY)

`QUERY` é um método HTTP seguro e idempotente que aceita corpo, pensado para consultas e filtros que não cabem bem
na query string (o RFC ainda está em andamento: "The HTTP QUERY Method"). Registre com `$app->query()` ou
`$app->route('QUERY', ...)` e leia o corpo com `$request->input()`:

```php
$app->query('/documents', static fn(Request $request): array => [
    'filter' => $request->input('filter'),
    'page' => $request->query('page', '1'),
]);
```

```js
await fetch('/documents?page=2', {
  method: 'QUERY',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ filter: { status: 'open' } }),
});
```

- `#[Method('QUERY')]`, `#[RequiredFields]` e `#[OptionalFields]` valem para o corpo; `#[RequiredParams]` e
  `#[OptionalParams]` continuam valendo para a query string.
- O corpo da `QUERY` é lido como JSON. Um corpo `application/x-www-form-urlencoded` não é decodificado (o PHP só
  preenche `$_POST` em `POST`), como em `PUT` e `PATCH`.
- Numa action por convenção (`/controller/action`), `QUERY` só vale com `#[Method]` listando `QUERY`.
- `QUERY` aparece no `Allow` do `405` e em `OPTIONS`. O framework não envia `Accept-Query`.
- O servidor e os proxies precisam deixar o método passar. O servidor embutido do PHP (`php -S`, testado no PHP 8.4)
  responde `501 Not Implemented` a `QUERY` antes de chegar ao framework. Em Apache, nginx ou proxy/CDN, confira que o
  método não é filtrado.

Detalhes em [Segurança do roteamento](docs/ROTEAMENTO.md#método-query).

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

### Rotas por convencao

O fallback `/controller/action` exige `#[Action]` no metodo exposto. O atributo
torna o opt-in HTTP explicito e impede que helpers publicos, metodos estaticos ou
herdados sejam publicados acidentalmente.

```php
use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Method;

final class UserController
{
    #[Action]
    #[Method('GET')]
    public function index(Request $request): Response
    {
        return Response::json(['users' => []]);
    }
}
```

Ao atualizar de uma versao anterior, adicione `#[Action]` a cada metodo que deve
continuar acessivel por convencao. Rotas registradas explicitamente nao exigem o
atributo.

As regras completas de segurança do roteamento estão em
[Segurança do roteamento](docs/ROTEAMENTO.md). Elas cobrem o formato da URL, quais classes e métodos podem ser
expostos, a resposta `404` quando uma regra falha, o log da recusa e os handlers aceitos por `Router::add`.

## Request

`Request` normaliza metodo, path, query string, corpo, headers e request id.

```php
$request->method();              // GET, POST, PUT, PATCH, DELETE, QUERY...
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

O framework inclui atributos para validar a request antes da action e para expor metadados em `OPTIONS`. Veja
[Documentação dos endpoints via `OPTIONS`](docs/DOCUMENTACAO-VIA-OPTIONS.md) para documentar a resposta com
`#[Details]`, `#[Response]` e `DescribesResponse`.

```php
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\RequiredParams;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;
use Elavora\Api\DataTypes\Email; // requer elavora/api-datatype-email

final class UserController
{
    #[Method('POST')]
    #[RequiredParams(['page' => 'int'])]
    #[RequiredFields(['name' => 'string', 'email' => Email::class])]
    public function store(Request $request): Response
    {
        return Response::created(['created' => true]);
    }
}
```

Atributos disponiveis:

- `Action`: autoriza a exposicao da action pelo roteamento por convencao.
- `Method`: restringe o metodo HTTP aceito.
- `OptionalFields`: descreve campos opcionais do corpo.
- `RequiredFields`: valida campos do corpo. A regra e um primitivo pelo nome (`string`, `int`, `float`, `bool`...), um DataType (classe ou instancia) ou um callable; formatos (e-mail, uuid, url...) vem do DataType, nao ha regra nomeada de formato.
- `RequiredParams`: valida parametros obrigatorios da query string. As regras `int`, `float` e `bool` aceitam o texto (`?limit=10`, `?price=1.5`, `?active=true`); no corpo JSON continuam estritas.
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

Fabricas disponiveis: `badRequest` (400), `unauthorized` (401), `forbidden` (403), `notFound` (404), `conflict` (409), `unprocessable` (422) e `internalServerError` (500). Todas aceitam a mensagem; `badRequest`, `conflict` e `unprocessable` aceitam tambem `errors`, e todas aceitam `headers`. Para outro status, use o construtor: `new HttpException(message: ..., status: 429)`.

Erros inesperados retornam `500 Internal Server Error` sem expor detalhes sensiveis por padrao. Para desenvolvimento, crie a aplicacao com debug:

```php
$app = Application::create(debug: true);
```

## Bootstrap para projetos Skeleton

Veja [bootstrap compartilhado](docs/BOOTSTRAP.md) para inicializar extensoes oficiais, rotas e customizacoes do projeto com configuracao atualizavel via Composer.

## Desenvolvimento e testes

O ambiente de desenvolvimento é a imagem `ghcr.io/elavora/php-dev:8.4-latest`, que acompanha a versão estável mais
recente da imagem. O CI usa a imagem padrão do pipeline central (detalhes em
[Ambiente de desenvolvimento e testes](docs/DESENVOLVIMENTO.md#o-que-roda-no-ci)). Você só precisa de
Docker: abra o repositório no VS Code com **Dev Containers: Reopen in Container**, ou rode no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.4-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

Scripts Composer disponíveis:

```bash
composer check   # lint, testes e PHPStan
composer test    # testes PHPUnit
composer lint    # sintaxe (elavora-standards lint, do elavora/code-standards)
```

O código segue o padrão PER-CS, com as regras do pacote `elavora/code-standards`. No devcontainer, o arquivo é
formatado ao salvar; no PR, o CI formata o que faltou e commita a correção (`style: organiza o código`).

O passo a passo está em [Ambiente de desenvolvimento e testes](docs/DESENVOLVIMENTO.md).

## Como contribuir

1. Crie uma branch a partir da `main` (`feat/...` ou `fix/...`).
2. Faça commits em português do Brasil, no padrão Conventional Commits (`feat: ...`, `fix: ...`, `docs: ...`).
3. Rode `composer check` antes de abrir o PR.
4. Abra o PR para a `main`, preencha o template e aplique as labels que definem a versão
   ([Qual label usar](docs/RELEASE.md#qual-label-usar)).
5. Confira o comentário do pipeline de qualidade no PR. Ele não bloqueia o merge, mas quem revisa olha o resultado.

Um guia de contribuição mais completo (`CONTRIBUTING.md`) será escrito no futuro.

## Versões

Cada PR integrado na `main` gera uma versão automaticamente, conforme as labels do PR: sem a label `release` sai uma
beta (`2.1.0-beta.1`), com ela sai a estável (`2.1.0`). As tags novas não têm o prefixo `v`, e as notas de cada
GitHub Release são geradas em português a partir dos PRs. Veja
[Versões e publicação](docs/RELEASE.md).

## Documentação

Leia nesta ordem:

1. [Guia de uso](docs/USO.md): instalação, quando usar e os principais pontos de entrada.
2. [Segurança do roteamento](docs/ROTEAMENTO.md): roteamento por convenção, recusas com `404`, log e handlers
   aceitos no registro de rotas.
3. [Exemplos de código](docs/EXEMPLOS.md): uma API de tarefas pequena, com controller, rota explícita, validação,
   DataType, atributo próprio, middleware, cache e transação. O código está em `examples/` e é testado.
4. [Documentação dos endpoints via `OPTIONS`](docs/DOCUMENTACAO-VIA-OPTIONS.md): o que cada attribute expõe,
   `#[Details]`, `#[Response]` e `DescribesResponse` para o objeto descrever a própria resposta.
5. [Injeção de serviços em controllers](docs/INJECAO-EM-CONTROLLERS.md): como entregar dependências a um controller
   com `container()->bind`.
6. [Bootstrap compartilhado](docs/BOOTSTRAP.md): inicialização padrão usada pelo API Skeleton.
7. [Ambiente de desenvolvimento e testes](docs/DESENVOLVIMENTO.md): devcontainer, como rodar os testes e o que roda
   no CI.
8. [Versões e publicação](docs/RELEASE.md): labels, betas, estáveis, Packagist e a transição das tags antigas.
9. [Automação do repositório](docs/AUTOMACAO.md): os workflows, o Dependabot, as labels e a referência de cada
   arquivo.

## Licenca

MIT

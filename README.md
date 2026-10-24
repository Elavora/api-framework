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

- PHP 8.3 ou superior
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

- `Action`: autoriza a exposicao da action pelo roteamento por convencao.
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

## Bootstrap para projetos Skeleton

Veja [bootstrap compartilhado](docs/BOOTSTRAP.md) para inicializar extensoes oficiais, rotas e customizacoes do projeto com configuracao atualizavel via Composer.

## Desenvolvimento e testes

O ambiente de desenvolvimento é a imagem `ghcr.io/elavora/php-dev:8.3-latest`, que acompanha a versão estável mais
recente da imagem. O CI usa a imagem padrão do pipeline central (detalhes em
[Ambiente de desenvolvimento e testes](docs/DESENVOLVIMENTO.md#o-que-roda-no-ci)). Você só precisa de
Docker: abra o repositório no VS Code com **Dev Containers: Reopen in Container**, ou rode no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
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
4. [Bootstrap compartilhado](docs/BOOTSTRAP.md): inicialização padrão usada pelo API Skeleton.
5. [Ambiente de desenvolvimento e testes](docs/DESENVOLVIMENTO.md): devcontainer, como rodar os testes e o que roda
   no CI.
6. [Versões e publicação](docs/RELEASE.md): labels, betas, estáveis, Packagist e a transição das tags antigas.
7. [Automação do repositório](docs/AUTOMACAO.md): os workflows, o Dependabot, as labels e a referência de cada
   arquivo.

## Licenca

MIT

# Exemplos de código

[README](../README.md) · Anterior: [Guia de uso](USO.md) · Próximo: [Bootstrap compartilhado](BOOTSTRAP.md)

Este documento mostra o framework em uso, com código que roda de verdade.

Os exemplos formam um projeto pequeno, na pasta [`examples/`](../examples). É uma API de tarefas, com a mesma
organização de um projeto criado pelo `api-skeleton`.

Cada seção tem duas partes:

1. uma explicação curta e o código do dia a dia;
2. no fim do documento, a [referência exata](#referência-exata): o que cada request devolve, campo por campo.

Todo bloco de código deste documento é cópia fiel de um arquivo de `examples/`. Os testes conferem isso e rodam os
exemplos. Por isso, um exemplo daqui nunca fica quebrado (veja
[Como os exemplos são testados](#como-os-exemplos-são-testados)).

## O projeto de exemplo

```
examples/
├── bootstrap.php                       cria a aplicação
└── app/                                namespace App\
    ├── Attributes/Permission.php       atributo próprio
    ├── DataTypes/ProjectCode.php       tipo de dado próprio
    ├── Extensions/InMemoryExtension.php
    ├── Http/Controller/TaskController.php
    ├── Http/Middleware/ApiVersionHeader.php
    └── Support/
        ├── InMemoryCacheStore.php
        └── InMemoryTransactionManager.php
```

O namespace `App\` aponta para `examples/app/`, como num projeto de verdade.

## Montar a aplicação

O `bootstrap.php` cria a aplicação e liga tudo: a extensão, o middleware e as rotas explícitas. Ele devolve a
aplicação pronta.

<!-- exemplo: examples/bootstrap.php -->
```php
<?php

declare(strict_types=1);

use App\Extensions\InMemoryExtension;
use App\Http\Controller\TaskController;
use App\Http\Middleware\ApiVersionHeader;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Response;

$app = Application::create();

$app->extend(new InMemoryExtension());
$app->middleware(new ApiVersionHeader());

$app->get('/health', static fn(): Response => Response::json(['status' => 'ok']));
$app->post('/tasks/archive', [TaskController::class, 'archive']);

return $app;
```

Num projeto, o `public/index.php` usa esse arquivo assim:
`$app = require __DIR__ . '/../bootstrap.php';` e depois `$app->emit($app->handle(Request::fromGlobals()));`.

## Controller com rota por convenção

O jeito mais simples de criar um endpoint é um controller com `#[Action]`. A URL `/controller/action` chega ao
método sem nenhum registro de rota.

- `/task` chama `TaskController::index` (a action padrão é `index`);
- `/task/store` chama `TaskController::store`.

Só os métodos com `#[Action]` são expostos. O `archive` não tem `#[Action]`, então `/task/archive` responde `404`.
As regras estão em [Rotas por convenção](../README.md#rotas-por-convencao).

<!-- exemplo: examples/app/Http/Controller/TaskController.php -->
```php
<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Attributes\Permission;
use App\DataTypes\ProjectCode;
use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Cache;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\Transaction;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class TaskController
{
    #[Action]
    #[Method('GET')]
    #[Cache(60)]
    public function index(Request $request): Response
    {
        return Response::json([
            'tasks' => [
                ['code' => 'APP-0001', 'title' => 'Escrever a documentação'],
            ],
        ]);
    }

    #[Action]
    #[Method('POST')]
    #[RequiredFields(['code' => ProjectCode::class, 'title' => 'string'])]
    #[Permission('tasks.write')]
    #[Transaction]
    public function store(Request $request): Response
    {
        $code = ProjectCode::from($request->input('code'));

        return Response::created([
            'code' => $code,
            'title' => $request->input('title'),
        ]);
    }

    public function archive(Request $request): Response
    {
        return Response::json(['archived' => true]);
    }
}
```

Os atributos em cima de cada método fazem o trabalho repetitivo:

- `#[Method('GET')]` aceita só `GET`. Outro método recebe `405`.
- `#[Cache(60)]` guarda a resposta por 60 segundos.
- `#[RequiredFields]` confere o corpo da request antes da action.
- `#[Permission]` é um atributo do próprio projeto (veja [Atributo próprio](#atributo-próprio)).
- `#[Transaction]` abre uma transação e faz commit se a resposta for de sucesso.

## Rota explícita

Quando a URL não segue a convenção, registre a rota no `bootstrap.php`:

- `$app->get('/health', ...)` usa uma função curta. É bom para respostas simples.
- `$app->post('/tasks/archive', [TaskController::class, 'archive'])` aponta para um método de controller.

A rota explícita não exige `#[Action]`. Quem registra já escolheu o método no código. Por isso o `archive` responde
em `/tasks/archive`, mas não em `/task/archive`.

## Validação dos dados

`#[RequiredFields]` recebe o nome de cada campo e a regra dele. A regra pode ser um nome pronto (`string`, `int`,
`email`, `uuid` e outros) ou uma classe de DataType.

No `store`, o campo `code` usa o DataType `ProjectCode`, e o `title` precisa ser texto. Se algo falhar, a resposta
é `400` e a action nem roda.

Para a query string, use `#[RequiredParams]`, do mesmo jeito.

## DataType

Um DataType é um valor com regra própria. Ele sabe dizer se um valor é válido (`isValid`) e cria o valor já
normalizado (`from`).

O `ProjectCode` aceita letras, um hífen e quatro números. Ele guarda tudo em maiúsculas: `app-1234` vira `APP-1234`.
Como implementa `Responseable`, ele vira texto no JSON da resposta.

<!-- exemplo: examples/app/DataTypes/ProjectCode.php -->
```php
<?php

declare(strict_types=1);

namespace App\DataTypes;

use Elavora\Api\Framework\Contracts\DataType;
use Elavora\Api\Framework\Contracts\Responseable;
use InvalidArgumentException;

final readonly class ProjectCode implements DataType, Responseable
{
    private function __construct(private string $value) {}

    public static function from(mixed $value): static
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException('ProjectCode deve usar o formato APP-1234.');
        }

        return new self(strtoupper((string) $value));
    }

    public static function isValid(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^[a-z]+-[0-9]{4}$/i', $value) === 1;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
```

## Atributo próprio

Um atributo próprio roda código antes da action. Basta implementar `BeforeRequestAttribute`.

Se `before` devolver uma `Response`, a action não roda e essa resposta vai para o cliente. Se devolver `null`, a
request segue.

O `Permission` confere o header `X-App-Permission`. Sem a permissão certa, a resposta é `403`. O método `options`
mostra a permissão exigida na resposta de `OPTIONS`.

<!-- exemplo: examples/app/Attributes/Permission.php -->
```php
<?php

declare(strict_types=1);

namespace App\Attributes;

use Attribute;
use Elavora\Api\Framework\Container;
use Elavora\Api\Framework\Contracts\BeforeRequestAttribute;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

#[Attribute(Attribute::TARGET_METHOD)]
final class Permission implements BeforeRequestAttribute
{
    public function __construct(private readonly string $required) {}

    public function before(Request $request, Container $container): ?Response
    {
        if ($request->header('X-App-Permission') === $this->required) {
            return null;
        }

        return Response::json(['message' => 'Permissão insuficiente.'], 403);
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return ['permission' => $this->required];
    }
}
```

## Middleware

Um middleware roda em toda request, inclusive nas que terminam em `404`. Ele recebe a request e o próximo passo
(`$next`), e devolve a resposta.

O `ApiVersionHeader` acrescenta o header `X-Api-Version: 1` em toda resposta.

<!-- exemplo: examples/app/Http/Middleware/ApiVersionHeader.php -->
```php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class ApiVersionHeader
{
    /**
     * @param callable(Request): Response $next
     */
    public function __invoke(Request $request, callable $next): Response
    {
        return $next($request)->withHeader('X-Api-Version', '1');
    }
}
```

## Cache e transação

`#[Cache]` e `#[Transaction]` não sabem onde guardar os dados. Eles usam o que estiver no container:

- `#[Cache]` usa um `CacheStore`;
- `#[Transaction]` usa um `TransactionManager`.

Num projeto, esses serviços vêm de um pacote, como o `api-cache-redis` ou o `api-database-pdo`. Nos exemplos, eles
guardam tudo em memória. Assim o exemplo roda sem Redis e sem banco.

A extensão registra os dois serviços no container. Uma extensão é o jeito padrão de um pacote se ligar à aplicação.

<!-- exemplo: examples/app/Extensions/InMemoryExtension.php -->
```php
<?php

declare(strict_types=1);

namespace App\Extensions;

use App\Support\InMemoryCacheStore;
use App\Support\InMemoryTransactionManager;
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Contracts\CacheStore;
use Elavora\Api\Framework\Contracts\Extension;
use Elavora\Api\Framework\Contracts\TransactionManager;

final class InMemoryExtension implements Extension
{
    public function register(Application $application): void
    {
        $application->container()->instance(CacheStore::class, new InMemoryCacheStore());
        $application->container()->instance(TransactionManager::class, new InMemoryTransactionManager());
    }
}
```

O cache em memória guarda cada valor com a hora em que ele vence. O método `count()` não faz parte do contrato: ele
existe para mostrar quantos itens estão guardados.

<!-- exemplo: examples/app/Support/InMemoryCacheStore.php -->
```php
<?php

declare(strict_types=1);

namespace App\Support;

use Elavora\Api\Framework\Contracts\CacheStore;

final class InMemoryCacheStore implements CacheStore
{
    /** @var array<string, array{value: mixed, expiresAt: ?int}> */
    private array $items = [];

    public function get(string $key): mixed
    {
        $item = $this->items[$key] ?? null;
        if ($item === null) {
            return null;
        }

        if ($item['expiresAt'] !== null && $item['expiresAt'] <= time()) {
            unset($this->items[$key]);

            return null;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->items[$key] = [
            'value' => $value,
            'expiresAt' => $ttlSeconds === null ? null : time() + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->items[$key]);
    }

    public function count(): int
    {
        return count($this->items);
    }
}
```

O gerenciador de transação em memória só anota o que aconteceu. O método `log()` não faz parte do contrato: ele
mostra a sequência de chamadas.

<!-- exemplo: examples/app/Support/InMemoryTransactionManager.php -->
```php
<?php

declare(strict_types=1);

namespace App\Support;

use Elavora\Api\Framework\Contracts\TransactionManager;

final class InMemoryTransactionManager implements TransactionManager
{
    /** @var list<string> */
    private array $log = [];

    public function begin(): bool
    {
        $this->log[] = 'begin';

        return true;
    }

    public function commit(): bool
    {
        $this->log[] = 'commit';

        return true;
    }

    public function rollback(): bool
    {
        $this->log[] = 'rollback';

        return true;
    }

    /**
     * @return list<string>
     */
    public function log(): array
    {
        return $this->log;
    }
}
```

## Referência exata

Esta seção diz exatamente o que os exemplos fazem. Os testes conferem cada linha.

### Requests

Em todos os casos, a aplicação é a devolvida por `require 'examples/bootstrap.php'`, e a request é criada com
`new Request(method: ..., path: ..., body: ..., headers: ...)`. Cada caso usa uma aplicação nova.

Toda resposta, inclusive as de erro, tem os headers `X-Api-Version: 1` e `X-Request-Id`. Toda resposta JSON com
status `400` ou maior também tem o campo `request_id` no corpo. As colunas de corpo abaixo não mostram o
`request_id`.

| # | Request | Status | Corpo JSON (sem `request_id`) | Outros efeitos |
| --- | --- | --- | --- | --- |
| 1 | `GET /health` | 200 | `{"status": "ok"}` | |
| 2 | `GET /task` | 200 | `{"tasks": [{"code": "APP-0001", "title": "Escrever a documentação"}]}` | o `CacheStore` passa a ter 1 item |
| 3 | `GET /task`, duas vezes na mesma aplicação | 200 nas duas | igual ao caso 2 nas duas | o `CacheStore` continua com 1 item |
| 4 | `GET /task/index` | 200 | igual ao caso 2 | |
| 5 | `POST /task/store`, corpo `{"code": "app-1234", "title": "Revisar"}`, header `X-App-Permission: tasks.write` | 201 | `{"code": "APP-1234", "title": "Revisar"}` | log da transação: `["begin", "commit"]` |
| 6 | igual ao 5, sem o header `X-App-Permission` | 403 | `{"message": "Permissão insuficiente."}` | log da transação: `[]` |
| 7 | igual ao 5, com `X-App-Permission: tasks.read` | 403 | `{"message": "Permissão insuficiente."}` | log da transação: `[]` |
| 8 | igual ao 5, com corpo `{"code": "1234", "title": "Revisar"}` | 400 | `{"message": "Invalid fields", "errors": {"fields": {"code": "Invalid field type"}}}` | log da transação: `[]` |
| 9 | igual ao 5, com corpo `{"code": "app-1234"}` | 400 | `{"message": "Invalid fields", "errors": {"fields": {"title": "Field not found"}}}` | log da transação: `[]` |
| 10 | igual ao 8, sem o header `X-App-Permission` | 400 | igual ao caso 8 | a validação roda antes do `Permission` |
| 11 | `GET /task/store` | 405 | `{"message": "Method GET is not allowed for this endpoint."}` | header `Allow: POST` |
| 12 | `POST /tasks/archive` | 200 | `{"archived": true}` | |
| 13 | `POST /task/archive` | 404 | `{"message": "Not Found"}` | sem `#[Action]` |
| 14 | `GET /tasks` | 404 | `{"message": "Not Found"}` | não existe `TasksController` |

O "log da transação" é o valor de `log()` do `TransactionManager` do container da aplicação, depois da request. O
número de itens do cache é o valor de `count()` do `CacheStore` do container.

### Ordem dos atributos

Numa action, o framework roda os atributos nesta ordem:

1. os de validação (`Method`, `RequiredFields`, `RequiredParams`), na ordem em que aparecem;
2. os de antes da action (`Permission`, `Cache`, `Transaction`), na ordem em que aparecem;
3. a action;
4. os de depois da resposta, na ordem inversa.

Por isso, no `store`, o `Permission` vem antes do `Transaction`. Uma request sem permissão para no `Permission`, e a
transação nem começa (casos 6 e 7). E uma request com o corpo errado recebe `400` mesmo sem permissão (caso 10).

### `ProjectCode`

| Chamada | Resultado |
| --- | --- |
| `ProjectCode::isValid('APP-1234')` | `true` |
| `ProjectCode::isValid('app-1234')` | `true` |
| `ProjectCode::isValid('APP-123')` | `false` |
| `ProjectCode::isValid('APP1234')` | `false` |
| `ProjectCode::isValid('1234')` | `false` |
| `ProjectCode::isValid(1234)` | `false` |
| `ProjectCode::isValid(null)` | `false` |
| `ProjectCode::from('app-1234')->value()` | `'APP-1234'` |
| `json_encode(ProjectCode::from('abc-0001'))` | `'"ABC-0001"'` |
| `ProjectCode::from('APP-12')` | lança `InvalidArgumentException` com a mensagem `ProjectCode deve usar o formato APP-1234.` |

### `InMemoryCacheStore`

| Passos | Resultado |
| --- | --- |
| `get('a')` num cache vazio | `null` |
| `set('a', 1)` e `get('a')` | `1` |
| `set('a', 1, 60)` e `get('a')` | `1` |
| `set('a', 1, 0)` e `get('a')` | `null`, e `count()` passa a `0` |
| `set('a', 1)`, `delete('a')` e `get('a')` | `null` |
| `set('a', 1)` e `set('b', 2)` | `count()` é `2` |

### `InMemoryTransactionManager`

| Passos | Resultado |
| --- | --- |
| nenhum | `log()` é `[]` |
| `begin()`, `rollback()` | `log()` é `['begin', 'rollback']`, e cada chamada devolve `true` |

## Como os exemplos são testados

A regra é simples: **o código de um exemplo existe uma vez só, num arquivo de `examples/`**. A doc mostra uma cópia
desse arquivo, e um teste garante que a cópia é igual.

### Marcador

Cada bloco de código de exemplo tem, na linha logo antes da abertura do bloco, um comentário HTML com o caminho do
arquivo. Por exemplo, o bloco do `ProjectCode` vem logo depois desta linha:
`<!-- exemplo: examples/app/DataTypes/ProjectCode.php -->`. O comentário não aparece quando o Markdown é exibido.

O teste confere, neste documento (`docs/EXEMPLOS.md`):

1. Todo bloco que abre com ` ```php ` tem o marcador na linha logo antes.
2. Todo marcador aponta para um arquivo que existe, com caminho relativo à raiz do repositório.
3. O conteúdo do bloco (as linhas entre a abertura e o fechamento, sem elas) mais uma quebra de linha final é
   igual, byte a byte, ao conteúdo do arquivo. Antes de comparar, o teste troca `\r\n` por `\n` nos dois lados,
   para funcionar também num checkout do Windows.
4. Todo arquivo `.php` dentro de `examples/`, em qualquer pasta, tem exatamente um marcador.

O formato do marcador é exato: `<!-- exemplo: ` + caminho + ` -->`, sozinho na linha.

Um marcador só vale quando está sozinho na linha. O marcador citado entre crases no parágrafo acima não conta.

Blocos sem a linguagem `php` (como a árvore de pastas) não são conferidos.

Exemplos de falha:

| Situação | O que o teste aponta |
| --- | --- |
| alguém muda o `ProjectCode.php` e esquece a doc | o bloco difere do arquivo |
| alguém cria `examples/app/Support/Clock.php` sem bloco na doc | arquivo sem marcador |
| alguém cola um bloco ` ```php ` novo sem marcador | bloco sem marcador |
| o marcador aponta para `examples/app/Nada.php` | arquivo inexistente |

### Comportamento

Um teste PHPUnit roda cada linha da [Referência exata](#referência-exata). Se um exemplo quebrar, o teste falha, e
o PR não passa no CI.

### Configuração

Para os exemplos rodarem nos testes e passarem pelas mesmas checagens do código:

- `composer.json` ganha, em `autoload-dev.psr-4`, a entrada `"App\\": "examples/app/"`. A entrada
  `"Elavora\\Api\\Framework\\Tests\\": "tests/"` continua.
- `phpstan.neon` ganha `examples` em `paths`, ao lado de `src`. Os exemplos passam no mesmo nível do código.
- Os exemplos seguem o estilo de código do repositório (PER-CS) e passam no `composer lint`.
- Os exemplos não são carregados em produção: o namespace `App\` só está no `autoload-dev`.

As fixtures dos testes também usam o namespace `App\` (em `tests/Fixtures/App/`). Uma classe de exemplo não pode
ter o mesmo nome completo de uma fixture.

Se o PHP-CS-Fixer mudar a formatação de um exemplo, a doc precisa ser atualizada para ficar igual ao arquivo.

## Como acrescentar um exemplo

1. Escreva a explicação e o bloco de código aqui, com o marcador.
2. Acrescente o comportamento esperado na [Referência exata](#referência-exata).
3. Crie o arquivo em `examples/`, igual ao bloco.
4. Acrescente o teste do comportamento.

Os passos seguem o fluxo da organização: primeiro a doc, depois o teste, depois o código.

## O que não está aqui

- **Worker de fila.** O framework só define o contrato `Queue`. O worker e o `TaskHandler` vêm do pacote
  `api-queue-worker`, e o exemplo de worker fica na documentação dele.
- **Log, banco e cache reais.** Cada pacote (`api-log-*`, `api-database-*`, `api-cache-*`) documenta o próprio uso.

---

[README](../README.md) · Anterior: [Guia de uso](USO.md) · Próximo: [Bootstrap compartilhado](BOOTSTRAP.md)

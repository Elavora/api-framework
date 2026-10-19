# Segurança do roteamento

Este documento é o contrato de segurança do roteamento. Ele cobre duas coisas:

- o roteamento por convenção, que transforma a URL `/controller/action` em uma classe e um método;
- o registro de rotas explícitas com `Router::add` (e os atalhos `$app->get`, `$app->post` etc.).

O roteamento por convenção continua no framework. Ele é mais prático para quem desenvolve do que um arquivo de rotas.
Mas a URL vem de fora, de quem faz a request. Por isso ela só pode chegar a código que o projeto marcou como
público.

## Visão geral

A request passa por estas etapas, nesta ordem:

1. O router procura uma rota explícita para o método e o path. Se achar, ela é executada. Rotas explícitas sempre
   têm prioridade.
2. Se o path tem rota explícita para outro método, a resposta é `405` (ou a resposta de `OPTIONS`), como hoje.
3. Se não houver rota explícita, entra o roteamento por convenção. Ele valida o path, a classe e o método. Todas as
   regras abaixo precisam passar.
4. Se alguma regra falhar, a resposta é `404 Not Found`. Nada é executado.

## Roteamento por convenção

### Namespace e sufixo

O `ConventionRouteResolver` recebe três valores no construtor. Eles são fixos: vêm do código, nunca da request.

| Parâmetro | Padrão | Uso |
| --- | --- | --- |
| `controllerNamespace` | `App\Http\Controller` | namespace onde ficam os controllers |
| `controllerSuffix` | `Controller` | sufixo do nome da classe |
| `defaultAction` | `index` | action usada quando a URL só tem o controller |

O nome da classe é sempre montado assim: namespace + `\` + segmento do controller com a primeira letra maiúscula +
sufixo. Exemplo: `/user/show` vira `App\Http\Controller\UserController::show`.

### Regras do path

O path é o valor de `Request::path()`. Ele não é decodificado: `%2F`, `%5C` e `%2E` ficam como texto e são
recusados pela regra do segmento.

O path precisa ter um ou dois segmentos, separados por `/`:

- `/controller` usa a action padrão (`index`);
- `/controller/action` usa a action informada.

A barra final é ignorada (`/user/show/` é igual a `/user/show`), como em todo o framework.

Cada segmento precisa seguir esta regra:

- começa com uma letra minúscula, de `a` a `z`;
- depois, só letras (`a` a `z`, `A` a `Z`) e números (`0` a `9`);
- tem no máximo 64 caracteres.

A expressão regular é `^[a-z][A-Za-z0-9]{0,63}$`.

Isso impede `\`, `..`, `.`, `_`, `-`, espaços, letras com acento e qualquer texto codificado com `%`. Também impede
nomes que começam com `_`, como os métodos mágicos (`__construct`, `__invoke`).

Um path com segmento vazio no meio, como `/user//show`, é recusado. Antes ele era aceito, porque os segmentos vazios
eram descartados.

| Path | Classe e método | Resultado |
| --- | --- | --- |
| `/user` | `UserController::index` | segue para as regras da classe |
| `/user/show` | `UserController::show` | segue |
| `/user/show/` | `UserController::show` | segue |
| `/user/listActive` | `UserController::listActive` | segue |
| `/report2024/show` | `Report2024Controller::show` | segue |
| `/` | | 404: nenhum segmento |
| `/user/show/1` | | 404: três segmentos |
| `/user//show` | | 404: segmento vazio |
| `/User/show` | | 404: começa com maiúscula |
| `/user/Show` | | 404: começa com maiúscula |
| `/user/SHOW` | | 404: começa com maiúscula |
| `/user_admin/show` | | 404: `_` não é permitido |
| `/user-admin/show` | | 404: `-` não é permitido |
| `/user/__construct` | | 404: começa com `_` |
| `/user/__invoke` | | 404: começa com `_` |
| `/../user/show` | | 404: `..` não é permitido |
| `/user/..` | | 404: `..` não é permitido |
| `/admin%5Cuser/show` | | 404: `%` não é permitido |
| `/admin\user/show` | | 404: `\` não é permitido |
| `/2fa/show` | | 404: começa com número |
| `/usuário/show` | | 404: letra com acento |
| `/user/` seguido de um segmento com 65 caracteres | | 404: segmento longo demais |

Quando o primeiro caractere é minúsculo, há só uma URL para cada action. `/User/show` e `/user/SHOW` não chegam ao
mesmo método que `/user/show`.

### Regras da classe

A classe montada a partir do path precisa cumprir todas estas regras:

1. **Existe.** O framework procura só o nome montado, que fica sempre dentro do namespace fixo. O autoload, se rodar,
   roda só para esse nome. Nenhum nome de fora do namespace é procurado.
2. **Tem o nome exato.** O nome declarado da classe é igual ao nome montado, com as mesmas maiúsculas e minúsculas.
   O PHP acha classes sem diferenciar maiúsculas. Esta regra evita que `/userAdmin` chegue à classe
   `UseradminController`.
3. **É uma classe concreta e instanciável.** Não é interface, trait, enum nem classe abstrata. O construtor é
   público.
4. **Pode ser criada.** Ou o container tem um registro com o nome exato da classe, ou o construtor não tem
   parâmetros obrigatórios.

O que torna uma classe um controller é a combinação de três coisas: estar no namespace fixo, ter o sufixo e ter pelo
menos a action pedida marcada com `#[Action]` (veja abaixo). O framework não pede interface nem classe base.

| Classe em `App\Http\Controller` | Path | Resultado |
| --- | --- | --- |
| `final class UserController` | `/user/show` | segue para as regras da action |
| `UserController` registrada no container, com construtor que recebe um serviço | `/user/show` | segue |
| nenhuma classe `ReportController` | `/report/show` | 404 |
| `abstract class BaseController` | `/base/show` | 404: abstrata |
| `interface AuditController` | `/audit/show` | 404: interface |
| `trait SharedController` | `/shared/show` | 404: trait |
| `enum StatusController` | `/status/show` | 404: enum |
| `UseradminController` | `/userAdmin/show` | 404: o nome montado é `UserAdminController` |
| `PrivateController` com construtor `private` | `/private/show` | 404: não instanciável |
| `ServiceController` com construtor `__construct(Mailer $mailer)`, fora do container | `/service/show` | 404: não pode ser criada |

### Regras da action

O método precisa cumprir todas estas regras:

1. **Existe** na classe.
2. **Tem o nome exato.** O nome declarado é igual ao segmento, com as mesmas maiúsculas e minúsculas.
3. **É público.**
4. **Não é estático.**
5. **Não é construtor nem destrutor**, e o nome não começa com `__`.
6. **Foi declarado na própria classe controller.** Não pode ser herdado de uma classe pai, de dentro ou de fora do
   namespace.
7. **Foi escrito no corpo da classe.** Não pode vir de um trait.
8. **Tem o atributo `#[Action]`** (`Elavora\Api\Framework\Attributes\Action`).

Exemplo com este controller:

```php
namespace App\Http\Controller;

use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class UserController extends BaseController
{
    use ExportActions; // o trait tem um método export() com #[Action]

    #[Action]
    public function show(Request $request): Response
    {
        return Response::json(['id' => 1]);
    }

    #[Action]
    public function listActive(Request $request): Response
    {
        return Response::json([]);
    }

    public function helper(Request $request): Response {}

    #[Action]
    public static function build(Request $request): Response {}

    #[Action]
    protected function audit(Request $request): Response {}

    #[Action]
    private function secret(Request $request): Response {}
}
```

`BaseController` é uma classe pai com um método público `inherited()` marcado com `#[Action]`.

| Path | Resultado | Por quê |
| --- | --- | --- |
| `/user/show` | executa `show` | todas as regras passam |
| `/user/listActive` | executa `listActive` | todas as regras passam |
| `/user/listactive` | 404 | o nome declarado é `listActive` |
| `/user/missing` | 404 | o método não existe |
| `/user/helper` | 404 | sem `#[Action]` |
| `/user/build` | 404 | estático |
| `/user/audit` | 404 | protegido |
| `/user/secret` | 404 | privado |
| `/user/inherited` | 404 | herdado da classe pai |
| `/user/export` | 404 | vem de um trait |
| `/user` | 404 | não existe `index` |

As regras de atributos continuam depois da resolução. Por exemplo, uma action com `#[Method('GET')]` chamada com
`POST` responde `405`, como hoje.

### Quando uma regra falha

A resposta é a mesma de um path que não existe:

- status `404`;
- corpo `{"message": "Not Found", "request_id": "<id da request>"}`;
- header `X-Request-Id`.

A resposta nunca diz qual regra falhou. Ela não cita a classe, o método nem o namespace. Assim, quem faz a request
não consegue descobrir quais classes e métodos existem.

Isso vale também para `OPTIONS` e `HEAD`. Um `OPTIONS` num path recusado responde `404`, sem metadados.

Nenhum código do projeto roda numa recusa: nenhum construtor, nenhum atributo e nenhuma action.

### O que vai para o log

O motivo da recusa vai para o log, e só para o log.

O log só acontece se o container da aplicação tiver um `Elavora\Api\Framework\Logging\Logger` registrado com o
nome `Logger::class`. Sem ele, a recusa não gera log, e nada falha.

O registro é assim:

- nível `debug`;
- mensagem `Convention route not resolved`;
- contexto com duas chaves: `path` e `reason`.

O `path` é o path da request, cortado em 200 caracteres. O `reason` é um destes códigos:

| `reason` | Quando |
| --- | --- |
| `invalid_path` | o path falha nas regras do path |
| `class_not_found` | a classe não existe ou não tem o nome exato |
| `class_not_instantiable` | a classe falha na regra 3 ou 4 da classe |
| `action_not_found` | o método não existe ou não tem o nome exato |
| `action_not_allowed` | o método existe, mas falha numa das regras de 3 a 8 da action |

O nível é `debug` porque robôs testam URLs o tempo todo. Um nível mais alto encheria o log com ruído.

O log não inclui headers, corpo, query string nem a mensagem de exceções.

Exemplo: `GET /user/helper`, com um `Logger` no container, faz o `LogWriter` receber esta entrada:

```php
[
    'timestamp' => '<data>',
    'level' => 'debug',
    'message' => 'Convention route not resolved',
    'request_id' => '<id da request>',
    'context' => ['path' => '/user/helper', 'reason' => 'action_not_allowed'],
]
```

Uma recusa gera exatamente uma entrada de log. Uma request resolvida não gera nenhuma.

### API pública

```php
namespace Elavora\Api\Framework\Routing;

final class ConventionRouteResolver
{
    public function __construct(
        string $controllerNamespace = 'App\\Http\\Controller',
        string $controllerSuffix = 'Controller',
        string $defaultAction = 'index',
        ?\Elavora\Api\Framework\Container $container = null,
    );

    /** @return array{class-string, string}|null */
    public function resolve(\Elavora\Api\Framework\Http\Request $request): ?array;
}
```

- `resolve()` devolve `[Classe::class, 'action']` quando todas as regras passam, e `null` quando alguma falha. Ele
  nunca lança exceção por causa da URL.
- `resolve()` não cria a classe e não chama a action.
- `$container` é novo e opcional. Ele é usado na regra 4 da classe e para achar o `Logger`. Sem container, a regra 4
  aceita só classes com construtor sem parâmetros obrigatórios, e não há log.
- A `Application` passa o próprio container. O `Logger` é procurado no momento da request, e não na criação da
  aplicação. Assim, um `Logger` registrado depois, por uma extensão, também vale.

## Registro de rotas explícitas

### Handlers aceitos

`Router::add` (e `Application::route`, `get`, `post`, `put`, `patch`, `delete`) aceita só estes handlers:

1. **Uma `Closure`.** Exemplo: `fn (Request $r) => Response::json([])`.
2. **Um objeto com `__invoke`.** Exemplo: `new HealthCheck()`.
3. **Um par `[Classe::class, 'metodo']`.** É um array com exatamente dois itens, nas chaves `0` e `1`, os dois
   texto.
4. **Um par `[$objeto, 'metodo']`**, em que `$objeto` é um objeto e o método é público.

Qualquer outro valor é recusado já no registro, com `InvalidArgumentException`. Antes, o handler inválido só falhava
na hora da request, com erro `500`.

Regras do par `[Classe::class, 'metodo']`:

- a classe existe;
- o método existe, com o nome exato;
- o método é público e não é estático;
- o método não é construtor nem destrutor, e o nome não começa com `__`.

Rotas explícitas **não** exigem `#[Action]`. Quem registra a rota já escolheu o método no código. A classe também não
precisa estar no namespace dos controllers, e o método pode ser herdado ou vir de um trait.

O registro não confere se a classe pode ser criada. O container pode ganhar o registro dela depois, por exemplo numa
extensão.

| Handler | Resultado |
| --- | --- |
| `fn (Request $r) => []` | aceito |
| `new HealthCheck()` (tem `__invoke`) | aceito |
| `[UserController::class, 'show']` | aceito |
| `[UserController::class, 'helper']` (sem `#[Action]`) | aceito |
| `[UserController::class, 'inherited']` (herdado) | aceito |
| `[new UserController(), 'show']` | aceito |
| `'phpinfo'` | recusado: texto não é handler |
| `'App\Http\Controller\UserController::show'` | recusado: texto não é handler |
| `[UserController::class]` | recusado: falta o método |
| `[UserController::class, 'show', 'extra']` | recusado: três itens |
| `['class' => UserController::class, 'method' => 'show']` | recusado: chaves erradas |
| `[MissingController::class, 'show']` | recusado: a classe não existe |
| `[UserController::class, 'missing']` | recusado: o método não existe |
| `[UserController::class, 'secret']` | recusado: privado |
| `[UserController::class, 'audit']` | recusado: protegido |
| `[UserController::class, 'build']` | recusado: estático |
| `[UserController::class, '__construct']` | recusado: construtor |
| `[UserController::class, '__invoke']` | recusado: começa com `__` |
| `[new UserController(), 'secret']` | recusado: privado |
| `null`, `42`, `true`, `new stdClass()` | recusado |

A mensagem da exceção diz o método HTTP, o path e o motivo. Exemplo:
`Handler invalido para GET /users: o metodo UserController::secret nao e publico.` Essa mensagem é para quem
desenvolve: ela aparece no boot da aplicação, e não numa resposta HTTP. O texto exato não faz parte do contrato.
O contrato é a classe da exceção e o momento em que ela acontece: dentro da chamada a `add`.

Uma recusa não registra a rota. O router fica como estava antes da chamada.

### API pública

```php
namespace Elavora\Api\Framework\Routing;

final class Router
{
    /**
     * @throws \InvalidArgumentException quando o handler não é aceito.
     */
    public function add(string|\Elavora\Api\Framework\Http\HttpMethod $method, string $path, mixed $handler): void;
}
```

`Application::route` e os atalhos repassam a mesma exceção.

## Mudanças de comportamento

Estas mudanças entram como correção de segurança. Quem usava algum destes casos precisa ajustar o projeto:

| Antes | Agora | Como ajustar |
| --- | --- | --- |
| `/User/show` e `/user/SHOW` chegavam a `UserController::show` | 404 | use a URL com a primeira letra minúscula e o nome exato do método |
| `/user_admin/show` chegava a `User_adminController` | 404 | renomeie a classe para `UserAdminController` e use `/userAdmin/show`, ou registre uma rota explícita |
| `/user//show` chegava a `UserController::show` | 404 | remova a barra repetida |
| métodos de trait com `#[Action]` eram expostos | 404 | declare o método no controller, ou registre uma rota explícita |
| controller com construtor de parâmetros obrigatórios, fora do container, dava `500` | 404 | registre o controller no container |
| handler inválido em `add` dava `500` na request | `InvalidArgumentException` no registro | corrija o handler |
| handler em texto (`'Classe::metodo'`, nome de função) era aceito | `InvalidArgumentException` no registro | use `[Classe::class, 'metodo']` ou uma `Closure` |

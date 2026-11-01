# Injeção de serviços em controllers

[README](../README.md) · Relacionado: [Segurança do roteamento](ROTEAMENTO.md) · [Exemplos de código](EXEMPLOS.md)

Este guia mostra como entregar serviços (um repositório, um logger, um cliente HTTP) a um controller.

## A regra em uma frase

O framework cria o controller sozinho, **sem argumentos**. Se o controller precisa de dependências, você registra no
container uma fábrica para a classe dele. Sem fábrica, o `new` sem argumentos é o único caminho.

## Como o framework obtém o controller

Na hora de executar uma action (por convenção ou por rota explícita `[Controller::class, 'metodo']`), o framework
faz:

1. Pergunta ao container se há um registro para a classe do controller (`$container->has(Controller::class)`).
2. Se há, usa `$container->get(Controller::class)`.
3. Se não há, faz `new Controller()`, sem nenhum argumento.

Consequência: um controller com construtor que exige parâmetros, sem registro no container, falha com `ArgumentCountError`
e a resposta é `500`. Isso acontece só quando a rota é executada, não no registro da rota.

Dois detalhes importantes do container:

- `bind($id, $fabrica)` guarda uma função que recebe o próprio container e devolve o objeto.
- A fábrica roda **na primeira vez** que alguém pede o controller. O objeto devolvido é guardado, e as próximas
  requests no mesmo processo recebem **o mesmo objeto**. Por isso, não guarde dados de uma request em propriedades do
  controller.

No roteamento por convenção, o container só é consultado depois que o path passou por todas as regras de segurança
(veja [Segurança do roteamento](ROTEAMENTO.md)). Registrar um controller no container não o torna acessível pela
URL: ele continua precisando cumprir as regras de namespace, sufixo e `#[Action]`.

## Passo a passo

### 1. O serviço

```php
<?php

declare(strict_types=1);

namespace App\Support;

final class TaskRepository
{
    /** @return list<array{code: string, title: string}> */
    public function all(): array
    {
        return [['code' => 'APP-0001', 'title' => 'Escrever a documentação']];
    }
}
```

### 2. O controller recebe o serviço no construtor

```php
<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Support\TaskRepository;
use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

final class TaskController
{
    public function __construct(private readonly TaskRepository $tasks) {}

    #[Action]
    public function index(Request $request): Response
    {
        return Response::json(['tasks' => $this->tasks->all()]);
    }
}
```

### 3. Registre a fábrica no container

Faça isso no ponto de bootstrap do projeto, antes de a aplicação receber requests. Num projeto criado pelo
`api-skeleton`, esse ponto é `HttpRoutes::register`; em qualquer projeto serve o lugar onde você monta a `Application`.

```php
use App\Http\Controller\TaskController;
use App\Support\TaskRepository;
use Elavora\Api\Framework\Application;

$app = Application::create();

$app->container()->bind(
    TaskController::class,
    static fn($container): TaskController => new TaskController(new TaskRepository()),
);
```

Se o serviço já está no container, peça-o à fábrica em vez de criar outro:

```php
$app->container()->instance(TaskRepository::class, new TaskRepository());

$app->container()->bind(
    TaskController::class,
    static fn($container): TaskController => new TaskController($container->get(TaskRepository::class)),
);
```

`$container->get()` lança `RuntimeException` se o serviço não foi registrado. Registre o serviço antes de a primeira
request chegar ao controller; a ordem entre o `bind` do serviço e o `bind` do controller não importa, porque a fábrica
só roda na primeira request.

### 4. Funciona nos dois tipos de rota

```php
// Roteamento por convenção: GET /task/index (ou GET /task)
// Rota explícita:
$app->get('/tarefas', [TaskController::class, 'index']);
```

As duas usam a fábrica registrada.

## Exemplo completo, que você pode rodar

Salve como `exemplo.php` numa pasta com `composer require elavora/api-framework` instalado. Ele não precisa de
servidor web: cria a request na mão.

```php
<?php

declare(strict_types=1);

namespace App\Support {
    final class TaskRepository
    {
        /** @return list<array{code: string, title: string}> */
        public function all(): array
        {
            return [['code' => 'APP-0001', 'title' => 'Escrever a documentação']];
        }
    }
}

namespace App\Http\Controller {
    use App\Support\TaskRepository;
    use Elavora\Api\Framework\Attributes\Action;
    use Elavora\Api\Framework\Http\Request;
    use Elavora\Api\Framework\Http\Response;

    final class TaskController
    {
        public function __construct(private readonly TaskRepository $tasks) {}

        #[Action]
        public function index(Request $request): Response
        {
            return Response::json(['tasks' => $this->tasks->all()]);
        }
    }
}

namespace {
    use App\Http\Controller\TaskController;
    use App\Support\TaskRepository;
    use Elavora\Api\Framework\Application;
    use Elavora\Api\Framework\Http\Request;

    require __DIR__ . '/vendor/autoload.php';

    $app = Application::create();

    $app->container()->instance(TaskRepository::class, new TaskRepository());
    $app->container()->bind(
        TaskController::class,
        static fn($container): TaskController => new TaskController($container->get(TaskRepository::class)),
    );
    $app->get('/tarefas', [TaskController::class, 'index']);

    $porConvencao = $app->handle(new Request(method: 'GET', path: '/task'));
    $porRotaExplicita = $app->handle(new Request(method: 'GET', path: '/tarefas'));

    echo $porConvencao->status(), ' ', $porConvencao->body(), PHP_EOL;
    echo $porRotaExplicita->status(), ' ', $porRotaExplicita->body(), PHP_EOL;
}
```

Saída esperada (o `X-Request-Id` fica nos headers, não no corpo):

```text
200 {"tasks":[{"code":"APP-0001","title":"Escrever a documentação"}]}
200 {"tasks":[{"code":"APP-0001","title":"Escrever a documentação"}]}
```

## O que acontece sem a fábrica

Se você remover o `bind` do exemplo acima, `GET /task` chama `new TaskController()` sem argumentos. Como o construtor
exige um `TaskRepository`, a request responde `500 Internal Server Error` (com `debug: true`, a mensagem da exceção
aparece no corpo).

## Resumo

| Situação | O que o framework faz |
| --- | --- |
| Controller sem construtor, sem registro | `new Controller()` |
| Controller com dependências, com `bind` | usa a fábrica, uma vez, e reaproveita o objeto |
| Controller com dependências, sem `bind` | `new Controller()` falha: `500` |
| `instance(Controller::class, $obj)` | usa o objeto pronto |
| Classe fora das regras da convenção | `404`, o controller nem é criado |

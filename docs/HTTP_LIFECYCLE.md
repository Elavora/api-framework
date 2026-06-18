# HTTP Lifecycle

O framework processa toda requisição HTTP através de um ciclo bem definido. Entender esse ciclo é fundamental para usar middleware, atributos e extensões corretamente.

## O Ciclo Completo

```
1. Request::fromGlobals()          ← Cria objeto Request
2. Application::handle()           ← Inicia o kernel
3. HttpKernel::handle()            ← Núcleo do processamento
4. Middleware BEFORE               ← Executado antes
5. Router::match()                 ← Busca rota
6. ControllerResolver::resolve()   ← Resolve controller/closure
7. Execução da ação                ← Seu código
8. Middleware AFTER                ← Executado depois
9. ResponseEmitter::emit()         ← Envia resposta
```

## Detalhes de Cada Etapa

### 1. Request::fromGlobals()

Converte variáveis superglobais PHP em um objeto `Request` imutável:

```php
use Elavora\Api\Http\Request;

$request = Request::fromGlobals();
// Extrai de $_GET, $_POST, $_SERVER, etc
```

### 2. Application::create()

Inicializa o container e registra rotas:

```php
$app = Application::create();

// Registra rotas
$app->get('/users', 'UsersController@list');
$app->post('/users', 'UsersController@create');

// Registra middleware
$app->middleware(AuthMiddleware::class);

// Inicia processamento
$response = $app->handle($request);
```

### 3. HttpKernel::handle()

É o coração do processamento:

```
kernel->handle(request)
  ├─ beforeMiddleware.handle()
  ├─ router.match() → encontra rota
  ├─ resolver.resolve() → executa controller
  ├─ afterMiddleware.handle()
  └─ retorna Response
```

### 4. Middleware BEFORE

Executa **antes** do controller:

```php
class LogMiddleware implements BeforeRequestAttribute {
    public function before(Request $request): ?Response {
        echo "Requisição: " . $request->method() . " " . $request->path();
        return null; // null = continua para o controller
    }
}
```

Pode:
- ✅ Modificar o request
- ✅ Retornar Response para pular o controller (early exit)
- ✅ Fazer logging, validação, autenticação

### 5. Router::match()

Busca a rota que corresponde à requisição:

```
Entrada: GET /users/123
Saída: Rota encontrada + parâmetros
```

Ordem de busca:
1. Rotas explícitas (registradas com `$app->get()`, etc)
2. ConventionRouteResolver (fallback automático)

[Veja docs/ROUTING.md para mais](ROUTING.md)

### 6. ControllerResolver::resolve()

Resolve o que executar (class@method ou closure):

```php
// Você registrou:
$app->get('/users/{id}', 'UserController@show');

// O resolver executa:
(new UserController())->show($request);
```

### 7. Execução da Ação

Seu controller/closure é executado e retorna uma `Response`:

```php
public function show(Request $request): Response {
    $id = $request->param('id');
    
    $user = $this->users->findById($id);
    if (!$user) {
        throw new HttpException('User not found', 404);
    }
    
    return Response::json($user->toArray());
}
```

### 8. Middleware AFTER

Executa **depois** que a Response foi criada:

```php
class LogResponseMiddleware implements AfterResponseAttribute {
    public function after(Response $response): Response {
        echo "Status: " . $response->statusCode();
        return $response;
    }
}
```

Pode:
- ✅ Adicionar headers
- ✅ Modificar status code
- ✅ Logging da resposta
- ❌ Não pode mudar o body (a resposta já foi construída)

### 9. ResponseEmitter::emit()

Envia a resposta para o cliente:

```php
$emitter = new ResponseEmitter();
$emitter->emit($response);

// Internamente:
// header('Content-Type: application/json');
// echo json_encode($data);
```

## Request ID Automático

Toda requisição recebe um ID único para rastreabilidade:

```php
// Cliente pode enviar um ID específico
curl -H "X-Request-Id: abc123" http://localhost/users

// Ou o framework gera um UUID
$id = $request->requestId();

// ID é automaticamente incluído em respostas de erro
Response::json(['error' => '...', 'request_id' => $id], 500);
```

## Tratamento de Erros

Exceções `HttpException` são capturadas automaticamente:

```php
// Seu código
throw new HttpException('Invalid user', 400);

// O framework converte em:
Response::json(
    ['error' => 'Invalid user', 'request_id' => '...'],
    400
);
```

Outras exceções (não HTTP) resultam em erro 500.

## Exemplo Prático

```php
<?php

use Elavora\Api\Application;
use Elavora\Api\Http\Request;
use Elavora\Api\Http\Response;
use Elavora\Api\Exceptions\HttpException;

$app = Application::create();

// Middleware BEFORE
$app->middleware(new class {
    public function before(Request $request): ?Response {
        if ($request->header('Authorization') === null) {
            throw new HttpException('Missing auth header', 401);
        }
        return null;
    }
});

// Rota
$app->get('/hello/{name}', function (Request $request): Response {
    return Response::json([
        'message' => 'Hello ' . $request->param('name'),
        'request_id' => $request->requestId(),
    ]);
});

// Middleware AFTER
$app->afterResponse(new class {
    public function after(Response $response): Response {
        return $response->header('X-Powered-By', 'Elavora API');
    }
});

// Executa
$request = Request::fromGlobals();
$response = $app->handle($request);
$app->emit($response);
```

## Quando Interceptar em Cada Etapa

| Necessidade | Etapa | Como |
|---|---|---|
| Autenticação | Middleware BEFORE | Verificar token |
| Rate limiting | Middleware BEFORE | Contar requisições |
| Logging de entrada | Middleware BEFORE | `error_log()` |
| Modificar response | Middleware AFTER | Adicionar headers |
| Cache de resposta | Router ou Attribute | `@Cache` ou custom |
| Database transactions | Attribute | `@Transaction` |

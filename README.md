# Elavora API Framework

Framework HTTP minimalista, modulado e desacoplado para construção de APIs em PHP. Fornece os componentes essenciais (aplicação, roteamento, middleware, request/response) mantendo tudo simples e extensível.

## Instalação Rápida

```bash
composer require elavora/api-framework
```

## Seu Primeiro Endpoint

```php
<?php

use Elavora\Api\Application;
use Elavora\Api\Http\Response;

$app = Application::create();
$app->get('/health', fn () => Response::json(['status' => 'ok']));
$app->emit($app->handle(Elavora\Api\Http\Request::fromGlobals()));
```

## O que Contém?

### Core HTTP
- **Application** - Inicializa e coordena toda a aplicação
- **HttpKernel** - Processa o ciclo de vida HTTP (middleware → routing → controller → response)
- **Request/Response** - Abstrações imutáveis para HTTP
- **ResponseEmitter** - Envia respostas ao cliente

### Roteamento
- **Router** - Define e busca rotas com convenção ou explicitamente
- **Route** - Representa uma rota individual
- **ControllerResolver** - Resolve qual controller/closure executar
- **ConventionRouteResolver** - Fallback automático para controllers por convenção

### HTTP & Exceções
- **HttpMethod** - Enum com os métodos HTTP
- **HttpStatusCode** - Enum com os status codes HTTP
- **HttpException** - Para erros HTTP com status code

### Extensibilidade
- **Container** - Dependency Injection minimalista
- **Contracts** - Interfaces para extensões (CacheStore, LogWriter, DatabaseConnection, etc.)
- **Attributes** - Metaprogramação com anotações PHP 8.0+
- **Middleware** - Antes e depois da resposta

## Arquitetura

O framework segue um ciclo HTTP bem definido. [Veja detalhes em docs/HTTP_LIFECYCLE.md](docs/HTTP_LIFECYCLE.md)

```
Request
  ↓
Middleware (antes)
  ↓
Router (busca rota)
  ↓
ControllerResolver (executa controller/closure)
  ↓
Middleware (depois)
  ↓
Response (enviada ao cliente)
```

## Usando Roteamento

### Rotas Explícitas

```php
$app->get('/users/{id}', function (Request $request) {
    $id = $request->param('id');
    return Response::json(['user_id' => $id]);
});

$app->post('/users', [UserController::class, 'create']);
$app->delete('/users/{id}', 'UserController@delete');
```

Métodos disponíveis: `get`, `post`, `put`, `patch`, `delete`, `options`, `head`.

### Convenção (Fallback Automático)

Se nenhuma rota explícita é encontrada, o framework tenta usar convenção:

```
GET /users/list
→ App\Http\Controller\UsersController->list()

GET /products
→ App\Http\Controller\ProductsController->index()
```

[Veja mais em docs/ROUTING.md](docs/ROUTING.md)

## Request & Response

### Request

```php
$method = $request->method();        // GET, POST, etc
$path = $request->path();            // /users/123
$id = $request->param('id');         // Parâmetro de rota
$name = $request->query('name');     // Query string
$data = $request->json();            // Parse JSON body
$header = $request->header('Auth');  // Header específico
$requestId = $request->requestId();  // X-Request-Id
```

### Response

```php
// JSON
Response::json(['key' => 'value'], 200);

// HTML
Response::html('<h1>Hello</h1>', 200);

// Status code específico
Response::status(201);

// Com headers customizados
Response::json(['id' => 1])
    ->header('X-Custom', 'value');
```

[Mais detalhes em docs/REQUEST_RESPONSE.md](docs/REQUEST_RESPONSE.md)

## Dependency Injection

O Container resolve e injeta dependências automaticamente:

```php
class UserService {
    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }
}

$app->singleton(UserService::class);

// Container injeta DatabaseConnection automaticamente
$userService = $app->get(UserService::class);
```

[Veja docs/CONTAINER.md](docs/CONTAINER.md)

## Attributes (Metaprogramação)

Use anotações para metaprogramação:

```php
use Elavora\Api\Attributes\Method;
use Elavora\Api\Attributes\Cache;

#[Method('POST')]
#[Cache(3600)]
public function handle(Request $request): Response {
    // ...
}
```

Attributes suportados: `Method`, `Cache`, `Transaction`, `RequiredFields`, `OptionalParams`, `Response`, e mais. [Veja em docs/ATTRIBUTES.md](docs/ATTRIBUTES.md)

## Middleware

Middleware executa antes e depois do controller:

```php
$app->middleware(AuthMiddleware::class);
$app->afterResponse(LogMiddleware::class);
```

[Mais em docs/MIDDLEWARE.md](docs/MIDDLEWARE.md)

## Extensões (Pacotes Opcionais)

O framework é minimalista. Componentes adicionais como Cache, Queue, Database, Storage estão em pacotes separados:

- `elavora/api-cache-apcu` - Cache local
- `elavora/api-cache-redis` - Cache Redis
- `elavora/api-database-mysql` - MySQL
- `elavora/api-database-postgresql` - PostgreSQL
- `elavora/api-storage-s3` - S3
- `elavora/api-datatypes` - DataTypes tipados

## Observabilidade

O framework gera um `X-Request-Id` automaticamente para rastreabilidade:

```php
$requestId = $request->requestId(); // UUID ou header recebido
```

Respostas JSON de erro incluem o `request_id` para debug.

## Quando Usar Elavora API

✅ APIs REST modernistas  
✅ Microsserviços  
✅ Projetos pequenos a médios  
✅ Quando você quer controle e simplicidade  

❌ Se precisa de ORM full-stack (use sintaxe nativa + extensão database)  
❌ Se precisa de admin panel automático  

## Documentação Completa

- [HTTP Lifecycle](docs/HTTP_LIFECYCLE.md) - Como requisições são processadas
- [Routing](docs/ROUTING.md) - Rotas explícitas e por convenção
- [Request & Response](docs/REQUEST_RESPONSE.md) - APIs HTTP
- [Container](docs/CONTAINER.md) - Dependency Injection
- [Attributes](docs/ATTRIBUTES.md) - Metaprogramação
- [Middleware](docs/MIDDLEWARE.md) - Processamento antes/depois

## Testes

```bash
composer test
```

## Licença

[MIT](LICENSE)

## Suporte

Levante uma issue ou abra um PR em [GitHub](https://github.com/Elavora/api-framework).

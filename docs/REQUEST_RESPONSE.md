# Request & Response

Request e Response são abstrações imutáveis do HTTP. Elas encapsulam dados e comportamento.

## Request

### Criar Request

```php
use Elavora\Api\Http\Request;

// Do ciclo HTTP (superglobais)
$request = Request::fromGlobals();

// Manual (testes ou casos especiais)
$request = new Request(
    method: 'GET',
    path: '/users/123',
    query: ['filter' => 'active'],
    body: '{"name":"John"}',
    headers: ['Content-Type' => 'application/json'],
);
```

### Métodos Principais

#### Informações Básicas

```php
// HTTP method
$request->method();        // "GET", "POST", etc
$request->isGet();         // true se GET
$request->isPost();        // true se POST
$request->isPut();         // true se PUT
// isDelete(), isPatch(), isOptions(), isHead() também existem

// URL
$request->path();          // "/users/123"
$request->fullPath();      // "/users/123?sort=name"
$request->scheme();        // "https"
$request->host();          // "example.com"
$request->port();          // 443
```

#### Parâmetros de Rota

```php
// /users/{id}/posts/{postId}

$request->param('id');     // "123"
$request->param('postId'); // "456"

// Todos os parâmetros
$params = $request->params(); // ['id' => '123', 'postId' => '456']
```

#### Query String (GET)

```php
// GET /users?page=2&limit=10

$request->query('page');    // "2"
$request->query('limit');   // "10"
$request->query('missing'); // null

// Com padrão
$request->query('page', 1);     // "2"
$request->query('missing', 10); // 10

// Todos os parâmetros
$queries = $request->queries(); // ['page' => '2', 'limit' => '10']
```

#### Body (POST, PUT, PATCH)

```php
// JSON
$request->json();           // Array parsed do JSON
$request->json('name');     // Valor específico

// Form data
$request->form();           // Array de POST data
$request->form('email');    // Campo específico

// Raw
$request->body();           // String bruto do body
```

#### Headers

```php
$request->header('Authorization');           // "Bearer token..."
$request->header('Content-Type');            // "application/json"
$request->header('Missing', 'default');      // "default"

// Todos os headers
$headers = $request->headers(); // ['authorization' => 'Bearer...', ...]

// User-Agent, Accept, etc
$request->userAgent();
$request->accept();
```

#### Request ID

```php
// Único para cada requisição (UUID)
$request->requestId();
// Ou obtém do header X-Request-Id se enviado pelo cliente
```

#### IP & Host

```php
$request->ip();             // IP do cliente
$request->isSecure();       // true se HTTPS
$request->isAjax();         // true se X-Requested-With = XMLHttpRequest
```

## Response

### Criar Response

```php
use Elavora\Api\Http\Response;

// JSON
Response::json(['id' => 1, 'name' => 'John']);
Response::json(['error' => 'Not found'], 404);

// HTML
Response::html('<h1>Hello</h1>');
Response::html('<h1>Error</h1>', 500);

// Apenas status
Response::status(204); // No Content

// String (text/plain)
Response::text('Hello World');

// Customizado
new Response(
    statusCode: 201,
    body: json_encode(['id' => 123]),
    headers: ['Content-Type' => 'application/json'],
);
```

### Métodos Principais

#### Status

```php
$response = Response::json([]);

$response->statusCode();    // 200
$response->isSuccess();     // true (2xx)
$response->isError();       // false
$response->isClientError(); // false (4xx)
$response->isServerError(); // false (5xx)
```

#### Headers

```php
$response = Response::json(['id' => 1]);

// Adicionar header
$response = $response->header('X-Custom', 'value');
$response = $response->header('Cache-Control', 'max-age=3600');

// Múltiplos
$response = $response
    ->header('X-Custom', 'value')
    ->header('X-Another', 'header');

// Obter header
$response->header('X-Custom'); // "value"

// Todos os headers
$headers = $response->headers();
```

#### Body

```php
// Obter body
$body = $response->body();

// Se JSON
$data = json_decode($response->body(), associative: true);

// Tipo de conteúdo
$response->contentType();  // "application/json"
```

## Exemplos Práticos

### Validação Simples

```php
$app->post('/users', function (Request $request): Response {
    $data = $request->json();
    
    if (empty($data['email'])) {
        throw new HttpException('Email is required', 400);
    }
    
    if (empty($data['name'])) {
        throw new HttpException('Name is required', 400);
    }
    
    // Validação OK, criar user
    $user = $this->users->create($data);
    
    return Response::json($user->toArray(), 201)
        ->header('Location', '/users/' . $user->id);
});
```

### Resposta com Headers Customizados

```php
$app->get('/api/users', function (): Response {
    $users = $this->users->all();
    
    return Response::json($users)
        ->header('X-Total-Count', count($users))
        ->header('Cache-Control', 'max-age=300')
        ->header('ETag', hash('sha256', json_encode($users)));
});
```

### Tratamento de Query String

```php
$app->get('/search', function (Request $request): Response {
    $query = $request->query('q');
    $limit = $request->query('limit', 10);
    $offset = $request->query('offset', 0);
    
    if (strlen($query) < 3) {
        throw new HttpException('Query too short', 400);
    }
    
    $results = $this->search($query, $limit, $offset);
    
    return Response::json([
        'query' => $query,
        'results' => $results,
        'total' => count($results),
    ]);
});
```

### Redirecionar

```php
$app->post('/users', function (Request $request): Response {
    $user = $this->users->create($request->json());
    
    return Response::status(301)
        ->header('Location', '/users/' . $user->id);
});
```

### Condicional por Accept Header

```php
$app->get('/data', function (Request $request): Response {
    $data = ['key' => 'value'];
    
    if (strpos($request->header('Accept', ''), 'application/xml') !== false) {
        return Response::xml($this->toXml($data));
    }
    
    return Response::json($data);
});
```

## Imutabilidade

Request e Response são imutáveis:

```php
$response = Response::json(['id' => 1]);
$response->header('X-Custom', 'value');

// Não mudou!
echo $response->header('X-Custom'); // null

// Correto:
$response = $response->header('X-Custom', 'value');
echo $response->header('X-Custom'); // "value"
```

Isso garante que você não modifique a resposta por acidente em middleware ou outro lugar.

## Padrões Comuns

### HATEOAS (Links)

```php
return Response::json([
    'id' => 1,
    'name' => 'John',
    'links' => [
        'self' => '/users/1',
        'update' => '/users/1',
        'delete' => '/users/1',
    ],
]);
```

### Envelope de Resposta

```php
return Response::json([
    'success' => true,
    'data' => $user,
    'timestamp' => time(),
    'request_id' => $request->requestId(),
]);
```

### Erro Estruturado

```php
throw new HttpException(
    message: 'Invalid input',
    statusCode: 422,
);

// Resposta automática:
// {
//     "error": "Invalid input",
//     "request_id": "abc-123-def"
// }
```

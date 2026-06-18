# Attributes (Metaprogramação)

Attributes permitem adicionar metadados a classes, métodos e propriedades. O framework usa reflection para ler esses atributos e aplicar comportamentos automáticos.

## O que São Attributes?

Anotações PHP 8+ que você coloca acima de classes/métodos:

```php
#[Cache(3600)]
#[RequiredFields(['email', 'name'])]
public function create(Request $request): Response {
    // Framework lê os atributos e aplica lógica automática
}
```

## Attributes Suportados

### @Method

Força um método HTTP específico:

```php
#[Method('POST')]
public function handle(Request $request): Response {
    // Só aceita POST, não GET/PUT/etc
}
```

### @Cache

Cache automático da resposta:

```php
#[Cache(3600)] // 1 hora
public function listUsers(): Response {
    // Resposta é cacheada por 1 hora
    return Response::json($this->users->all());
}
```

Com key customizado:

```php
#[Cache(3600, 'users:list:{userId}')]
public function userPosts(Request $request): Response {
    $userId = $request->param('userId');
    // Key vira: users:list:123
}
```

### @Transaction

Envolve em transação de banco de dados:

```php
#[Transaction]
public function create(Request $request): Response {
    $user = $this->users->create($request->json());
    // Tudo é feito em transação
    return Response::json($user, 201);
}
```

### @RequiredFields

Valida campos obrigatórios no JSON:

```php
#[RequiredFields(['email', 'name'])]
public function create(Request $request): Response {
    $data = $request->json();
    // Framework lança erro 422 se 'email' ou 'name' estiverem faltando
}
```

### @OptionalFields

Especifica campos opcionais (permite null):

```php
#[RequiredFields(['email', 'name'])]
#[OptionalFields(['phone', 'company'])]
public function create(Request $request): Response {
    // email, name: obrigatórios
    // phone, company: opcionais
}
```

### @RequiredParams

Valida query string:

```php
#[RequiredParams(['page', 'limit'])]
public function search(Request $request): Response {
    $page = $request->query('page');
    // Framework lança erro 400 se page ou limit estiverem faltando
}
```

### @OptionalParams

Define query params opcionais:

```php
#[RequiredParams(['search'])]
#[OptionalParams(['limit', 'offset'])]
public function search(Request $request): Response {
    // search: obrigatório
    // limit, offset: opcionais
}
```

### @Details

Adiciona informações descritivas:

```php
#[Details(
    description: 'Create a new user',
    example: ['email' => 'john@example.com', 'name' => 'John Doe']
)]
public function create(Request $request): Response {
    // Útil para documentação automática
}
```

### @Response

Define respostas possíveis:

```php
#[Response(statusCode: 201, description: 'User created')]
#[Response(statusCode: 400, description: 'Invalid data')]
public function create(Request $request): Response {
    // Para documentação
}
```

## Criando Seus Próprios Attributes

### Attribute de Logging

```php
<?php

use Elavora\Api\Attributes\HttpAttribute;

#[Attribute]
class LogExecution implements HttpAttribute {
    public function __construct(
        private string $level = 'info'
    ) {}
    
    public function handle(\Elavora\Api\Http\Request $request): void {
        error_log("[$this->level] {$request->method()} {$request->path()}");
    }
}

// Use
#[LogExecution('debug')]
public function show(Request $request): Response {
    // Execução é logada automaticamente
}
```

### Attribute de Autenticação

```php
#[Attribute]
class Authenticated implements BeforeRequestAttribute {
    public function before(Request $request): ?\Elavora\Api\Http\Response {
        $token = $request->header('Authorization');
        
        if (!$token) {
            throw new \Elavora\Api\Exceptions\HttpException('Unauthorized', 401);
        }
        
        if (!$this->validateToken($token)) {
            throw new \Elavora\Api\Exceptions\HttpException('Invalid token', 401);
        }
        
        return null; // Continua para o controller
    }
    
    private function validateToken(string $token): bool {
        // Sua lógica
    }
}

// Use
#[Authenticated]
public function deleteUser(Request $request): Response {
    // Só executa se autenticado
}
```

### Attribute de Validação Custom

```php
#[Attribute]
class ValidateEmail implements BeforeRequestAttribute {
    public function before(Request $request): ?\Elavora\Api\Http\Response {
        $data = $request->json();
        
        if (!filter_var($data['email'] ?? null, FILTER_VALIDATE_EMAIL)) {
            throw new \Elavora\Api\Exceptions\HttpException('Invalid email', 422);
        }
        
        return null;
    }
}

#[ValidateEmail]
public function create(Request $request): Response {
    // Email já validado
}
```

## Exemplos Práticos

### Endpoint Completo

```php
use Elavora\Api\Attributes\Method;
use Elavora\Api\Attributes\Cache;
use Elavora\Api\Attributes\RequiredFields;
use Elavora\Api\Attributes\Transaction;

class UserController {
    #[Method('GET')]
    #[Cache(3600)]
    public function index(): Response {
        // GET, cacheado por 1 hora
        return Response::json($this->users->all());
    }
    
    #[Method('POST')]
    #[RequiredFields(['email', 'name'])]
    #[Transaction]
    public function store(Request $request): Response {
        // POST, valida campos, em transação
        $user = $this->users->create($request->json());
        return Response::json($user, 201);
    }
    
    #[Method('GET')]
    #[Cache(300, 'user:{id}')]
    public function show(Request $request): Response {
        // GET, cacheado por 5 minutos
        return Response::json($this->users->find($request->param('id')));
    }
}
```

### Com Autenticação

```php
#[Authenticated]  // Custom attribute criado acima
#[RequiredFields(['title', 'content'])]
#[Transaction]
public function createPost(Request $request): Response {
    $user = $this->auth->user();
    $data = $request->json();
    
    $post = $this->posts->create([
        'user_id' => $user->id,
        'title' => $data['title'],
        'content' => $data['content'],
    ]);
    
    return Response::json($post, 201);
}
```

## Processamento Automático

O framework processa attributes em três pontos:

1. **BeforeRequestAttribute** - Antes da ação
   - Autenticação, validação, etc
   - Pode interromper com resposta antecipada

2. **HttpAttribute** - Durante a execução
   - Logging, timing, etc
   - Não pode interromper

3. **AfterResponseAttribute** - Depois da ação
   - Adicionar headers, caching, etc
   - Pode modificar resposta

## Boas Práticas

1. **Mantenha atributos simples**: Uma responsabilidade cada
2. **Reutilize**: Crie atributos genéricos que você usa múltiplas vezes
3. **Documente**: Deixe claro o que cada atributo faz
4. **Teste**: Teste atributos separadamente

```php
// ✅ Bom
#[RequiredFields(['email'])]
#[ValidateEmail]
#[Authenticated]
public function update(Request $request): Response { ... }

// ❌ Ruim - muito específico
#[DoManyThingsAtOnce]
public function update(Request $request): Response { ... }
```

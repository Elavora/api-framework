# Container & Dependency Injection

O Container é um Service Locator simples que resolve dependências automaticamente usando reflection. Ele remove a necessidade de instanciar manualmente classes e suas dependências.

## Conceitos Básicos

### Sem Container (Manual)

```php
class UserService {
    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }
}

class UserController {
    public function __construct(UserService $service) {
        $this->service = $service;
    }
}

// Você precisa instanciar tudo manualmente
$db = new DatabaseConnection();
$service = new UserService($db);
$controller = new UserController($service);
```

### Com Container (Automático)

```php
// Registre uma vez
$container = new Container();
$container->singleton(DatabaseConnection::class);
$container->singleton(UserService::class);
$container->singleton(UserController::class);

// Use em qualquer lugar
$controller = $container->get(UserController::class);
// DatabaseConnection e UserService são injetadas automaticamente
```

## Registrando Serviços

### Singleton (Instância Única)

```php
$app->singleton(DatabaseConnection::class);

// Toda requisição usa a mesma instância
$db1 = $app->get(DatabaseConnection::class);
$db2 = $app->get(DatabaseConnection::class);
$db1 === $db2; // true
```

### Transient (Nova Instância)

```php
$app->transient(UserService::class);

// Cada chamada cria uma nova instância
$service1 = $app->get(UserService::class);
$service2 = $app->get(UserService::class);
$service1 === $service2; // false
```

### Com Factory

```php
$app->singleton(DatabaseConnection::class, function (Container $c) {
    $config = $c->get(Config::class);
    return new DatabaseConnection($config->get('database'));
});

// Ou mais simples
$app->singleton(DatabaseConnection::class, 
    fn ($c) => new DatabaseConnection($c->get(Config::class)->get('database'))
);
```

### Com Tipo de Retorno

```php
interface CacheStore {
    public function get(string $key);
}

class RedisCache implements CacheStore {
    // ...
}

$app->singleton(CacheStore::class, RedisCache::class);

// Quando você pede CacheStore, recebe RedisCache
$cache = $app->get(CacheStore::class); // Instância de RedisCache
```

## Usando o Container

### Em Controllers

O Container injeta automaticamente no construtor:

```php
class UserController {
    public function __construct(UserService $service) {
        $this->service = $service;
    }
    
    public function show(Request $request): Response {
        $user = $this->service->findById($request->param('id'));
        return Response::json($user);
    }
}

// Você registra
$app->singleton(UserController::class);

// O Container injeta UserService automaticamente
```

### Injeção Manual

```php
$app->singleton(DatabaseConnection::class);

// Você pode obter manualmente
$db = $app->get(DatabaseConnection::class);
$db->query('SELECT * FROM users');
```

### Em Closures

```php
$app->post('/users', function (Request $req, UserService $service): Response {
    // Container injeta automaticamente
    $user = $service->create($req->json());
    return Response::json($user, 201);
});
```

## Resolução Automática

O Container usa reflection para descobrir dependências:

```php
class UserService {
    public function __construct(
        DatabaseConnection $db,
        Logger $logger,
        CacheStore $cache
    ) {
        // Tudo injetado automaticamente
    }
}

// Basta registrar UserService
$app->singleton(UserService::class);

// DatabaseConnection, Logger e CacheStore
// são resolvidas automaticamente se existirem
```

## Exemplos Práticos

### Setup Completo

```php
<?php

use Elavora\Api\Application;
use Elavora\Api\Http\Response;

$app = Application::create();

// Registre seus serviços
$app->singleton(DatabaseConnection::class, function ($c) {
    return new DatabaseConnection('mysql://localhost/db');
});

$app->singleton(UserRepository::class);
$app->singleton(UserService::class);
$app->singleton(UserController::class);

// Middleware que usa container
$app->middleware(new class {
    public function before(\Elavora\Api\Http\Request $req, Logger $logger) {
        $logger->info("Request: {$req->method()} {$req->path()}");
        return null;
    }
});

// Rota que injeta serviço
$app->get('/users/{id}', function (\Elavora\Api\Http\Request $req, UserService $service) {
    $user = $service->findById($req->param('id'));
    return Response::json($user);
});

$request = \Elavora\Api\Http\Request::fromGlobals();
$response = $app->handle($request);
$app->emit($response);
```

### Com Interfaces

```php
interface UserRepository {
    public function findById(int $id);
    public function save(User $user);
}

class MySQLUserRepository implements UserRepository {
    public function __construct(DatabaseConnection $db) {
        $this->db = $db;
    }
    
    public function findById(int $id) {
        return $this->db->query("SELECT * FROM users WHERE id = ?", [$id]);
    }
}

// Registre a interface
$app->singleton(UserRepository::class, MySQLUserRepository::class);

// Use em qualquer lugar
class UserService {
    public function __construct(UserRepository $repo) {
        $this->repo = $repo; // Recebe MySQLUserRepository
    }
}
```

### Configuração Condicional

```php
$env = $_ENV['APP_ENV'] ?? 'development';

if ($env === 'production') {
    $app->singleton(CacheStore::class, RedisCache::class);
} else {
    $app->singleton(CacheStore::class, ArrayCache::class);
}

// Qualquer lugar que pedir CacheStore, recebe a implementação correta
```

## Boas Práticas

### 1. Registre no Bootstrap

```php
// bootstrap.php
$app = Application::create();

// Todos os singletons aqui
$app->singleton(DatabaseConnection::class);
$app->singleton(UserRepository::class);
$app->singleton(UserService::class);

return $app;
```

### 2. Use Interfaces

```php
// Não registre a implementação
// $app->singleton(MySQLUserRepository::class);

// Registre a interface
$app->singleton(UserRepository::class, MySQLUserRepository::class);

// Assim você pode trocar a implementação depois sem mudar código
```

### 3. Lazy Loading com Factory

```php
// Não crie tudo na inicialização
$app->singleton(DatabaseConnection::class, function ($c) {
    // Criado apenas quando solicitado
    return new DatabaseConnection(/* ... */);
});
```

### 4. Evite Circular Dependencies

```php
// ❌ Ruim: A depende de B que depende de A
class ServiceA {
    public function __construct(ServiceB $b) {}
}

class ServiceB {
    public function __construct(ServiceA $a) {}
}

// ✅ Bom: Use injeção de dependência
class ServiceA {
    public function __construct(ServiceB $b) {}
}

class ServiceB {
    private ServiceA $a;
    
    public function setA(ServiceA $a) {
        $this->a = $a;
    }
}
```

### 5. Teste com Mocks

```php
// Em testes
$container = new Container();

// Injete mock ao invés da implementação real
$mockDb = $this->createMock(DatabaseConnection::class);
$container->singleton(DatabaseConnection::class, $mockDb);

$service = $container->get(UserService::class);
// UserService recebe o mock
```

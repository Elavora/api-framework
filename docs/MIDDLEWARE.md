# Middleware

Middleware são componentes que executam **antes** ou **depois** da lógica do seu controller. Eles são úteis para autenticação, logging, compressão, cors, etc.

## Conceito

Middleware funciona como um "filtro" em torno da requisição:

```
Request
  ↓
[Middleware 1 - ANTES]
  ↓
[Middleware 2 - ANTES]
  ↓
Controller
  ↓
[Middleware 2 - DEPOIS]
  ↓
[Middleware 1 - DEPOIS]
  ↓
Response
```

## Dois Tipos de Middleware

### Middleware BEFORE (BeforeRequestAttribute)

Executa **antes** do controller. Pode:
- ✅ Validar a requisição
- ✅ Autenticar usuário
- ✅ Modificar request
- ✅ Retornar response antecipada (bloqueia controller)

```php
class AuthMiddleware implements BeforeRequestAttribute {
    public function before(Request $request): ?Response {
        $token = $request->header('Authorization');
        
        if (!$token) {
            // Retorna response, controller não executa
            return Response::json(['error' => 'Unauthorized'], 401);
        }
        
        if (!$this->validateToken($token)) {
            return Response::json(['error' => 'Invalid token'], 401);
        }
        
        // null significa: continua para o controller
        return null;
    }
}
```

### Middleware AFTER (AfterResponseAttribute)

Executa **depois** do controller. Pode:
- ✅ Adicionar headers
- ✅ Modificar status code
- ✅ Logging
- ❌ Modificar body (resposta já foi construída)

```php
class LogResponseMiddleware implements AfterResponseAttribute {
    public function after(Response $response): Response {
        error_log("Response: {$response->statusCode()}");
        
        // Adicionar header
        return $response->header('X-Processed-At', date('c'));
    }
}
```

## Registrando Middleware

### Middleware Global

Executa em **todas** as requisições:

```php
$app->middleware(AuthMiddleware::class);
$app->middleware(LogMiddleware::class);
$app->afterResponse(LogResponseMiddleware::class);
```

Ordem importa:

```php
// 1. LogMiddleware
// 2. AuthMiddleware (usa log)
// 3. Controller
// 4. LogResponseMiddleware
$app->middleware(LogMiddleware::class);
$app->middleware(AuthMiddleware::class);
$app->afterResponse(LogResponseMiddleware::class);
```

### Middleware por Rota

Você pode registrar middleware em rotas específicas (se o framework suportar):

```php
// Apenas para POST /admin
$app->post('/admin/users', [...], 
    middleware: [AdminAuthMiddleware::class]
);
```

Mas geralmente é melhor usar atributos no controller.

### Middleware com Atributos

Registre como atributo no controller:

```php
#[Authenticated]
#[AdminOnly]
public function deleteUser(Request $request): Response {
    // Executa middlewares apenas para este método
}
```

## Exemplos Práticos

### Autenticação

```php
class AuthMiddleware implements BeforeRequestAttribute {
    public function __construct(private UserRepository $users) {}
    
    public function before(Request $request): ?Response {
        $token = $request->header('Authorization');
        
        if (!$token) {
            throw new HttpException('Missing auth header', 401);
        }
        
        // Valida token e carrega usuário
        $user = $this->users->findByToken($token);
        
        if (!$user) {
            throw new HttpException('Invalid token', 401);
        }
        
        // Guarda usuário na requisição para usar depois
        $request->setAttribute('user', $user);
        
        return null;
    }
}

// Controller
#[Authenticated]
public function profile(Request $request): Response {
    $user = $request->getAttribute('user');
    return Response::json($user);
}
```

### Rate Limiting

```php
class RateLimitMiddleware implements BeforeRequestAttribute {
    private array $limits = [];
    
    public function before(Request $request): ?Response {
        $ip = $request->ip();
        $key = "$ip:{$request->method()}:{$request->path()}";
        
        $this->limits[$key] = ($this->limits[$key] ?? 0) + 1;
        
        if ($this->limits[$key] > 60) { // 60 requests por minuto
            throw new HttpException('Too many requests', 429);
        }
        
        return null;
    }
}
```

### CORS

```php
class CorsMiddleware implements BeforeRequestAttribute, AfterResponseAttribute {
    private array $allowedOrigins = ['https://example.com', 'http://localhost:3000'];
    
    public function before(Request $request): ?Response {
        if ($request->method() === 'OPTIONS') {
            // Preflight
            return Response::status(204)
                ->header('Access-Control-Allow-Methods', 'GET,POST,PUT,DELETE')
                ->header('Access-Control-Allow-Headers', 'Content-Type');
        }
        
        return null;
    }
    
    public function after(Response $response): Response {
        $origin = request()->header('Origin');
        
        if (in_array($origin, $this->allowedOrigins)) {
            return $response->header('Access-Control-Allow-Origin', $origin);
        }
        
        return $response;
    }
}
```

### Logging

```php
class LogMiddleware implements BeforeRequestAttribute, AfterResponseAttribute {
    public function before(Request $request): ?Response {
        $this->startTime = microtime(true);
        error_log("[START] {$request->method()} {$request->path()}");
        return null;
    }
    
    public function after(Response $response): Response {
        $duration = (microtime(true) - $this->startTime) * 1000;
        error_log("[END] Status: {$response->statusCode()} Duration: {$duration}ms");
        return $response;
    }
}
```

### Compressão

```php
class CompressionMiddleware implements AfterResponseAttribute {
    public function after(Response $response): Response {
        // Adiciona gzip se suportado
        if (strpos($request->header('Accept-Encoding', ''), 'gzip') !== false) {
            return $response->header('Content-Encoding', 'gzip');
        }
        
        return $response;
    }
}
```

## Padrões Comuns

### Middleware em Cadeia

```php
// Registre vários middlewares
$app->middleware(LogMiddleware::class);
$app->middleware(AuthMiddleware::class);
$app->middleware(ValidationMiddleware::class);
$app->middleware(RateLimitMiddleware::class);

// Executam nessa ordem
```

### Middleware Condicional

```php
class AdminOnlyMiddleware implements BeforeRequestAttribute {
    public function before(Request $request): ?Response {
        $user = $request->getAttribute('user');
        
        if (!$user || !$user->isAdmin()) {
            throw new HttpException('Forbidden', 403);
        }
        
        return null;
    }
}

// Use em controllers admin
#[Authenticated]
#[AdminOnly]
public function deleteUser(Request $request): Response {
    // Só executa se admin
}
```

### Middleware com Dependências

```php
class AuthMiddleware implements BeforeRequestAttribute {
    // Container injeta automaticamente
    public function __construct(
        private UserRepository $users,
        private TokenValidator $validator
    ) {}
    
    public function before(Request $request): ?Response {
        // Usa $this->users e $this->validator
    }
}
```

## Boas Práticas

1. **Uma responsabilidade por middleware**: Auth, Logging, CORS separados
2. **Ordene corretamente**: Logging antes, depois tudo que depende dele
3. **Seja específico**: Use atributos para middleware que não é global
4. **Teste separadamente**: Middleware são easy to test

```php
// ✅ Bom
$app->middleware(LogMiddleware::class);
$app->middleware(AuthMiddleware::class);
$app->middleware(RateLimitMiddleware::class);

// ❌ Ruim
$app->middleware(DoEverythingMiddleware::class);
```

5. **Use atributos para exceções**: Não deixe lógica complexa em middleware global

```php
// ✅ Bom
#[Authenticated]
#[AdminOnly]
#[RateLimit(requests: 10, window: 60)]
public function criticalAction(): Response { ... }

// ❌ Ruim
// Middleware global checando manualmente cada rota
```

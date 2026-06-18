# Elavora API Framework

Pacote HTTP desacoplado do Elavora API. Ele fornece aplicação, roteamento, middleware,
request/response e contratos para extensões opcionais.

```bash
composer require elavora/api-framework
```

```php
<?php

use Elavora\Api\Application;
use Elavora\Api\Http\Request;
use Elavora\Api\Http\Response;

$app = Application::create();
$app->get('/health', fn (): Response => Response::json(['status' => 'healthy']));
$app->emit($app->handle(Request::fromGlobals()));
```

O pacote não inclui Redis, banco de dados, fila, storage ou controllers de uma
aplicação real. Esses recursos devem ser adicionados por extensões.

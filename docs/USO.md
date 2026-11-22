# Guia de uso

Nucleo HTTP modular do framework Elavora

## Instalacao

```bash
composer require elavora/api-framework
```

## Quando usar

- Criar APIs HTTP pequenas e modulares.
- Registrar rotas, middlewares e extensoes.
- Usar contratos compartilhados por pacotes opcionais.

## Exemplo rapido

```php
<?php

declare(strict_types=1);

use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Http\Request;
use Elavora\Api\Framework\Http\Response;

require __DIR__ . '/vendor/autoload.php';

$app = Application::create();

$app->get('/health', static fn (): Response => Response::json([
    'status' => 'ok',
]));

$response = $app->handle(Request::fromGlobals());
$app->emit($response);
```

## Principais pontos de entrada

- `Elavora\Api\Framework\Application`
- `Elavora\Api\Framework\Container`
- `Elavora\Api\Framework\Contracts\Storage`
- `Elavora\Api\Framework\Contracts\InspectsObjects`
- `Elavora\Api\Framework\Attributes\Cache`
- `Elavora\Api\Framework\Attributes\Details`
- `Elavora\Api\Framework\Attributes\Method`
- `Elavora\Api\Framework\Http\Request`
- `Elavora\Api\Framework\Http\Response`

## Cache de respostas (`#[Cache]`)

`#[Cache(seconds, varyByHeaders, extra)]` guarda a resposta de uma action num `CacheStore` registrado no container.
Sem `CacheStore` no container, a action lança `RuntimeException`.

### Chave

A chave é um hash de: método, path, query string, corpo da request, os headers listados em `varyByHeaders` e o
`extra`. Requests com chaves diferentes têm entradas diferentes.

### Regras

1. **Falha (miss).** Não há entrada para a chave: a action roda. Se o status da resposta for `2xx` (de `200` a
   `299`), o attribute grava a resposta no `CacheStore`, **uma única vez**, com `set($chave, $resposta, $seconds)`.
   Resposta com outro status (`3xx`, `4xx`, `5xx`) não é gravada.
2. **Acerto (hit).** Há uma entrada para a chave: a action **não** roda e a resposta guardada é devolvida. O attribute
   **não grava nada**: nenhum `set`, nenhum `delete`, e o tempo de vida da entrada não muda.
3. **TTL fixo.** O tempo de vida de `#[Cache(N)]` conta a partir da gravação (o miss), não do último acesso. Passados
   `N` segundos da gravação, a entrada expira e a próxima request é um miss, que roda a action e grava de novo,
   começando outro período de `N` segundos. Quantos acertos aconteceram nesse intervalo não importa.
4. **Exceção na action.** Nada é gravado.

Os outros attributes da action (por exemplo um attribute próprio que muda a resposta depois da action) continuam
rodando como antes num hit. A regra 2 vale só para o `Cache`.

### Exemplo

Action com `#[Cache(10)]` e um `CacheStore` que conta as chamadas a `set`. Uma request por segundo, sempre para o
mesmo `GET /task`, a partir do tempo 0:

| Tempo | Resultado | Chamadas a `set` até aqui |
| --- | --- | --- |
| 0 s | miss: a action roda e grava com TTL `10` | 1 |
| 1 s a 9 s | hit: devolve a resposta guardada, a action não roda | 1 |
| 10 s | a entrada expirou: miss, a action roda e grava | 2 |
| 11 s a 19 s | hit | 2 |
| 20 s | expirou: miss | 3 |

Comportamento incorreto que existia antes da correção, como contraexemplo: o hit também chamava `set`, então a
entrada era regravada a cada acerto e o TTL recomeçava. Com tráfego mais frequente que `N` segundos a entrada nunca
expirava, e a action nunca rodava de novo. No exemplo acima haveria um `set` por request (21 em vez de 3, até os 20 s) e o miss
das 10 s e das 20 s nunca aconteceria.

## Storage e InspectsObjects

`Elavora\Api\Framework\Contracts\Storage` define `put`, `get`, `delete` e `temporaryUrl`, sem depender do provider.
Ele **não** diz se um objeto existe nem qual o tamanho dele. Para isso existe o contrato opcional
`Elavora\Api\Framework\Contracts\InspectsObjects`, usado, por exemplo, para conferir um upload direto (o cliente envia
o arquivo para a URL pré-assinada) antes de registrar o arquivo:

```php
interface InspectsObjects
{
    public function exists(string $key): bool;

    public function size(string $key): ?int;
}
```

- `exists($key)`: `true` se o objeto existe, `false` se não existe. Só o "não existe" vira `false`; qualquer outra
  falha do provider (acesso negado, erro de rede etc.) **não** é escondida e sobe como exceção.
- `size($key)`: o tamanho do objeto em bytes, ou `null` se ele não existe. Um objeto vazio tem tamanho `0`, não `null`.
- Uma chave inválida lança `InvalidArgumentException`, como nos métodos do `Storage`.
- Os dois métodos só consultam: não criam, não alteram e não baixam o conteúdo do objeto.
- O contrato é separado do `Storage` para não quebrar quem já o implementa. Uma classe pode implementar os dois
  (`final class MeuStorage implements Storage, InspectsObjects`). Quem consome faz a checagem de tipo:

```php
$storage = $application->container()->get(Storage::class);

if ($storage instanceof InspectsObjects && $storage->size($key) === $declaredSize) {
    // o arquivo chegou e o tamanho confere
}
```

- `elavora/api-storage-local` (`LocalStorage`) e `elavora/api-storage-s3` (`S3Storage`) implementam os dois contratos
  a partir da versão que exige este contrato (framework `^6.4`).

## Dependencias de runtime

- `ext-json` `*`

## Validacao no projeto consumidor

Depois de instalar o pacote, rode os testes da aplicacao consumidora. Para uma verificacao isolada do pacote, use a imagem de desenvolvimento da Elavora (PowerShell, na pasta do pacote):

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace,readonly" ghcr.io/elavora/php-dev:8.4-latest sh -lc 'cp -R /workspace /tmp/package && cd /tmp/package && git config --global --add safe.directory /tmp/package && composer install --no-interaction --no-progress && composer check'
```

Mais detalhes em [Ambiente de desenvolvimento e testes](DESENVOLVIMENTO.md).

## Observacoes

- Mantenha regras de produto fora deste pacote.
- Prefira configurar extensoes no bootstrap da aplicacao.
- Instale apenas os modulos que a aplicacao realmente usa.

---

[README](../README.md) · Próximo: [Segurança do roteamento](ROTEAMENTO.md)

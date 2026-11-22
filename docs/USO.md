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
- `Elavora\Api\Framework\Attributes\Cache`
- `Elavora\Api\Framework\Attributes\Details`
- `Elavora\Api\Framework\Attributes\Method`
- `Elavora\Api\Framework\Http\Request`
- `Elavora\Api\Framework\Http\Response`
- `Elavora\Api\Framework\Contracts\Queue`
- `Elavora\Api\Framework\Contracts\AcknowledgesJobs`
- `Elavora\Api\Framework\Queue\ReservedJob`

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

## Fila com confirmação (`AcknowledgesJobs`)

O contrato `Queue` (`push()` e `pop()`) entrega a mensagem e esquece dela: se o consumidor cair no meio do
processamento, a mensagem se perde. O contrato opcional `Elavora\Api\Framework\Contracts\AcknowledgesJobs` descreve
adaptadores que **reservam** a mensagem, em vez de removê-la, e só a descartam quando o consumidor confirma (`ack`).
Quem não confirma dentro do prazo de visibilidade tem a mensagem devolvida à fila. É uma interface **à parte**:
`Queue` não mudou, e um adaptador pode implementar só `Queue`, ou `Queue` e `AcknowledgesJobs`. O consumidor
descobre o suporte com `$queue instanceof AcknowledgesJobs`.

A garantia é **pelo menos uma vez**: se o consumidor cair depois de processar e antes do `ack`, a mensagem volta e
roda de novo. Os handlers devem ser idempotentes.

```php
namespace Elavora\Api\Framework\Contracts;

interface AcknowledgesJobs
{
    /** @param list<string> $queues Filas em ordem de prioridade (a primeira é a mais prioritária). */
    public function reserve(array $queues): ?ReservedJob;

    public function acknowledge(ReservedJob $job): void;

    public function extend(ReservedJob $job, ?int $seconds = null): void;

    public function recoverExpired(string $queue): int;
}
```

(`ReservedJob` é `Elavora\Api\Framework\Queue\ReservedJob`.)

| Método | Regra |
|---|---|
| `reserve($queues)` | Procura nas filas, na ordem recebida, e reserva a primeira mensagem que encontrar. Só olha a fila seguinte quando a anterior está vazia. Devolve `null` quando todas estão vazias. Antes de reservar, o adaptador devolve à fila as reservas expiradas das filas listadas. A lista vazia lança `InvalidArgumentException` (`A lista de filas nao pode ser vazia.`) |
| `acknowledge($job)` | Confirma o processamento e descarta a reserva. Confirmar uma reserva que já expirou, já foi confirmada ou já foi devolvida à fila não lança erro e não faz nada |
| `extend($job, $seconds)` | Renova o prazo de visibilidade (heartbeat), para jobs longos. `$seconds` é o novo prazo, contado a partir de agora; `null` usa o prazo padrão do adaptador. `$seconds` menor que 1 lança `InvalidArgumentException` (`O prazo de visibilidade deve ser maior que zero.`). Reserva que não existe mais: não faz nada |
| `recoverExpired($queue)` | Devolve à fila as reservas cujo prazo passou sem `ack` e devolve quantas devolveu. As devolvidas voltam para o **início** da fila (são as mais antigas) |

O prazo padrão de visibilidade vem de `QUEUE_VISIBILITY_TIMEOUT` (veja o [Bootstrap](BOOTSTRAP.md)).

### `ReservedJob` (classe final, imutável)

```php
public function __construct(string $queue, array $payload, string $token);
public function queue(): string;     // fila de onde a mensagem saiu
public function payload(): array;    // dados da mensagem, como em Queue::pop()
public function token(): string;     // identificador opaco, só o adaptador interpreta
```

- `queue` vazia lança `InvalidArgumentException` com `A fila da reserva nao pode ser vazia.`
- `token` vazio lança `InvalidArgumentException` com `O token da reserva nao pode ser vazio.`
- O `token` é opaco: o consumidor só o devolve ao adaptador (`acknowledge()`, `extend()`), sem interpretá-lo.

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

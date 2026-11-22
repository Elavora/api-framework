# Bootstrap compartilhado

`Application::create()` continua disponivel e nao carrega extensoes implicitamente.
A API adicional `Elavora\Api\Framework\Bootstrap\ApplicationBootstrap::create()`
centraliza os defaults usados pelo API Skeleton:

```php
use Elavora\Api\Framework\Application;
use Elavora\Api\Framework\Bootstrap\ApplicationBootstrap;
use Elavora\Api\Framework\Http\Response;

$app = ApplicationBootstrap::create(
    basePath: dirname(__DIR__),
    extensions: [], // instancias de Contracts\Extension do projeto
    configure: static function (Application $app): void {
        $app->get('/health', static fn (): Response => Response::json(['status' => 'ok']));
    },
);
```

A ordem e: criar Application com APP_DEBUG, registrar extensoes oficiais,
registrar extensoes do projeto, executar configure. O callback recebe Application
e pode registrar rotas, middlewares e substituir bindings do container.
A lista de extensoes aceita qualquer iterable de Contracts\Extension.

Por padrao, as variaveis sao lidas com getenv(). O argumento `environment`, quando
fornecido, substitui integralmente o ambiente; `[]` permite testes isolados.
Nenhum arquivo .env e carregado pelo framework. `basePath` aponta para a raiz do
projeto consumidor e determina o caminho padrao de storage/logs/app.log.

Os defaults preservam o Skeleton anterior: CACHE_DRIVER seleciona apcu/redis,
DB_DRIVER seleciona mysql/postgresql, LOG_DRIVER seleciona stdout/file/mongodb e ACCESS_LOG liga o [log de acesso](#log-de-acesso).
A fila Redis e registrada quando seu pacote esta instalado, mesmo sem cache.
`QUEUE_VISIBILITY_TIMEOUT` (segundos, padrao `300`) e repassada a fila como `visibility_timeout`: e o prazo
em que uma mensagem reservada por um adaptador `AcknowledgesJobs` espera o `ack` antes de voltar a fila
(veja o [guia de uso](USO.md#fila-com-confirmação-acknowledgesjobs)). Vazia ou ausente vale `300`. Um valor que nao
seja inteiro maior que zero lanca `RuntimeException` com `QUEUE_VISIBILITY_TIMEOUT deve ser um inteiro maior que zero.`
A chave `visibility_timeout` e sempre passada ao construtor da extensao da fila, como int.
Pacotes opcionais continuam fora de require; selecionar um driver sem instalar
seu pacote gera uma mensagem com a dependencia necessaria.

`EnvironmentExtensions::load($basePath, $environment)` tambem pode ser usado
separadamente e retorna list<Extension>, sem registrar as instancias.
Para configurar tudo explicitamente, passe `loadEnvironmentExtensions: false`
ao bootstrap e forneca as extensoes desejadas. Ao migrar, remova da lista local
os registros oficiais que agora serao carregados pelo ambiente, evitando duplicacao.

## Responsabilidades

- Framework: mecanismos genericos de inicializacao e defaults oficiais versionados.
- Pacotes de extensao: implementacao de cada driver e seus contratos de configuracao.
- Projeto: rotas, handlers, extensoes proprias, valores de ambiente e overrides.
- Skeleton: arquivos iniciais, Docker e CI; estes nao sao sincronizados por Composer.

Esta API nao modifica arquivos do consumidor e nao depende do namespace App.
O worker permanece implementado no pacote opcional api-queue-worker; o projeto
continua fornecendo o registro de tarefas.

CACHE_TTL e encaminhado como ttl tanto ao APCu quanto ao Redis quando definido e nao vazio. Valores zero e negativos sao preservados para o driver aplicar sua semantica. Sem a variavel, o default do pacote permanece em vigor.

## Log de acesso

O framework pode gravar uma linha `http.request` por requisição, pelo `Logger` registrado no container (por exemplo o de `elavora/api-log-mongodb`). É opt-in: nada muda se você não ligar.

### Como ligar

Pelo ambiente (`ApplicationBootstrap` / `EnvironmentExtensions`):

| Variável             | Padrão                  | Efeito                                                        |
|----------------------|-------------------------|---------------------------------------------------------------|
| `ACCESS_LOG`         | `false`                 | `true`, `1`, `on` ou `yes` liga o log de acesso                |
| `ACCESS_LOG_EXCLUDE` | `/health,/index/health` | caminhos que não são logados, separados por vírgula; vazio (`ACCESS_LOG_EXCLUDE=`) loga tudo |

Um item terminado em `*` casa por prefixo (`/interno/*`). O log de acesso vira o middleware mais externo do pipeline.
Ele resolve o `Logger` do container a cada requisição, então o `LOG_DRIVER` (ou qualquer extensão) pode registrá-lo
antes ou depois. Sem `Logger` no container, nada é gravado.

Sem o bootstrap, registre à mão, inclusive com contexto extra do app:

```php
use Elavora\Api\Framework\Logging\AccessLogExtension;

$app->extend(new AccessLogExtension(
    exclude: ['/health'],
    context: static fn (Request $request, Response $response): array => [
        'user_id' => $request->header('X-User-Id'), // o framework não conhece autenticação; o app decide
    ],
));
```

`context` é um `callable(Request, Response): array` e suas chaves são somadas às do framework (e podem
substituí-las, por exemplo `ip` atrás de um proxy confiável). Em caso de exceção não tratada, o `Response` recebido é um
substituto só com o status.

### Campos

Mensagem `http.request`; o `context` traz:

| Campo         | Conteúdo                                                                                      |
|---------------|-----------------------------------------------------------------------------------------------|
| `method`      | método HTTP                                                                                   |
| `path`        | caminho, sem query string                                                                     |
| `query_keys`  | só os NOMES dos parâmetros da query (até 50); ausente se não há query                         |
| `status`      | status HTTP da resposta (500 para exceção não tratada; o status da `HttpException` quando for ela) |
| `duration_ms` | duração do pipeline, em milissegundos                                                         |
| `bytes`       | tamanho do corpo da resposta; ausente quando a exceção sobe antes de existir resposta         |
| `request_id`  | o `X-Request-Id` da requisição                                                                |
| `ip`          | `REMOTE_ADDR` (o framework não confia em `X-Forwarded-For`)                                   |
| `user_agent`  | header `User-Agent`, limitado a 512 caracteres                                                |

Nível pelo status: `info` (abaixo de 400), `warning` (4xx) e `error` (5xx).

### Privacidade e falhas

Nunca são gravados corpo, cookies, `Authorization`, valores de query nem corpo da resposta. Qualquer exceção do logger
(ou do `context`) é capturada: o log de acesso não altera a resposta. O log é gravado depois que o pipeline responde,
mas antes do envio ao cliente; requisições recusadas antes do pipeline (corpo JSON inválido, 400) não passam pelo
middleware e não são logadas.

Exemplo de consulta no MongoDB (com `elavora/api-log-mongodb`), as 20 requisições mais lentas com erro de servidor:

```js
db.logs.find({ message: 'http.request', 'context.status': { $gte: 500 } })
  .sort({ 'context.duration_ms': -1 }).limit(20)
```

---

[README](../README.md) · Anterior: [Exemplos de código](EXEMPLOS.md) · Próximo: [Ambiente de desenvolvimento e testes](DESENVOLVIMENTO.md)

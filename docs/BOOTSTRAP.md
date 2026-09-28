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
DB_DRIVER seleciona mysql/postgresql, LOG_DRIVER seleciona stdout/file/mongodb.
A fila Redis e registrada quando seu pacote esta instalado, mesmo sem cache.
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

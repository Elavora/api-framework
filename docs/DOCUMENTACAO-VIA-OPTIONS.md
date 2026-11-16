# Documentação dos endpoints via `OPTIONS`

Um `OPTIONS` em um endpoint devolve os metadados dos attributes da action, sem executar a action. Quem consome a API
(ou uma ferramenta que gera documentação) descobre o que o endpoint aceita e devolve fazendo uma request, sem ler o
código.

```http
OPTIONS /auth/register
```

```json
{
  "attributes": {
    "methods": ["POST"],
    "fields": {"name": "App\\DataTypes\\Name", "email": "Elavora\\Api\\DataTypes\\Email"},
    "transaction": true,
    "summary": "Cadastra um usuario",
    "response": {"status": 201, "data": {"id": "uuid", "name": "string"}}
  }
}
```

## O que cada attribute expõe

Cada attribute HTTP implementa `HttpAttribute::options()`, e o framework junta o resultado de todos os attributes
da action. Quem escreve a documentação só precisa colocar os attributes que a action já usa, mais os dois de
documentação (`#[Details]` e `#[Response]`).

| Attribute | Chave em `attributes` | Conteúdo |
| --- | --- | --- |
| `#[Method]` | `methods` | métodos aceitos (e o header `Allow`, nas rotas por convenção) |
| `#[RequiredFields]` | `fields` | campo do corpo → tipo |
| `#[OptionalFields]` | `optionalFields` | campo opcional do corpo → tipo |
| `#[RequiredParams]` | `params` | parâmetro da URL → tipo |
| `#[OptionalParams]` | `optionalParams` | parâmetro opcional da URL → tipo |
| `#[Cache]` | `cache` | segundos e headers de variação |
| `#[Transaction]` | `transaction` | `true` |
| `#[Details]` | as chaves que você informar | metadados livres: resumo, descrição, tags, erros |
| `#[Response]` | `response` | o formato da resposta (veja abaixo) |

O tipo de um campo é um nome de tipo (`'string'`, `'int'`) ou o nome da classe de um DataType, como `Email::class`.

## Um endpoint documentado

```php
use Elavora\Api\Framework\Attributes\Action;
use Elavora\Api\Framework\Attributes\Details;
use Elavora\Api\Framework\Attributes\Method;
use Elavora\Api\Framework\Attributes\RequiredFields;
use Elavora\Api\Framework\Attributes\Response as ResponseDoc;

final class UserController
{
    #[Action]
    #[Method('POST')]
    #[RequiredFields(['name' => Name::class, 'email' => Email::class])]
    #[Details([
        'summary' => 'Cadastra um usuario',
        'tags' => ['users'],
        'errors' => [400 => 'Campos invalidos', 409 => 'E-mail ja cadastrado'],
    ])]
    #[ResponseDoc(['status' => 201, 'data' => User::class])]
    public function store(Request $request): Response
    {
        // ...
    }
}
```

O attribute `Response` do framework tem o mesmo nome da classe `Http\Response` que o controller devolve. Importe o
attribute com um alias (`Response as ResponseDoc`) para os dois conviverem no mesmo arquivo.

## Documentar a resposta

`#[Response]` aceita um array livre, no formato que fizer sentido para o projeto:

```php
#[ResponseDoc(['status' => 200, 'body' => ['id' => 'int', 'title' => 'string']])]
```

O framework não valida esse formato. Ele só o devolve em `OPTIONS`. Escolha uma convenção e use a mesma em todas as
actions.

### Pela própria classe: `DescribesResponse`

Quando a action devolve um objeto, o formato dele já está na classe. Para não repetir o array em cada action que o
devolve, a classe implementa `DescribesResponse`:

```php
use Elavora\Api\Framework\Contracts\DescribesResponse;
use Elavora\Api\Framework\Contracts\Responseable;

final readonly class User implements Responseable, DescribesResponse
{
    public static function responseSchema(): array
    {
        return [
            'id' => Uuid::class,
            'name' => Name::class,
            'email' => Email::class,
            'organization_id' => 'uuid|null',
        ];
    }

    public function jsonSerialize(): array
    {
        // ...
    }
}
```

e a action aponta para a classe:

```php
#[ResponseDoc(User::class)]
```

O framework chama `User::responseSchema()` e devolve o resultado em `response`.

Regras de expansão:

- **Em qualquer nível do array.** O nome de uma classe que implementa `DescribesResponse` é trocado pelo schema
  dela: `#[ResponseDoc(['status' => 201, 'data' => User::class])]`.
- **Listas.** `['data' => [User::class]]` expande o item e mantém a lista.
- **Objetos dentro de objetos.** Se o schema de `Order` tem `'buyer' => User::class`, o `buyer` também é expandido.
- **DataTypes ficam pelo nome.** `Email::class` não implementa `DescribesResponse`, então continua como nome de
  classe, igual ao que `#[RequiredFields]` devolve. Quem lê a documentação vê o mesmo tipo na entrada e na saída.
- **Nulo.** O framework não tem uma sintaxe para isso. A convenção é escrever o tipo e `|null` no texto:
  `'uuid|null'`. Para um DataType, `Uuid::class . '|null'` funciona dentro de um attribute.

Erros de configuração aparecem no `OPTIONS`, não na action:

| Situação | Resultado |
| --- | --- |
| `#[ResponseDoc(Classe::class)]` com uma classe que não implementa `DescribesResponse` | `InvalidArgumentException` |
| um schema que se referencia (direta ou indiretamente) ou passa de 16 níveis | `LogicException` |

A action continua funcionando nos dois casos, porque o attribute só é lido em `OPTIONS`.

`DescribesResponse` é independente de `Responseable`. Um não obriga o outro: um objeto pode ser devolvido sem se
descrever, e uma classe pode se descrever sem ser devolvida diretamente (como o item de uma lista).

Mantenha `responseSchema()` ao lado de `jsonSerialize()` e escreva um teste que compare as chaves dos dois. O
framework não consegue conferir que o schema acompanha o JSON.

## Metadados livres: `#[Details]`

`#[Details]` recebe um array e o devolve como está. Não há chaves obrigatórias. Para as ferramentas poderem confiar
neles, use sempre os mesmos nomes no projeto. Uma sugestão:

| Chave | Uso |
| --- | --- |
| `summary` | uma linha sobre o que a action faz |
| `description` | explicação mais longa, se precisar |
| `tags` | agrupamento na documentação gerada |
| `errors` | status → motivo dos erros que a action pode devolver |

## Attributes do projeto

Um attribute do projeto aparece em `OPTIONS` se implementar `HttpAttribute`. O `options()` dele devolve as chaves que
quiser:

```php
public function options(): array
{
    return ['access' => $this->level];
}
```

O [exemplo do repositório](EXEMPLOS.md) tem um attribute de permissão que faz isso.

## Limites

- **`OPTIONS` é público.** A action não executa, e os attributes de `BeforeRequestAttribute` (como autenticação e
  permissão) só rodam quando a action executa. Quem faz um `OPTIONS` recebe os metadados sem estar autenticado. Os
  middlewares globais do `Application` continuam valendo. Não coloque nada sensível em `#[Details]` nem no schema.
- **Vários métodos no mesmo path.** Com rotas explícitas (`$app->route(...)`) para o mesmo path em métodos
  diferentes, o `OPTIONS` devolve só os attributes da primeira rota registrada. Nas rotas por convenção
  (`/controller/action`), cada path é uma action, e o resultado é completo.
- **Path recusado.** Um `OPTIONS` num path que a convenção recusa responde `404`, sem metadados (veja
  [Segurança do roteamento](ROTEAMENTO.md#quando-uma-regra-falha)).

---

[README](../README.md) · Anterior: [Exemplos de código](EXEMPLOS.md)

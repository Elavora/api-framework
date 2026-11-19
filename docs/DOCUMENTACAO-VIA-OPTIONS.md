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
    "fields": {"name": {"type": "string"}, "email": {"type": "string", "format": "email"}},
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

## O tipo de um campo

No código, o tipo de um campo é uma regra nomeada (`'int'`, `'email'`) ou a classe de um DataType (`Email::class`).
Quem consome a API não conhece classes PHP, e o `OPTIONS` é público. Por isso a resposta **nunca mostra o nome de uma
classe**:

| No attribute | Em `OPTIONS` |
| --- | --- |
| `'int'`, `'bool'`, `'email'`, `'uuid'` (regra nomeada) | `{"type": "int"}`, `{"type": "bool"}`, `{"type": "email"}`... |
| `Name::class`, DataType com `value(): string` | `{"type": "string"}` |
| `Quantity::class`, DataType com `value(): int` | `{"type": "int"}` |
| DataType com `value()` de união (`string\|int`) ou `mixed` | `{"type": "mixed"}` |
| DataType que implementa `DescribesType` | o que `documentedType()` devolve |
| qualquer outra classe | `{"type": "Money"}` (nome curto, sem namespace) |

É a mesma ideia do DataType: ele substitui o primitivo no código e volta a ser o primitivo na saída da API. O
framework descobre o primitivo pelo tipo de retorno de `value()`. Quando isso não basta (a `value()` devolve uma
união, ou você quer informar um formato como `email` ou `uuid`), implemente `DescribesType`:

```php
use Elavora\Api\Framework\Contracts\DescribesType;

final readonly class Email implements DataType, DescribesType
{
    public static function documentedType(): array
    {
        return ['type' => 'string', 'format' => 'email'];
    }

    // ...
}
```

`documentedType()` devolve sempre um array (`['type' => 'email']`) com um nome neutro em `type`: um primitivo ou uma regra nomeada do framework (`email`, `uuid`, `url`,
`base64`, `json`). Não devolva o nome de uma classe.

### Mais dados sobre o tipo

Além do `type`, o array pode ter mais dados. Só `type` é obrigatória, e cada DataType
declara apenas o que faz sentido para ele. Um genérico declara só `{"type": "string"}`, um CPF declara máscara e regex, um
status declara só a lista de opções:

| Chave | Para quê | Exemplo |
| --- | --- | --- |
| `type` | primitivo ou regra nomeada | `string` |
| `format` | formato semântico | `email`, `date`, `uuid` |
| `mask` | como digitar ou exibir. `0` é dígito, `A` é letra, o resto é literal | `000.000.000-00` |
| `pattern` | regex sem delimitadores, num subconjunto que PCRE e JavaScript entendem | `^[0-9]{11}$` |
| `enum` | lista fechada de opções (valores escalares) | `["pending", "paid"]` |
| `example` | um valor de exemplo | `"12345678901"` |
| `description` | texto curto | `"Situação do pedido"` |

```php
public static function documentedType(): array
{
    return ['type' => 'string', 'mask' => '000.000.000-00', 'pattern' => '^[0-9]{11}$'];
}
```

Todo campo sai em `OPTIONS` como objeto, com ou sem as demais chaves, para a API ter um formato só:

```json
{"fields": {"name": {"type": "string"}, "cpf": {"type": "string", "mask": "000.000.000-00", "pattern": "^[0-9]{11}$"}}}
```

O conjunto de chaves é **fixo**, para toda a API falar o mesmo formato. O framework descarta qualquer outra chave e
as vazias. Se surgir uma necessidade nova, a mudança é feita neste pacote, não em cada projeto. Os nomes seguem o
JSON Schema e o OpenAPI (exceto `mask`), então uma ferramenta de documentação converte quase sem tradução.

`mask` e `pattern` só descrevem. Quem valida de verdade é o `isValid()` do DataType.

Isso vale igualmente para a requisição (`fields`, `params`) e para a resposta (`response`).

### `int`, `float` e `bool` em parâmetros de query

Parâmetros de query string chegam sempre como texto. Por isso, em `#[RequiredParams]` e `#[OptionalParams]`, vale o
princípio **tenta converter o texto para o tipo; se a conversão falhar, o valor é inválido**. As regras `'int'`,
`'float'` e `'bool'` aceitam o texto equivalente (em `OPTIONS` continuam `{"type": "int"}`, `{"type": "float"}` e
`{"type": "bool"}`):

- `'int'`: um inteiro ou um texto de inteiro, com sinal opcional e zeros à esquerda (`'10'`, `'-5'`, `'+1'`, `'0'`,
  `'-0'`, `'007'`, `'-007'`). Recusa vazio, espaços, decimais (`'1.0'`), notação científica (`'1e3'`), hexadecimal,
  valores acima do limite do `int` do PHP, bool, float, null e array.
- `'float'`: o float do PHP, o int do PHP (conversível, `10`) ou um texto que vira float: `'1.5'`, `'-2.0'`, `'10'`,
  `'.5'`, `'5.'`, `'+1.5'`, `'1e3'`. Recusa vazio, espaços nas pontas, `'abc'`, `'1,5'`, `'0x1A'`, `'NaN'`, `'INF'`,
  valores que estouram para infinito (`'1e999'`), null, bool e array.
- `'bool'`: o bool do PHP, os inteiros `0` e `1` e os textos `'0'`, `'1'`, `'true'` e `'false'` (sem diferenciar
  maiúsculas, sem espaços nas pontas). `'yes'`, `'no'`, `'on'` e `'off'` são recusados.

`'string'` já aceita o texto, e `array`, `object` e `null` não mudam. Só a validação aceita o texto: o valor não é
convertido e `$request->query()` devolve exatamente o que veio. Converta na action (`(int) $request->query('limit')`).
No corpo JSON (`#[RequiredFields]` e `#[OptionalFields]`) as regras continuam estritas: `"10"` não é `int`, `"1.5"` não
é `float` e `"true"` não é `bool`, porque lá o tipo já vem correto.

A regra `'int-string'` foi removida: troque por `'int'`. Uma regra desconhecida nunca valida.

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
- **DataTypes saem como o primitivo.** `Email::class` não implementa `DescribesResponse`, então segue a regra de
  [O tipo de um campo](#o-tipo-de-um-campo): aparece como `{"type": "string"}`, não como o nome da classe. Quem lê a documentação
  vê o mesmo tipo na entrada e na saída.
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

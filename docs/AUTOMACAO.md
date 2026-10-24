# Automação do repositório

[README](../README.md) · Anterior: [Versões e publicação](RELEASE.md)

## Visão geral

Quase toda a automação deste repositório vem do repositório central
[`Elavora/.github`](https://github.com/Elavora/.github). Aqui ficam só arquivos pequenos, os **chamadores**, que dizem
qual versão da automação central usar e com quais opções. Assim, uma correção na automação vale para todos os
pacotes, e cada repositório escolhe quando atualizar.

| Arquivo | Para que serve |
| --- | --- |
| `.devcontainer/devcontainer.json` | ambiente de desenvolvimento ([como usar](DESENVOLVIMENTO.md)) |
| `.github/workflows/quality.yml` | pipeline de qualidade do PR: formatação, lint, testes e comentário no PR |
| `.github/workflows/release.yml` | versão, tag, GitHub Release com notas em português e Packagist a cada merge ([regras](RELEASE.md)) |
| `.github/workflows/sync-labels.yml` | deixa as labels do repositório iguais ao catálogo central |
| `.github/workflows/codeql.yml` | análise de segurança dos workflows |
| `.github/workflows/dependency-review.yml` | aponta dependências novas com vulnerabilidade alta |
| `.github/workflows/automation.yml` | testes da automação e actionlint |
| `.github/dependabot.yml` | PRs semanais de atualização das dependências Composer e das actions |
| `.php-cs-fixer.dist.php` e `.editorconfig` | regras de estilo, vindas do pacote `elavora/code-standards` ([detalhes](#estilo-de-código-elavoracode-standards)) |

O modelo de descrição de PR não fica mais aqui: vale o
[template da organização](#modelo-de-descrição-do-pr), mantido no `Elavora/.github`.

### Qual versão do `.github`

Todos os chamadores usam a **mesma** versão do `Elavora/.github`: a tag **`1.2.0`**, fixada pelo SHA completo do
commit da tag, com o nome da tag em comentário:

```yaml
uses: Elavora/.github/.github/workflows/release.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0
```

- **Por que SHA, e não o nome da tag:** uma tag movida por engano mudaria a automação sem nenhum PR. Com o SHA, toda
  mudança passa por revisão.
- **Por que o comentário `# 1.2.0`:** é por ele que uma pessoa sabe qual versão está em uso, e é ele que o
  Dependabot atualiza junto com o SHA quando sai uma versão nova (ecossistema `github-actions`).
- **A versão aparece uma vez só.** Os chamadores não passam a entrada `automation_ref`: o workflow central descobre a
  própria versão pelo token OIDC do GitHub. Por isso cada job chamador concede `id-token: write`. Se o chamador
  repetisse a versão em `automation_ref`, o Dependabot atualizaria só o `uses`, e as duas ficariam diferentes.
  Detalhes em [Versão da automação](https://github.com/Elavora/.github/blob/1.2.0/docs/versao-da-automacao.md).

O que a `1.2.0` traz para este repositório:

- o pipeline de qualidade (`php-quality.yml`, desde a `1.1.0`);
- as notas da GitHub Release geradas em português, com resumo, grupos por label e contribuidores (ver
  [Versões e publicação](RELEASE.md#notas-da-release));
- o template de PR da organização, com a seção opcional **Notas de release**.

As regras de versão e de labels são as mesmas da `1.0.0`.

### Labels e o Dependabot

A sincronização de labels faz **prune total**: o repositório fica só com as 17 labels do
[catálogo central](https://github.com/Elavora/.github/blob/1.2.0/docs/labels.md). Na primeira execução, neste
repositório:

- são criadas `feature` e `improvement`;
- são apagadas `IA`, `ci`, `github_actions`, `github-actions`, `major`, `minor`, `patch`, `php`, `prerelease` e
  `security`, e saem de todas as issues e PRs que as usavam;
- as demais têm cor e descrição ajustadas ao catálogo.

O Dependabot, por padrão, marca os PRs com `dependencies` e com o nome do ecossistema (`php`, `github_actions`).
As de ecossistema estão fora do catálogo e seriam recriadas e apagadas toda semana. Por isso o `dependabot.yml`
define `labels: ["dependencies"]` em cada atualização: o Dependabot usa só essa label, que está no catálogo e vale
patch.

### Nenhum check trava o merge

Até esta adoção, a proteção da `main` exigia três checks: `CodeQL`, `Quality / Quality Gate` e
`Dependency Review / Dependency Review`. **A partir dela, nenhum check é obrigatório.** Todos os workflows continuam
rodando em cada PR e mostram o resultado no PR, mas nenhum impede o merge. Quem revisa decide.

| Check no PR | Workflow | Obrigatório? |
| --- | --- | --- |
| `quality / Qualidade PHP`, mais o comentário fixo no PR | `quality.yml` | não |
| `CodeQL` | `codeql.yml` | não |
| `Dependency Review` | `dependency-review.yml` | não |
| `Automação` | `automation.yml` | não |

Por que: a decisão da organização é que o pipeline avise e não bloqueie. Além disso, os nomes dos checks mudam com
a adoção, e um check obrigatório com nome antigo deixaria o PR esperando para sempre.

As outras regras da proteção (histórico linear, conversas resolvidas, sem force push e sem apagar a `main`)
continuam como estão.

A proteção é uma configuração do GitHub, fora dos arquivos do repositório. A retirada dos checks obrigatórios é
feita pelo orquestrador no momento do PR de adoção, antes do merge.

---

## Referência dos arquivos

Esta seção é o contrato de cada arquivo, conferido pelos testes da automação. Nela:

- **imagem** é `ghcr.io/elavora/php-dev:8.3-latest`, a tag que aponta sempre para a versão estável mais recente da
  imagem PHP 8.3 (só muda quando sai uma release estável do `Elavora/devcontainers`).

Os SHAs e as versões que aparecem nos exemplos desta seção são os da adoção. O Dependabot troca esses valores, e os
testes aceitam a troca. Veja
[Atualizações do Dependabot não quebram os testes](#atualizações-do-dependabot-não-quebram-os-testes).

### Regras gerais

- Nenhum arquivo em `.github/` cita o SHA antigo `1e0f42a92a4aafc35522709c30bc4e83acab82f1` (nem o prefixo `1e0f42a`).
- Todo `uses:` para `Elavora/.github/.github/workflows/...` tem uma **referência central**. O formato está em
  [Formato das referências](#formato-das-referências). Nenhum usa nome de branch, `main` ou tag sem SHA.
- Nenhum chamador passa a entrada `automation_ref` nem a entrada `image`.
- Todo job que chama um workflow de `Elavora/.github` concede `id-token: write`.
- Toda action de terceiros (`actions/*`, `github/*`) tem uma **referência de action**, no formato de
  [Formato das referências](#formato-das-referências).
- Nenhum chamador usa `secrets: inherit`.
- Os workflows passam no actionlint 1.7.12 sem avisos.

### Como os testes comparam os workflows

Cada workflow tem, abaixo, um bloco **Conteúdo esperado**. O teste compara o arquivo com esse bloco, linha a linha.

A única parte que pode ser diferente é a referência no fim de cada linha `uses:`: o `@`, o SHA, o espaço e o
comentário de versão. Nessa parte, o teste confere o formato, e não o valor.

Todo o resto precisa ser igual ao bloco: os gatilhos, as permissões, os jobs, os passos, a ordem dos passos, as
entradas `with`, os comandos `run` e o caminho de cada action ou workflow usado (o texto antes do `@`).

Exemplo com o bloco do `dependency-review.yml`:

| Linha no arquivo | Resultado | Por quê |
| --- | --- | --- |
| `- uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4` | passa | outro SHA, formato válido |
| `- uses: actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v5.0.0` | passa | outro SHA e outra versão, formato válido |
| `- uses: actions/dependency-review-action@v4` | falha | tag em vez de SHA |
| `- uses: actions/some-other-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4` | falha | outra action |
| `fail-on-severity: critical` no lugar de `fail-on-severity: high` | falha | outra entrada `with` |
| `pull-requests: write` acrescentado às permissões | falha | outra permissão |

Os outros arquivos com **Conteúdo exato** (`devcontainer.json`, `dependabot.yml` e `.php-cs-fixer.dist.php`) são
comparados por inteiro, sem nenhuma parte livre. O Dependabot não muda esses arquivos.

### Formato das referências

Uma **referência central** é o fim de um `uses:` que chama o `Elavora/.github`. O formato é:
`@` + SHA + espaço + `# ` + versão `X.Y.Z`.

Uma **referência de action** é o fim de um `uses:` que chama uma action de terceiros. O formato é:
`@` + SHA + espaço + `# v` + versão com uma, duas ou três partes (`v4`, `v4.39`, `v4.39.0`).

Nos dois casos:

- o SHA tem exatamente 40 caracteres, todos de `0` a `9` ou de `a` a `f` (minúsculos);
- o comentário é o último texto da linha;
- cada parte da versão é um número sem sinal (`0`, `7`, `39`).

| Referência | Tipo | Resultado | Por quê |
| --- | --- | --- | --- |
| `@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0` | central | passa | |
| `@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0` | central | passa | outro SHA e outra versão |
| `@3e8259291c0d3f7c031695fe63d714169792c454 # v1.2.0` | central | falha | a versão central não tem `v` |
| `@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2` | central | falha | a versão central tem três partes |
| `@1.2.0` | central | falha | tag em vez de SHA |
| `@3e8259291c0d3f7c031695fe63d714169792c454` | central | falha | sem comentário de versão |
| `@3e82592 # 1.2.0` | central | falha | SHA curto |
| `@3E8259291C0D3F7C031695FE63D714169792C454 # 1.2.0` | central | falha | SHA com letras maiúsculas |
| `@3e8259291c0d3f7c031695fe63d714169792c45g # 1.2.0` | central | falha | `g` não é hexadecimal |
| `@main # 1.2.0` | central | falha | nome de branch |
| `@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4` | action | passa | |
| `@6f2ac81f5cd81fe351a197ba0b00d91d03cd2aee # v4.39.0` | action | passa | outro SHA e outra versão |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1` | action | passa | |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # 7.0.1` | action | falha | falta o `v` |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1.2` | action | falha | quatro partes |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1 # latest` | action | falha | não é versão |
| `@v7.0.1` | action | falha | tag em vez de SHA |
| `@3d3c42e5aac5ba805825da76410c181273ba90b1` | action | falha | sem comentário de versão |

### `.devcontainer/devcontainer.json`

Igual ao `examples/devcontainer.json` da versão `0.3.0` do `Elavora/devcontainers`, trocando a imagem pela
`8.3-latest`. Conteúdo exato:

```json
{
  "name": "Elavora PHP 8.3",
  "image": "ghcr.io/elavora/php-dev:8.3-latest",
  "remoteUser": "vscode",
  "postCreateCommand": "git config --global --add safe.directory \"$(pwd)\" && composer install --no-interaction --prefer-dist",
  "customizations": {
    "vscode": {
      "extensions": [
        "xdebug.php-debug",
        "bmewburn.vscode-intelephense-client",
        "junstyle.php-cs-fixer",
        "EditorConfig.EditorConfig"
      ],
      "settings": {
        "php.validate.executablePath": "/usr/local/bin/php",
        "php-cs-fixer.executablePath": "/usr/local/bin/php-cs-fixer",
        "php-cs-fixer.config": ".php-cs-fixer.php;.php-cs-fixer.dist.php",
        "php-cs-fixer.rules": "@PER-CS",
        "php-cs-fixer.onsave": false,
        "[php]": {
          "editor.defaultFormatter": "junstyle.php-cs-fixer",
          "editor.formatOnSave": true
        }
      }
    }
  }
}
```

- É JSON válido (sem comentários).
- `image` é exatamente `ghcr.io/elavora/php-dev:8.3-latest`. Não pode ser uma versão fixa (`8.3-0.3.0`,
  `8.3-0.2.0` ou qualquer `8.3-X.Y.Z`), outra tag móvel (`8.3`, `latest`) nem o nome local `elavora/php-dev:8.3`.
  Por que a `8.3-latest`: o ambiente acompanha cada release estável da imagem sem PR de troca. Pré-releases não movem
  a tag, e o contêiner só passa a usar a versão nova quando é reconstruído (ver
  [Atualizações da imagem](DESENVOLVIMENTO.md#atualizações-da-imagem)).
- `remoteUser` é `vscode`, e `postCreateCommand` contém `composer install`.
- As extensões incluem `junstyle.php-cs-fixer` e `EditorConfig.EditorConfig`, e `editor.formatOnSave` é `true` para
  PHP: o arquivo é formatado ao salvar.

### `.github/workflows/release.yml`

Conteúdo esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: Release

on:
  pull_request:
    types: [closed]
    branches: [main]

permissions:
  contents: read

jobs:
  release:
    if: github.event.pull_request.merged == true
    permissions:
      contents: write
      pull-requests: read
      id-token: write
    uses: Elavora/.github/.github/workflows/release.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0
    secrets:
      PACKAGIST_WEBHOOK_URL: ${{ secrets.PACKAGIST_WEBHOOK_URL }}
```

- Sem bloco `with`. A entrada `package_name` não é passada: a partir da `1.2.0` ela é ignorada, e o título da release
  vem do nome do repositório (`Elavora/api-framework 2.0.0`). A entrada `packagist` também não é passada: o padrão
  `true` liga o job do Packagist, e `packagist: false` é proibido.
- O único secret repassado é `PACKAGIST_WEBHOOK_URL`.

### `.github/workflows/sync-labels.yml`

Conteúdo esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: Sincronizar labels

on:
  schedule:
    - cron: '17 3 * * 1'
  workflow_dispatch:

permissions:
  contents: read

jobs:
  sync:
    permissions:
      contents: read
      issues: write
      id-token: write
    uses: Elavora/.github/.github/workflows/sync-labels.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0
```

- Sem bloco `with` (a entrada `prune` não existe mais; o prune é sempre total) e sem bloco `secrets`.

### `.github/workflows/quality.yml`

Chamador do workflow reutilizável `php-quality.yml` do `Elavora/.github`. Conteúdo
esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: Qualidade

on:
  pull_request:
    branches: [main]
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  quality:
    permissions:
      contents: write
      pull-requests: write
      id-token: write
    uses: Elavora/.github/.github/workflows/php-quality.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0
```

O arquivo termina na linha do `uses:`, com uma quebra de linha no final: não existe bloco `with:`.

- O arquivo continua se chamando `quality.yml`, então o badge do README continua funcionando.
- O id do job é `quality`, e o check aparece no PR como **`quality / Qualidade PHP`**.
- `contents: write` permite o commit de formatação na branch do PR, e `pull-requests: write`, o comentário fixo.
- Não passa a entrada `image`: o CI usa a imagem padrão do pipeline central. Assim a imagem do CI é atualizada junto
  com o `Elavora/.github`, sem PR neste repositório só para trocar a imagem.
  - No SHA fixado (1.2.0), esse padrão é `ghcr.io/elavora/php-dev:8.3-0.2.0`. Ela foi gerada do mesmo Dockerfile da
    `8.3-0.3.0`, então traz as mesmas ferramentas do devcontainer.
  - Uma próxima versão do `Elavora/.github` muda o padrão para `8.3-latest`. Quando ela sair, o Dependabot abre o PR
    que sobe o SHA e o comentário de versão (ver [`.github/dependabot.yml`](#githubdependabotyml)), e a partir daí o
    CI usa a mesma tag do devcontainer.
  - Enquanto o SHA for o da 1.2.0, a diferença de tag entre o CI (`8.3-0.2.0`) e o devcontainer (`8.3-latest`) é
    esperada.
- Nenhuma entrada mexe em cobertura, `composer audit` ou dependências mínimas: as três ficam no padrão do central
  (cobertura mínima de 80%, auditoria ligada e `lowest`), que reproduz as garantias do `quality.yml` antigo.
- Não existe nenhum job local de PHP neste arquivo (matriz, cobertura ou `setup-php`): tudo vem do central.
- Sem bloco `concurrency`: o workflow central já cancela a execução anterior do mesmo PR.

### `phpunit.xml`

O pipeline central roda os testes com cobertura:
`XDEBUG_MODE=coverage composer test -- --coverage-clover=<arquivo>`.

O `phpunit.xml` tem o bloco abaixo, filho direto de `<phpunit>`:

```xml
<source>
    <include>
        <directory>src</directory>
    </include>
</source>
```

Esse bloco diz ao PHPUnit que a cobertura mede a pasta `src/`.

Sem o bloco, o PHPUnit 10.5 avisa `No filter is configured, code coverage will not be processed`. Como o
`phpunit.xml` tem `failOnWarning="true"`, o aviso faz o `composer test` sair com código 1, mesmo com todos os testes
passando. Além disso, nenhuma cobertura é gerada.

Com o bloco, `XDEBUG_MODE=coverage composer test -- --coverage-clover=<arquivo>` cumpre três condições:

- sai com código 0 quando todos os testes passam;
- não mostra o aviso `No filter is configured`;
- gera o `<arquivo>` de cobertura, com os arquivos de `src/`.

O restante do `phpunit.xml` não muda: `bootstrap`, `cacheDirectory`, `colors`, `failOnWarning="true"` e a suíte
`tests`.

### Estilo de código (`elavora/code-standards`)

As regras de estilo (PHP-CS-Fixer com PER-CS e o `.editorconfig` base) e o lint de sintaxe vêm do pacote Composer
[`elavora/code-standards`](https://packagist.org/packages/elavora/code-standards), a partir da **1.1.0**, publicado
no Packagist. O contrato completo está no
[guia de adoção do pacote](https://github.com/Elavora/code-standards/blob/1.1.0/docs/adocao.md). O lint está em
[Lint de sintaxe PHP](https://github.com/Elavora/code-standards/blob/1.1.0/docs/lint.md).

**`composer.json`:**

- `elavora/code-standards` entra em `require-dev`, com a restrição `^1.1`. Não entra no `require`.
- A restrição precisa começar no minor 1 ou maior. Motivo: o CI roda o `composer lint` com as dependências nas
  versões mínimas permitidas. Com `^1.0`, a mínima é a 1.0.0, que não tem o subcomando `lint`, e o CI falha.
- O teste confere o major e o minor mínimo, e não o valor exato: a restrição é `^1.` seguido do minor, e
  opcionalmente do patch, com minor maior ou igual a 1. Assim, o PR do Dependabot que sobe a restrição continua
  passando (veja
  [Atualizações do Dependabot não quebram os testes](#atualizações-do-dependabot-não-quebram-os-testes)).

  | Restrição | Resultado | Por quê |
  | --- | --- | --- |
  | `^1.1` | passa | |
  | `^1.2` | passa | minor maior |
  | `^1.1.3` | passa | com patch |
  | `^1.10` | passa | minor 10 |
  | `^1.0` | falha | a 1.0.0 não tem o lint |
  | `^1.0.5` | falha | minor 0 |
  | `^2.0` | falha | outro major |
  | `^1.1 \|\| ^2.0` | falha | aceita outro major |
  | `~1.1` | falha | outro operador |

- `friendsofphp/php-cs-fixer` não entra em nenhuma seção: o binário vem da imagem.
- `scripts` ganha as duas entradas abaixo, exatamente assim:

  ```json
  "post-install-cmd": ["Elavora\\CodeStandards\\Composer::sync"],
  "post-update-cmd": ["Elavora\\CodeStandards\\Composer::sync"]
  ```

  Por que o método PHP, e não `vendor/bin/elavora-standards sync`: numa instalação `composer install --no-dev`, o
  pacote não existe, e um comando em `vendor/bin` faria a instalação falhar. Com o método, o Composer só avisa
  `Class Elavora\CodeStandards\Composer is not autoloadable, can not call post-install-cmd script` e segue com
  sucesso. Esse aviso é esperado.
- O script `lint` é exatamente `"lint": "elavora-standards lint"`, sem argumentos. Antes era `php tools/lint.php`.
  Veja [Lint de sintaxe](#lint-de-sintaxe).
- Os scripts `test`, `analyse` e `check` continuam existindo, com o mesmo conteúdo. O `check` continua chamando
  `@lint`. O `test` continua chamando o PHPUnit direto (`phpunit -c phpunit.xml`), porque o pipeline acrescenta
  argumentos de cobertura a ele.
- `extra.branch-alias.dev-main` passa de `1.0-dev` para `2.0-dev`, porque a `main` passa a ser a linha 2.

**`.php-cs-fixer.dist.php`** (raiz, versionado): é o stub que a sincronização cria. Conteúdo exato, em bytes (fim de
linha LF e uma quebra de linha no final):

```php
<?php

require_once __DIR__ . '/vendor/elavora/code-standards/src/PhpCsFixer.php';

return Elavora\CodeStandards\PhpCsFixer::config(__DIR__);
```

Ele carrega só o arquivo da classe, e não o `vendor/autoload.php`, para não misturar as dependências do projeto com
as do PHP-CS-Fixer. Não acrescente regras nele.

**`.editorconfig`** (raiz, versionado): é a cópia gerada pela sincronização e contém só o bloco gerado: a
primeira linha é `# >>> elavora/code-standards >>>` e a última é `# <<< elavora/code-standards <<<`. O conteúdo do
bloco é o que o pacote escreve; não edite à mão, porque o próximo `composer install` reescreve o bloco. Este
repositório não tem seções próprias depois do bloco. Na prática, o arquivo é criado pelo primeiro `composer install`
depois de adicionar o pacote, e é esse resultado que se commita.

**`.gitignore`** ganha duas linhas:

```
/.elavora/
/.php-cs-fixer.php
```

- `/.elavora/` é a pasta onde o CI baixa os scripts centrais.
- `/.php-cs-fixer.php` é o override pessoal opcional de quem desenvolve (ver
  [Ajustar as regras](https://github.com/Elavora/code-standards/blob/1.1.0/docs/php-cs-fixer.md)).

As linhas que já existem no `.gitignore` continuam.

### Lint de sintaxe

O `composer lint` roda `elavora-standards lint`, o subcomando do `elavora/code-standards`. Ele roda `php -l` em cada
arquivo PHP e falha se algum não compilar. Não é análise estática (isso é o PHPStan) nem formatação.

O repositório não tem mais lint próprio. O `tools/lint.php` foi apagado, e a pasta `tools/` também, porque ficou
vazia. Motivo: uma cópia local não recebe correções. Com o lint no pacote, as melhorias chegam pelo PR do
Dependabot, junto com as regras de estilo.

Quais arquivos entram: todo `.php` do projeto, menos a pasta `vendor`, os arquivos e pastas cujo nome começa com
ponto e o que for passado em `--exclude`. O script não usa `--exclude`.

| Arquivo | Conferido? | Por quê |
| --- | --- | --- |
| `src/Application.php` | sim | |
| `tests/ApplicationTest.php` | sim | |
| `tests/Fixtures/App/Http/Controller/HealthController.php` | sim | |
| `.php-cs-fixer.dist.php` | não | nome começa com ponto |
| `.phpstan.cache/resultCache.php` | não | pasta começa com ponto |
| `vendor/autoload.php` | não | pasta `vendor` |
| `tests/automation/workflows.test.cjs` | não | não é `.php` |

Hoje, na prática, a lista é a mesma do script antigo: `src/` e `tests/`. Uma pasta nova com PHP, como `examples/`,
passa a ser conferida sem mudar o script.

O que muda na saída:

| | `tools/lint.php` (antigo) | `elavora-standards lint` |
| --- | --- | --- |
| Sem erro | uma linha `No syntax errors detected in ...` por arquivo | uma linha só: `elavora/code-standards: lint ok: N arquivo(s) sem erro de sintaxe` |
| Com erro | a mensagem do `php -l` | só os arquivos com erro, cada um com a mensagem, e um resumo no fim; código de saída 1 |

Depois de `composer install --no-dev`, o comando não existe e o `composer lint` falha. Isso é esperado: o lint é só
para desenvolvimento e CI.

Para rodar só uma pasta, numa execução: `composer lint -- src`. Os detalhes estão no
[guia do lint](https://github.com/Elavora/code-standards/blob/1.1.0/docs/lint.md).

### Primeira formatação

A adoção do PER-CS reformata arquivos PHP que já existem (`src/`, `tests/` e `tools/`). Essa mudança entra neste
mesmo PR, num **commit separado e só de formatação**, com a mensagem `style: aplica a formatação PER-CS`.

- É feito com o PHP-CS-Fixer da imagem `8.3-0.3.0` e a configuração do repositório (`php-cs-fixer fix`, na raiz,
  depois do `composer install`), para que o pipeline do PR já encontre o código formatado.
- Não muda nenhum comportamento: os testes passam antes e depois dele.
- Não mistura outras mudanças: nada de `composer.json`, workflows ou documentação nesse commit.

Se sobrar algo, o pipeline do PR completa a formatação com o commit automático `style: organiza o código`.

### `.github/workflows/codeql.yml`

Deixa de chamar o workflow central antigo e passa a ter o job local. Conteúdo
esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: CodeQL

on:
  pull_request:
    branches: [main]
    types: [opened, synchronize, reopened, ready_for_review]
  merge_group:
  schedule:
    - cron: "0 9 * * 1"
  workflow_dispatch:

permissions:
  contents: read
  security-events: write

jobs:
  analyze:
    name: CodeQL
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - uses: github/codeql-action/init@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4
        with:
          languages: actions
      - uses: github/codeql-action/analyze@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4
```

### `.github/workflows/dependency-review.yml`

Conteúdo esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: Dependency Review

on:
  pull_request:
    branches: [main]

permissions:
  contents: read

jobs:
  dependency-review:
    name: Dependency Review
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false
      - uses: actions/dependency-review-action@2031cfc080254a8a887f58cffee85186f0e49e48 # v4
        with:
          fail-on-severity: high
```

### `.github/workflows/automation.yml`

Conteúdo esperado ([como o teste compara](#como-os-testes-comparam-os-workflows)):

```yaml
name: Automação

on:
  pull_request:
  push:
    branches: [main]

permissions:
  contents: read

jobs:
  tests:
    name: Automação
    runs-on: ubuntu-latest
    timeout-minutes: 10
    steps:
      - uses: actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1
        with:
          persist-credentials: false

      - name: Testes da automação
        run: docker run --rm -v "$PWD:/w:ro" -w /w node:22-alpine node --test "tests/automation/*.test.cjs"

      - name: actionlint
        run: docker run --rm -v "$PWD:/w:ro" -w /w rhysd/actionlint:1.7.12 -no-color
```

### `.github/dependabot.yml`

Duas atualizações, `composer` e `github-actions`, com a mesma agenda e o mesmo limite de hoje. Cada uma ganha
`labels: ["dependencies"]` e `commit-message`, para que os commits e os títulos dos PRs do Dependabot sigam o
Conventional Commits da organização.

A entrada `github-actions` tem também o grupo `codeql-action`. Ele junta numa só PR as atualizações de
`github/codeql-action/init` e `github/codeql-action/analyze` (veja
[Grupo do CodeQL](#grupo-do-codeql)).

Conteúdo exato:

```yaml
version: 2
updates:
  - package-ecosystem: "composer"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "America/Sao_Paulo"
    open-pull-requests-limit: 5
    labels: ["dependencies"]
    commit-message:
      prefix: "chore"
      prefix-development: "chore"
      include: "scope"

  - package-ecosystem: "github-actions"
    directory: "/"
    schedule:
      interval: "weekly"
      day: "monday"
      time: "09:00"
      timezone: "America/Sao_Paulo"
    open-pull-requests-limit: 5
    labels: ["dependencies"]
    commit-message:
      prefix: "ci"
      include: "scope"
    groups:
      codeql-action:
        patterns:
          - "github/codeql-action*"
```

O que cada atualização cobre:

| Ecossistema | O que o Dependabot atualiza |
| --- | --- |
| `composer` | `phpstan/phpstan`, `phpunit/phpunit` e `elavora/code-standards` (as regras de estilo) |
| `github-actions` | as actions de terceiros e os workflows reutilizáveis do `Elavora/.github` (o SHA e o comentário `# 1.2.0`) |

**A imagem não precisa de entrada.** O devcontainer usa a tag móvel `8.3-latest`, que já acompanha as releases
estáveis, e o CI usa o padrão do pipeline central, que muda junto com o SHA do `Elavora/.github` (coberto pela
entrada `github-actions`). Além disso, o ecossistema `devcontainers` do Dependabot só atualiza *features* do
`devcontainer.json`, e este arquivo não usa nenhuma; o ecossistema `docker` só lê `Dockerfile` e arquivos Compose, e
o repositório não tem nenhum. Por isso não existe uma terceira entrada (ver
[Atualizações da imagem](DESENVOLVIMENTO.md#atualizações-da-imagem)).

Regras conferidas pelos testes:

- Toda entrada de `updates` tem `labels`, e toda label listada está no catálogo central.
- Toda entrada de `updates` tem `commit-message` com `include: "scope"`. O `prefix` é `chore` para `composer` (com
  `prefix-development` também `chore`) e `ci` para `github-actions`.
- Não existe entrada para outros ecossistemas.
- A entrada `github-actions` tem exatamente um grupo, `codeql-action`, com exatamente um padrão:
  `github/codeql-action*`. O grupo não tem outras chaves (`applies-to`, `dependency-type`, `update-types`,
  `exclude-patterns`).
- A entrada `composer` não tem grupos.

Resultado: os PRs saem como `chore(deps): bump phpstan/phpstan from 2.2.0 to 2.2.1`, `chore(deps-dev): ...` (para
dependências de desenvolvimento) e `ci(deps): bump actions/checkout from 7.0.1 to 7.1.0`. O PR do grupo sai como
`ci(deps): bump the codeql-action group with 2 updates`.

### Grupo do CodeQL

O `codeql.yml` usa duas actions do mesmo repositório: `github/codeql-action/init` e `github/codeql-action/analyze`.
Elas precisam estar na mesma versão. O `analyze` lê o que o `init` preparou.

Sem grupo, o Dependabot trata as duas como dependências separadas. Ele abre um PR para cada uma, e cada PR deixa as
duas em versões diferentes até o outro entrar. Foi o que aconteceu no PR #53: ele subiu só o `analyze`.

Com o grupo, as duas sobem juntas num PR só.

O padrão `github/codeql-action*` pega o `init`, o `analyze` e qualquer outra action desse repositório que o
workflow passe a usar. Ele não pega nenhuma outra action do `codeql.yml`: o `actions/checkout` continua no seu
próprio PR.

O grupo vale só para as atualizações de versão. As atualizações de segurança seguem o padrão do Dependabot, um PR por
dependência. É o comportamento padrão quando o grupo não define `applies-to`.

Os testes não exigem que o `init` e o `analyze` tenham o mesmo SHA. Quem garante isso é o grupo. Assim, um PR antigo
que suba só uma delas continua passando nos testes.

**Limitação:** a regra da organização é que todo commit, inclusive os de bots, seja em português do Brasil e siga o
Conventional Commits. O Dependabot só deixa configurar o prefixo. O resto da mensagem (`bump X from A to B`) é
gerado por ele em inglês e não pode ser traduzido. O prefixo segue o padrão, e o texto em inglês fica como exceção
aceita.

### Commits feitos por workflows

O único workflow que commita neste repositório é o de qualidade, quando corrige a formatação num PR. A mensagem é
definida no `Elavora/.github` e é exatamente `style: organiza o código`. Em PR do Dependabot, PR de fork e push na
`main`, ele não commita: só avisa no relatório.

### Modelo de descrição do PR

O repositório **não tem** template de PR próprio. Não existe `.github/pull_request_template.md` (nem
`PULL_REQUEST_TEMPLATE.md`, em qualquer caixa, em `.github/`, na raiz ou em `docs/`).

Sem template próprio, o GitHub usa o
[template da organização](https://github.com/Elavora/.github/blob/1.2.0/.github/PULL_REQUEST_TEMPLATE.md), com as
seções `Objetivo`, `Principais mudanças`, `Testes executados`, `Riscos` e **`Notas de release`**. A última é
opcional: o texto escrito nela substitui o título do PR na GitHub Release (ver
[Notas da release](RELEASE.md#notas-da-release)).

Por que remover o template antigo: ele indicava labels que não existem mais (`Nao gera release`, `dependencies` como
minor) e não tinha a seção "Notas de release". Manter uma cópia do template da organização aqui exigiria atualizá-la
à mão a cada versão do `.github`.

### Arquivos removidos

- `.github/pull_request_template.md`, pelo motivo acima.
- `CONTRIBUTING.md`, porque estava desatualizado (versão de PHP e checks antigos). Ele será revisto e reescrito no
  futuro. Enquanto isso, o fluxo de contribuição está no [README](../README.md#documentação) e nestes documentos.
- `tools/lint.php` e a pasta `tools/`, porque o lint passou para o `elavora/code-standards` (veja
  [Lint de sintaxe](#lint-de-sintaxe)).
- Nenhum arquivo `packagist-sync.yml` existe em `.github/workflows/`.
- Nenhum workflow local usa `shivammathur/setup-php`.

## Testes da automação

Os testes ficam em `tests/automation/*.test.cjs`, usam só o `node:test` (sem dependências) e leem os arquivos do
repositório como texto ou JSON. Eles conferem as regras da [Referência dos arquivos](#referência-dos-arquivos):
workflows, Dependabot, devcontainer, os arquivos de estilo, as entradas do `composer.json` e do `.gitignore` e os
arquivos removidos.

Os arquivos `.test.cjs` não são PHP: o PHPUnit e o `composer lint` os ignoram.

### Atualizações do Dependabot não quebram os testes

O Dependabot abre PRs que mudam referências fixas. Esses PRs precisam passar no check **Automação** sem ninguém
editar os testes.

Antes, os testes conferiam o valor exato de cada SHA e de cada versão. Todo PR do Dependabot falhava, porque mudar
esse valor é justamente o que ele faz. Os PRs #52 e #53 falharam por isso. Esse comportamento está errado.

Por isso os testes conferem o **formato** das referências que o Dependabot atualiza, e nunca o **valor**:

| O que o Dependabot muda | O que o teste confere | O que o teste não confere |
| --- | --- | --- |
| SHA e comentário do `Elavora/.github` nos chamadores | a [referência central](#formato-das-referências) | qual SHA e qual versão |
| SHA e comentário das actions de terceiros | a [referência de action](#formato-das-referências) | qual SHA e qual versão |
| Restrição de `elavora/code-standards` no `composer.json` | a restrição é `^1.` seguido do minor (1 ou maior) e, opcionalmente, do patch (`^1.1`, `^1.2`, `^1.1.2`) | qual minor, a partir do 1, e qual patch |
| Restrições de `phpstan/phpstan` e `phpunit/phpunit` | o pacote está em `require-dev` | a restrição |
| Bloco do `.editorconfig`, quando a versão nova do pacote muda o bloco | os marcadores de início e fim | o conteúdo do bloco |
| `composer.lock` | nada | o conteúdo do lock |

A estrutura continua conferida. Um PR que muda um job, um passo, uma permissão, uma entrada `with` ou a action usada
falha no teste (veja [Como os testes comparam os workflows](#como-os-testes-comparam-os-workflows)).

Exemplos que passam:

- o PR #52: `actions/dependency-review-action@2031cfc080254a8a887f58cffee85186f0e49e48 # v4` trocado por
  `actions/dependency-review-action@a1d282b36b6f3519aa1f3fc636f609c47dddb294 # v4`;
- o PR #53: `github/codeql-action/analyze@e4fba868fa4b1b91e1fdab776edc8cfbe6e9fb81 # v4` trocado por
  `github/codeql-action/analyze@6f2ac81f5cd81fe351a197ba0b00d91d03cd2aee # v4`;
- `Elavora/.github/.github/workflows/release.yml@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0` trocado por
  `Elavora/.github/.github/workflows/release.yml@d96e9bb606f84016dd031ad748d009d820fd5487 # 1.3.0`;
- `actions/checkout@3d3c42e5aac5ba805825da76410c181273ba90b1 # v7.0.1` trocado por outro SHA com `# v7.1.0`;
- `"elavora/code-standards": "^1.1"` trocado por `"^1.2"`;
- `"phpunit/phpunit": "^10.5 || ^11.0 || ^12.0"` trocado por `"^10.5 || ^11.0 || ^12.0 || ^13.0"`.

Exemplos que falham:

- `Elavora/.github/.github/workflows/release.yml@1.3.0` (tag em vez de SHA);
- `Elavora/.github/.github/workflows/release.yml@d96e9bb606f84016dd031ad748d009d820fd5487` (sem o comentário de
  versão);
- `actions/checkout@3d3c42e # v7.0.1` (SHA curto);
- `"elavora/code-standards": "^2.0"` (outro major);
- `"elavora/code-standards": "^1.1 || ^2.0"` (aceita outro major);
- `"elavora/code-standards": "^1.0"` (minor 0: a 1.0.0 não tem o lint).

Por que o major de `elavora/code-standards` é conferido: um major novo muda as regras de estilo e reformata o código.
Essa troca precisa de um PR próprio, que atualize também esta documentação e o teste.

Por que o minor mínimo é 1: o `composer lint` usa o subcomando `lint`, que só existe a partir da 1.1.0 (veja
[Estilo de código](#estilo-de-código-elavoracode-standards)).

Como rodar: veja [Testes da automação](DESENVOLVIMENTO.md#testes-da-automação). No CI, rodam no workflow
**Automação**, em todo PR e em todo push na `main`.

---

[README](../README.md) · Anterior: [Versões e publicação](RELEASE.md)

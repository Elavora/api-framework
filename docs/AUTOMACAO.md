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

- **SHA da 1.2.0** é `3e8259291c0d3f7c031695fe63d714169792c454`, o commit da tag `1.2.0` do `Elavora/.github`;
- **imagem** é `ghcr.io/elavora/php-dev:8.3-0.3.0`.

### Regras gerais

- Nenhum arquivo em `.github/` cita o SHA antigo `1e0f42a92a4aafc35522709c30bc4e83acab82f1` (nem o prefixo `1e0f42a`).
- Todo `uses:` para `Elavora/.github/.github/workflows/...` termina exatamente em
  `@3e8259291c0d3f7c031695fe63d714169792c454 # 1.2.0`. Nenhum usa nome de branch, `main`, tag sem SHA ou outro SHA.
- Nenhum chamador passa a entrada `automation_ref`.
- Todo job que chama um workflow de `Elavora/.github` concede `id-token: write`.
- Toda action de terceiros (`actions/*`, `github/*`) é fixada por SHA completo (40 caracteres hexadecimais), com a
  versão em comentário.
- Nenhum chamador usa `secrets: inherit`.
- Os workflows passam no actionlint 1.7.12 sem avisos.

### `.devcontainer/devcontainer.json`

Igual ao `examples/devcontainer.json` da versão `0.3.0` do `Elavora/devcontainers`, trocando a imagem pela
`8.3-0.3.0`. Conteúdo exato:

```json
{
  "name": "Elavora PHP 8.3",
  "image": "ghcr.io/elavora/php-dev:8.3-0.3.0",
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
- `image` é exatamente `ghcr.io/elavora/php-dev:8.3-0.3.0`. Não pode ser uma tag móvel (`8.3`, `latest`,
  `8.3-latest`) nem o nome local `elavora/php-dev:8.3`. A `8.3-latest` existe e aponta para a estável mais recente,
  mas mudaria o ambiente sem PR.
- `remoteUser` é `vscode`, e `postCreateCommand` contém `composer install`.
- As extensões incluem `junstyle.php-cs-fixer` e `EditorConfig.EditorConfig`, e `editor.formatOnSave` é `true` para
  PHP: o arquivo é formatado ao salvar.

### `.github/workflows/release.yml`

Conteúdo exato:

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

Conteúdo exato:

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

Chamador do workflow reutilizável `php-quality.yml` do `Elavora/.github`. Conteúdo exato:

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
    with:
      image: ghcr.io/elavora/php-dev:8.3-0.3.0
```

- O arquivo continua se chamando `quality.yml`, então o badge do README continua funcionando.
- O id do job é `quality`, e o check aparece no PR como **`quality / Qualidade PHP`**.
- `contents: write` permite o commit de formatação na branch do PR, e `pull-requests: write`, o comentário fixo.
- `image` é a mesma imagem do devcontainer. Sem ela, o central usaria o padrão dele (`8.3-0.2.0`), e o CI teria
  ferramentas diferentes das do editor.
- Nenhuma entrada mexe em cobertura, `composer audit` ou dependências mínimas: as três ficam no padrão do central
  (cobertura mínima de 80%, auditoria ligada e `lowest`), que reproduz as garantias do `quality.yml` antigo.
- Não existe nenhum job local de PHP neste arquivo (matriz, cobertura ou `setup-php`): tudo vem do central.
- Sem bloco `concurrency`: o workflow central já cancela a execução anterior do mesmo PR.

### Estilo de código (`elavora/code-standards`)

As regras de estilo (PHP-CS-Fixer com PER-CS e o `.editorconfig` base) vêm do pacote Composer
[`elavora/code-standards`](https://packagist.org/packages/elavora/code-standards) **1.0.0**, publicado no Packagist.
O contrato completo está no
[guia de adoção do pacote](https://github.com/Elavora/code-standards/blob/1.0.0/docs/adocao.md).

**`composer.json`:**

- `elavora/code-standards` entra em `require-dev`, com a restrição `^1.0`. Não entra no `require`.
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
- Os scripts `lint`, `test`, `analyse` e `check` continuam existindo, com o mesmo conteúdo. O `test` continua
  chamando o PHPUnit direto (`phpunit -c phpunit.xml`), porque o pipeline acrescenta argumentos de cobertura a ele.
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
  [Ajustar as regras](https://github.com/Elavora/code-standards/blob/1.0.0/docs/php-cs-fixer.md)).

As linhas que já existem no `.gitignore` continuam.

### Primeira formatação

A adoção do PER-CS reformata arquivos PHP que já existem (`src/`, `tests/` e `tools/`). Essa mudança entra neste
mesmo PR, num **commit separado e só de formatação**, com a mensagem `style: aplica a formatação PER-CS`.

- É feito com o PHP-CS-Fixer da imagem `8.3-0.3.0` e a configuração do repositório (`php-cs-fixer fix`, na raiz,
  depois do `composer install`), para que o pipeline do PR já encontre o código formatado.
- Não muda nenhum comportamento: os testes passam antes e depois dele.
- Não mistura outras mudanças: nada de `composer.json`, workflows ou documentação nesse commit.

Se sobrar algo, o pipeline do PR completa a formatação com o commit automático `style: organiza o código`.

### `.github/workflows/codeql.yml`

Deixa de chamar o workflow central antigo e passa a ter o job local. Conteúdo exato:

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

Conteúdo exato:

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

Conteúdo exato:

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
Conventional Commits da organização. Conteúdo exato:

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
```

O que cada atualização cobre:

| Ecossistema | O que o Dependabot atualiza |
| --- | --- |
| `composer` | `phpstan/phpstan`, `phpunit/phpunit` e `elavora/code-standards` (as regras de estilo) |
| `github-actions` | as actions de terceiros e os workflows reutilizáveis do `Elavora/.github` (o SHA e o comentário `# 1.2.0`) |

**A imagem do devcontainer não é coberta.** O ecossistema `devcontainers` do Dependabot só atualiza *features* do
`devcontainer.json`, e este arquivo não usa nenhuma. O ecossistema `docker` só lê `Dockerfile` e arquivos Compose, e
o repositório não tem nenhum. Por isso não existe uma terceira entrada, e a troca da imagem é manual (ver
[Atualizar a versão da imagem](DESENVOLVIMENTO.md#atualizar-a-versão-da-imagem)).

Regras conferidas pelos testes:

- Toda entrada de `updates` tem `labels`, e toda label listada está no catálogo central.
- Toda entrada de `updates` tem `commit-message` com `include: "scope"`. O `prefix` é `chore` para `composer` (com
  `prefix-development` também `chore`) e `ci` para `github-actions`.
- Não existe entrada para outros ecossistemas.

Resultado: os PRs saem como `chore(deps): bump phpstan/phpstan from 2.2.0 to 2.2.1`, `chore(deps-dev): ...` (para
dependências de desenvolvimento) e `ci(deps): bump actions/checkout from 7.0.1 to 7.1.0`.

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
- Nenhum arquivo `packagist-sync.yml` existe em `.github/workflows/`.
- Nenhum workflow local usa `shivammathur/setup-php`.

## Testes da automação

Os testes ficam em `tests/automation/*.test.cjs`, usam só o `node:test` (sem dependências) e leem os arquivos do
repositório como texto ou JSON. Eles conferem as regras da [Referência dos arquivos](#referência-dos-arquivos):
workflows, Dependabot, devcontainer, os arquivos de estilo, as entradas do `composer.json` e do `.gitignore` e os
arquivos removidos.

Os arquivos `.test.cjs` não são PHP: o PHPUnit e o `composer lint` os ignoram.

Como rodar: veja [Testes da automação](DESENVOLVIMENTO.md#testes-da-automação). No CI, rodam no workflow
**Automação**, em todo PR e em todo push na `main`.

---

[README](../README.md) · Anterior: [Versões e publicação](RELEASE.md)

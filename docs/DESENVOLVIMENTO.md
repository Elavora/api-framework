# Ambiente de desenvolvimento e testes

[README](../README.md) · Anterior: [Bootstrap compartilhado](BOOTSTRAP.md) · Próximo: [Versões e publicação](RELEASE.md)

## Por que usar a imagem da Elavora

Todo pacote PHP da Elavora usa o mesmo ambiente: a imagem `ghcr.io/elavora/php-dev`, mantida no repositório
[`Elavora/devcontainers`](https://github.com/Elavora/devcontainers). Ela traz PHP 8.3, Composer, Git, Xdebug e as
ferramentas de estilo da organização (PHP-CS-Fixer e editorconfig-checker), com o usuário `vscode`, sem privilégios
de root. A mesma imagem roda na sua máquina, no editor e no CI, então as mesmas versões de ferramenta valem nos três
lugares.

Você não precisa instalar PHP nem Composer: só Docker. PHPUnit e PHPStan não vêm na imagem: eles estão no
`require-dev` deste pacote e são instalados pelo `composer install`.

Este repositório usa a tag **`ghcr.io/elavora/php-dev:8.3-latest`**, que aponta sempre para a versão estável mais
recente da imagem PHP 8.3. Ela só muda quando sai uma release estável do `Elavora/devcontainers` (pré-releases não a
movem), então o ambiente acompanha as releases sem nenhum PR de troca de imagem. O CI usa a imagem padrão do
pipeline central (ver [O que roda no CI](#o-que-roda-no-ci)).

## Estilo de código

O código segue o padrão **PER-CS**, aplicado pelo PHP-CS-Fixer, e as regras básicas de arquivo (fim de linha,
indentação, espaço no fim da linha) do `.editorconfig`. As regras não ficam neste repositório: elas vêm do pacote
[`elavora/code-standards`](https://github.com/Elavora/code-standards), instalado como dependência de desenvolvimento.

- `.php-cs-fixer.dist.php` só carrega a configuração do pacote;
- `.editorconfig` é reescrito pelo pacote a cada `composer install` ou `composer update`. Não edite à mão.

Os detalhes estão em [Automação do repositório](AUTOMACAO.md#estilo-de-código-elavoracode-standards).

Você não precisa decorar as regras:

- No devcontainer, o arquivo PHP é formatado ao salvar.
- No PR, o CI formata o que faltou e commita a correção na branch, com a mensagem `style: organiza o código` (veja
  [O que roda no CI](#o-que-roda-no-ci)). Depois disso, dê `git pull` antes do próximo push.

Para formatar ou conferir à mão, no terminal do devcontainer, na raiz do repositório:

```sh
php-cs-fixer fix                                  # formata com a configuração do pacote
php-cs-fixer check                                # só confere, sem alterar
git ls-files -z | xargs -0 editorconfig-checker   # confere os arquivos versionados contra o .editorconfig
```

Para desligar uma regra só na sua máquina, crie um `.php-cs-fixer.php` (ele está no `.gitignore`), conforme o
[guia do pacote](https://github.com/Elavora/code-standards/blob/1.1.0/docs/php-cs-fixer.md).

## Abrir no VS Code (Dev Containers)

1. Instale o Docker Desktop e a extensão **Dev Containers** do VS Code.
2. Abra a pasta do repositório e execute **Dev Containers: Reopen in Container**.
3. O container usa o arquivo [`.devcontainer/devcontainer.json`](../.devcontainer/devcontainer.json) e, ao ser
   criado, roda `composer install`. Esse comando também cria ou atualiza o `.editorconfig`.
4. No terminal do editor:

   ```sh
   composer check   # lint, testes PHPUnit e PHPStan
   composer test    # só os testes PHPUnit
   composer lint    # só a checagem de sintaxe (php -l em todo .php do projeto)
   ```

   O `composer lint` chama o `elavora-standards lint`, do pacote `elavora/code-standards`. Os detalhes estão em
   [Lint de sintaxe](AUTOMACAO.md#lint-de-sintaxe).

O Xdebug fica desligado por padrão (`XDEBUG_MODE=off`). Para depurar, use `XDEBUG_MODE=debug`. Para gerar
cobertura, use `XDEBUG_MODE=coverage`.

## Rodar sem editor

Na pasta do repositório, no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer check'
```

O comando cria `vendor/` e `composer.lock` na pasta (os dois estão no `.gitignore`) e pode atualizar o
`.editorconfig`.

No Git Bash, a conversão automática de caminhos quebra o `docker run`. Use o PowerShell.

### Validar como o CI

O CI roda os testes com cobertura. O `composer check` roda sem cobertura e não pega um erro de configuração da
cobertura. Para reproduzir o CI, rode também, no PowerShell:

```powershell
docker run --rm --mount "type=bind,source=$($PWD.Path),target=/workspace" -w /workspace -e XDEBUG_MODE=coverage ghcr.io/elavora/php-dev:8.3-latest sh -lc 'git config --global --add safe.directory /workspace && composer install --no-interaction --prefer-dist && composer test -- --coverage-clover=/tmp/clover.xml'
```

O comando precisa sair com código 0 e não pode mostrar o aviso `No filter is configured`. A configuração que garante
isso está em [Automação do repositório](AUTOMACAO.md#phpunitxml).

### Testes da automação

Além dos testes PHP, o repositório tem testes que conferem os arquivos de automação (workflows, Dependabot,
devcontainer e arquivos de estilo), descritos em [Automação do repositório](AUTOMACAO.md#testes-da-automação). Eles
usam Node.js e o actionlint, também em container. No PowerShell:

```powershell
docker run --rm -v "$($PWD.Path):/w:ro" -w /w node:22-alpine node --test "tests/automation/*.test.cjs"
docker run --rm -v "$($PWD.Path):/w:ro" -w /w rhysd/actionlint:1.7.12 -no-color
```

## O que roda no CI

Em cada PR e em cada push na `main`, o workflow **Qualidade** (`quality.yml`) chama o pipeline de qualidade central
do `Elavora/.github` 1.2.0, igual para todos os pacotes `api-*`, dentro da imagem padrão do pipeline central. Na
1.2.0 esse padrão é `php-dev:8.3-0.2.0`, gerada do mesmo Dockerfile da `8.3-0.3.0`. Uma próxima versão do
`Elavora/.github` passa o padrão para `8.3-latest`, e o Dependabot sobe o SHA quando ela sair:

1. **Formatação:** roda o PHP-CS-Fixer com a configuração do repositório. Se algo mudou, commita a correção na
   branch do PR, com a mensagem `style: organiza o código`. Em PR de fork, PR do Dependabot e push na `main` não é
   possível ou não é desejado commitar, e o CI só avisa.
2. **Lint:** editorconfig, `composer lint` (sintaxe) e PHPStan (`composer analyse`).
3. **Auditoria:** `composer audit` (dependências vulneráveis ou abandonadas).
4. **Testes:** a suíte PHPUnit, com as dependências nas versões mínimas permitidas pelo `composer.json`.
5. **Cobertura:** mede a cobertura e avisa se ela ficar abaixo de 80%. É só um aviso.
6. **Comentário no PR:** um comentário fixo, atualizado a cada execução, diz "testes rodaram ok" ou "testes com
   erro", com o resultado de cada etapa.

O check aparece no PR como **`quality / Qualidade PHP`**. As etapas 2 a 4 deixam o check vermelho quando falham.

Outros workflows que também rodam em cada PR:

- **CodeQL**: análise de segurança dos workflows do GitHub Actions.
- **Dependency Review**: aponta dependências novas com vulnerabilidade alta.
- **Automação**: os testes da automação e o actionlint, descritos acima.

**Nenhum desses checks trava o merge.** Eles avisam no PR, e quem revisa decide. O motivo está em
[Automação do repositório](AUTOMACAO.md#nenhum-check-trava-o-merge).

Os detalhes de cada arquivo estão em [Automação do repositório](AUTOMACAO.md).

## Atualizações da imagem

Não existe PR de troca de imagem neste repositório:

- **Devcontainer e comandos `docker run`:** usam `8.3-latest`. Quando sai uma release estável da imagem, a tag passa a
  apontar para ela. O Docker não baixa a versão nova sozinho: rode `docker pull ghcr.io/elavora/php-dev:8.3-latest`
  ou, no VS Code, **Dev Containers: Rebuild Container**.
- **CI:** o `quality.yml` não passa a entrada `image` e usa o padrão do pipeline central. Esse padrão muda junto com
  o SHA do `Elavora/.github`, que o Dependabot atualiza (ver
  [`.github/dependabot.yml`](AUTOMACAO.md#githubdependabotyml)).

Se uma release nova da imagem quebrar algo, a correção é feita no `Elavora/devcontainers` (com uma nova release
estável), e não fixando uma versão aqui.

---

[README](../README.md) · Anterior: [Bootstrap compartilhado](BOOTSTRAP.md) · Próximo: [Versões e publicação](RELEASE.md)

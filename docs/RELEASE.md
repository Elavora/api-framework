# Versões e publicação

[README](../README.md) · Anterior: [Ambiente de desenvolvimento e testes](DESENVOLVIMENTO.md) · Próximo: [Automação do repositório](AUTOMACAO.md)

## Por que funciona assim

Este pacote é a base do framework Elavora: quase todos os outros pacotes `elavora/api-*` dependem dele. Por isso,
cada mudança integrada na `main` vira uma versão nova, de forma automática e previsível, e quem usa o pacote só
precisa rodar `composer update` para receber a correção ou a novidade.

A automação não é escrita aqui. Ela vem do repositório central [`Elavora/.github`](https://github.com/Elavora/.github),
igual para todos os repositórios da organização. Este repositório usa a versão `1.2.0` do `.github` (ver
[Automação do repositório](AUTOMACAO.md#qual-versão-do-github)). As regras de versão são as mesmas desde a `1.0.0`;
a `1.2.0` acrescenta as notas da release em português. Este documento explica o que muda para quem contribui e para quem consome o `elavora/api-framework`. As regras
completas estão nos documentos centrais:

- [Regras de versão](https://github.com/Elavora/.github/blob/1.2.0/docs/versionamento.md)
- [Release automática e Packagist](https://github.com/Elavora/.github/blob/1.2.0/docs/release.md)
- [Labels padrão](https://github.com/Elavora/.github/blob/1.2.0/docs/labels.md)
- [Notas de release](https://github.com/Elavora/.github/blob/1.2.0/docs/notas-de-release.md)

## Em poucas palavras

1. Abra um PR para a `main` e escolha as labels conforme o tipo de mudança. Se quiser, escreva na seção
   **Notas de release** da descrição o texto que deve aparecer na release.
2. Quando o PR é integrado, o workflow **Release** cria a tag, a GitHub Release (com notas em português) e avisa o
   Packagist.
3. Sem a label `release`, a versão sai como **beta** (`2.1.0-beta.1`). Com `release`, sai **estável** (`2.1.0`).

As tags não têm mais o prefixo `v`: as novas são `2.0.0`, `2.0.1-beta.1` e assim por diante.

## Qual label usar

| Label no PR | O que acontece com a versão | Exemplo a partir de `2.0.0` |
| --- | --- | --- |
| `upgrade` | major: quebra de compatibilidade | `3.0.0-beta.1` |
| `enhancement`, `feature` ou `improvement` | minor: novidade compatível | `2.1.0-beta.1` |
| `bug`, `documentation`, `tests`, `refactor`, `dependencies`, outra ou nenhuma | patch | `2.0.1-beta.1` |
| `release` (junto com as outras) | publica a estável em vez da beta | `bug` + `release` → `2.0.1` |

- Com várias labels, vale a maior: `bug` + `enhancement` dá minor.
- Todo merge gera versão. Não existe label que pule a release.
- `dependencies` agora é **patch** (no fluxo antigo era minor).
- `major`, `minor`, `patch` e `breaking` deixaram de existir: são apagadas pela sincronização de labels e, se
  aparecerem num PR, valem patch.

### Betas e estável

Uma beta é uma prévia da próxima estável. As betas seguintes continuam na mesma linha, e a base nunca anda para
trás:

| Tags que já existem | Labels do PR | Nova tag |
| --- | --- | --- |
| `2.0.0` | `bug` | `2.0.1-beta.1` |
| `2.0.0`, `2.0.1-beta.1` | `enhancement` | `2.1.0-beta.1` |
| `2.0.0`, `2.1.0-beta.1` | `bug` | `2.1.0-beta.2` |
| `2.0.0`, `2.1.0-beta.2` | `release` | `2.1.0` (promove a linha de betas) |

Para quem consome o pacote:

- Um projeto com `"elavora/api-framework": "^2.0"` e `minimum-stability: stable` (o padrão do Composer) só recebe
  as estáveis. As betas não aparecem para ele.
- Para testar uma beta, o projeto precisa pedir explicitamente, por exemplo `"^2.1@beta"`.

## Transição do fluxo antigo

O repositório já tem tags do fluxo antigo: `v1.0.0` e anteriores (estáveis), e as prévias `v1.1.0-rc.1` e
`v2.0.0-rc.1`. O fluxo novo trata essas tags assim:

- As estáveis `vX.Y.Z` contam como histórico. A versão nova parte de `v1.0.0`, e não de `0.0.1`.
- As prévias `vX.Y.Z-rc.N` são **ignoradas**. Elas continuam no GitHub e no Packagist, mas não entram no cálculo.
- Nenhuma tag antiga é apagada ou movida.

### A primeira versão do fluxo novo

A primeira release considera todos os PRs integrados desde a `v1.0.0`, e não só o PR que disparou a release.
Hoje, depois da `v1.0.0`, já entraram na `main`:

| PR | Labels | Efeito |
| --- | --- | --- |
| #45 `fix: estabiliza qualidade e guia do framework` | `bug`, `documentation`, `tests`, `upgrade` | major |
| #48 `feat: centraliza bootstrap e configuração de extensões` | `enhancement` | minor |

O #44 também tem `upgrade`, mas não conta: a `v1.0.0` aponta para o merge dele. A label `IA` desses PRs é apagada
pela sincronização e não muda nada.

Como o #45 tem `upgrade`, a primeira versão nova é **2.0.0**, quaisquer que sejam as labels do PR de adoção:

- PR de adoção **com** `release`: sai a estável `2.0.0`.
- PR de adoção **sem** `release`: sai a beta `2.0.0-beta.1`.

**Decisão desta adoção (2026-10-17):** o PR que adota o fluxo novo leva as labels `release` e `tests`, e a primeira
versão é a estável `2.0.0`. Não é preciso `upgrade` no PR de adoção, porque o major já vem do #45. O motivo de não
começar por uma beta está logo abaixo.

Antes de aplicar as labels, confira de novo se nenhum PR integrado depois da `v1.0.0` tem `upgrade` ou `release`
além dos listados acima: todos entram na conta.

### Por que não começar com `2.0.0-beta.1`

Para o Composer, uma beta vem **antes** de uma RC da mesma versão:

```
2.0.0-beta.1  <  2.0.0-RC1 (a tag antiga v2.0.0-rc.1)  <  2.0.0
```

Se a primeira versão nova fosse `2.0.0-beta.1`:

- Quem já instalou `v2.0.0-rc.1` não receberia as betas novas, porque elas são "mais antigas" que a RC. Essa
  pessoa ficaria parada na RC até a estável `2.0.0` sair.
- O Packagist continuaria mostrando `v2.0.0-rc.1` como a prévia mais recente, o que confunde quem chega ao pacote.

Publicando direto a estável `2.0.0`, que é maior que a RC, esses dois problemas não existem.

Impacto para os pacotes da organização (levantado em 2026-10-17):

- Nenhum pacote pede `^2.0` nem a RC. Os pacotes `api-*` pedem `^0.3.1` (alguns `^0.3.1 || ^0.4.0`), o
  `api-datatype-core` pede `^1.0` e o `api-skeleton` usa `dev-main as 1.0.x-dev`.
- Quem pede `^1.0` continua na `v1.0.0`. A `2.0.0` só chega a quem trocar a restrição para `^2.0`, num PR próprio,
  porque ela traz quebras de compatibilidade (por exemplo, o `#[Action]` obrigatório nas rotas por convenção,
  descrito no [README](../README.md#rotas-por-convencao)).

## Notas da release

A partir da `1.2.0` do `.github`, o workflow escreve as notas da GitHub Release em português, no mesmo formato em
todos os repositórios da organização:

- **Título:** `Elavora/api-framework <tag>`, por exemplo `Elavora/api-framework 2.0.0`.
- **Resumo:** se a versão é estável ou beta e o tamanho do salto em relação à estável anterior.
- **O que há de novo:** desde qual versão as mudanças contam e os PRs agrupados pelas labels (novidades, correções,
  outras mudanças), cada um com o link e o autor. Numa estável, entram também os PRs que já tinham saído nas betas,
  com a beta em que cada um entrou.
- **Contribuidores** e o link **Comparação completa** entre a versão anterior e a nova.

O texto de cada item é o título do PR. Para usar outro texto, preencha a seção **Notas de release** do template de
PR (ver [Modelo de descrição do PR](AUTOMACAO.md#modelo-de-descrição-do-pr)). O texto escrito ali substitui o título
na release e pode ter mais de uma linha. Deixe a seção vazia para usar o título.

Na primeira versão do fluxo novo, a release `Elavora/api-framework 2.0.0` começa assim:

```markdown
Versão estável **2.0.0**, com salto major (quebra de compatibilidade).

## O que há de novo

Mudanças desde a versão estável `v1.0.0`.
```

e lista o #45, o #48 e o PR de adoção.

As releases publicadas antes da adoção (`v1.0.0` e anteriores) não são reescritas.

## Packagist

O pacote está publicado em [packagist.org/packages/elavora/api-framework](https://packagist.org/packages/elavora/api-framework).
O Packagist é avisado de cada tag nova de dois jeitos, e qualquer um deles basta:

1. **Job "Atualizar Packagist" do workflow Release.** Roda logo depois de a tag e a release serem criadas e usa o
   secret `PACKAGIST_WEBHOOK_URL`. Tenta até 4 vezes em caso de falha de rede, 429 ou 5xx.
2. **Webhook do GitHub para o Packagist** (`https://packagist.org/api/github`, evento `push`), já configurado no
   repositório. Ele dispara em qualquer push, inclusive nas tags criadas pelo workflow.

### O secret `PACKAGIST_WEBHOOK_URL`

- Fica na organização (Settings > Secrets and variables > Actions), com acesso a este repositório. Ele já foi
  configurado na organização em 2026-10-17. Um secret de
  repositório com o mesmo nome teria prioridade sobre o da organização. Não use uma *variable* para ele.
- O valor precisa ter exatamente esta forma:

  ```
  https://packagist.org/api/update-package?username=USUARIO&apiToken=TOKEN
  ```

- O workflow chamador repassa só esse secret (`secrets: PACKAGIST_WEBHOOK_URL: ...`), e não todos os secrets do
  repositório.

### Se o secret não estiver configurado (ou estiver errado)

- A tag e a GitHub Release são criadas normalmente. O job **Criar tag e release** termina com sucesso.
- O job **Atualizar Packagist** falha, com uma mensagem que cita `PACKAGIST_WEBHOOK_URL`, sem nenhuma requisição ao
  Packagist. A execução do workflow aparece como falha (vermelha).
- Mesmo assim, o Packagist costuma receber a versão pelo webhook do GitHub, se ele estiver ativo.
- Para corrigir: configure o secret e use **Re-run failed jobs** na execução que falhou. A tag não é recriada nem
  alterada.

### Se a versão não aparecer no Packagist

1. Confira se a tag existe no GitHub e se a release aponta para ela.
2. Veja o resumo e o log do job **Atualizar Packagist** na execução do workflow Release.
3. Confira o webhook do Packagist em Settings > Webhooks do repositório.
4. Só depois disso, use o botão de update manual no Packagist.

## Recuperação

- **A tag foi criada e a release falhou:** reexecute o workflow. A tag que já aponta para o merge é reaproveitada e
  a release que faltava é criada.
- **O Packagist falhou:** veja a seção anterior.
- **Tag errada:** o workflow nunca move nem apaga tags. A correção é manual e precisa de análise, porque alguém pode
  já ter instalado a versão. Em geral, a saída é abrir um PR novo e gerar outra versão.

## Hotfix

1. Abra um PR pequeno e focado, com a label `bug`.
2. Se a correção precisa chegar a quem usa só estáveis, acrescente `release`.
3. Aguarde os checks, faça o merge e confira a versão nova no GitHub e no Packagist.

---

[README](../README.md) · Anterior: [Ambiente de desenvolvimento e testes](DESENVOLVIMENTO.md) · Próximo: [Automação do repositório](AUTOMACAO.md)

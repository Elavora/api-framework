# Configuração do Webhook Packagist

Este documento descreve como configurar o webhook do Packagist para permitir a publicação automática do pacote no Composer quando novas tags são criadas.

## Passo 1: Registrar o Repositório no Packagist

1. Acesse https://packagist.org/
2. Faça login com sua conta (ou crie uma nova conta se necessário)
3. Clique em "Submit" no menu superior
4. Insira a URL do repositório: `https://github.com/Elavora/api-framework`
5. Clique em "Check" e depois em "Submit" para registrar o pacote

## Passo 2: Obter a URL do Webhook

1. Após registrar o pacote, acesse a página do seu pacote no Packagist
2. Clique em "Settings" ou "Edit" no painel do pacote
3. Localize a seção "Webhook" ou "API Token"
4. Copie a URL do webhook gerada (geralmente no formato: `https://packagist.org/api/update-package?username=XXX&apiToken=YYY`)

## Passo 3: Configurar o Webhook no GitHub

1. Acesse o repositório no GitHub: https://github.com/Elavora/api-framework
2. Vá para **Settings** > **Secrets and variables** > **Actions**
3. Na aba "Variables", clique em "New repository variable"
4. Configure a variável:
   - **Name**: `PACKAGIST_WEBHOOK_URL`
   - **Value**: Cole a URL do webhook copiada do Packagist
5. Clique em "Add variable" para salvar

## Resultado

Após configurar o webhook, sempre que uma nova tag for criada no repositório, o GitHub Actions irá notificar automaticamente o Packagist, que atualizará o pacote com a nova versão disponível para instalação via Composer.

## Verificação

Para verificar se a configuração está funcionando:

1. Crie uma nova tag no repositório
2. Aguarde o workflow do GitHub Actions ser executado
3. Verifique no Packagist se a nova versão aparece na página do pacote

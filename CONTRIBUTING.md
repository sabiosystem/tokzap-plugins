# Contributing to TokZap Plugins

## Tag Convention

Cada plugin tem seu próprio ciclo de release. Use o padrão:

```
{pasta}/v{major}.{minor}.{patch}
```

Exemplos:
```
wordpress/v1.0.0
wordpress/v1.0.1
n8n/v1.0.0
shopify/v2.1.0
```

O GitHub Actions só dispara o workflow correto quando a tag bate com o prefixo da pasta.

## Commit Convention

Use [Conventional Commits](https://www.conventionalcommits.org/):

```
feat(wordpress): add WooCommerce checkout verification
fix(n8n): correct phone number format for Brazilian numbers
docs(shopify): add installation screenshots
chore(wordpress): update API base URL to api.tokzap.com/v1
```

## Como rodar cada plugin localmente

### WordPress (PHP)

```bash
cd wordpress/core
# Instalar dependências (se houver)
composer install

# Para testes, use o WP-CLI ou uma instalação local do WordPress
# Documentação: https://developer.wordpress.org/plugins/
```

### n8n (Node.js / TypeScript)

```bash
cd nocode/n8n
npm install
npm run build
npm run test

# Para testar no n8n local:
npm link
cd ~/.n8n
npm link n8n-nodes-tokzap
```

### Shopify / Nuvemshop (Node.js)

```bash
cd ecommerce/shopify
npm install
npm run dev
```

## Abrindo um PR

1. Fork o repositório
2. Crie um branch: `git checkout -b feat/wordpress-woocommerce`
3. Faça commits seguindo o padrão acima
4. Abra o PR para `main` com descrição do que foi feito
5. Aguarde revisão da equipe TokZap

## Fazendo um release

```bash
# Certificar-se de que está na main atualizada
git checkout main && git pull

# Criar e publicar a tag
git tag wordpress/v1.0.1
git push origin wordpress/v1.0.1

# O GitHub Actions cria a Release e o ZIP automaticamente
```

## Dúvidas

Abra uma [Issue](https://github.com/sabiosystem/tokzap-plugins/issues) ou envie email para contato@tokzap.com.

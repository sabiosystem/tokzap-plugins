# Publicando na Shopify App Store

## Pré-requisitos

1. Conta de parceiro Shopify: https://partners.shopify.com/
2. App criado no painel de parceiros
3. Revisão de segurança aprovada pela Shopify

## Stack recomendada

- Shopify CLI: `npm install -g @shopify/cli`
- Remix + Shopify App Template: `shopify app init`
- Autenticação: Shopify OAuth (não API Key direta)

## Fluxo de publicação

1. `shopify app deploy` — envia o app para revisão
2. Aguardar revisão (5-10 dias úteis)
3. Configurar preço via Shopify Billing API (dentro do app)

## Billing

Usar `appSubscriptionCreate` do Shopify Admin GraphQL para cobrar USD 15/mês.
Nunca cobrar fora da plataforma — viola os termos.

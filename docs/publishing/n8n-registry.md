# Publicando no n8n Community Registry

## Estrutura do node

O pacote deve ter o nome `n8n-nodes-tokzap` e seguir a estrutura:

```
n8n-nodes-tokzap/
├── nodes/
│   └── TokZap/
│       ├── TokZap.node.ts
│       └── tokzap.svg
├── credentials/
│   └── TokZapApi.credentials.ts
├── package.json  ← keywords: ["n8n-community-node-package"]
└── tsconfig.json
```

## Publicação no npm

```bash
npm login
npm publish --access public
```

## Submissão para o registry

Abrir PR em: https://github.com/n8n-io/n8n/blob/master/packages/nodes-base/package.json
Adicionar o pacote na lista de community nodes.

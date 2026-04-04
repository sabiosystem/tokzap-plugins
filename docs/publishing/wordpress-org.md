# Publicando no WordPress.org

## Pré-requisitos

1. Conta em wordpress.org — criar em https://wordpress.org/support/register.php
2. Plugin validado com Plugin Check (https://wordpress.org/plugins/plugin-check/)
3. Todas as strings traduzíveis com `__()` ou `_e()`
4. `readme.txt` no formato padrão do wordpress.org

## Submissão inicial

1. Acesse https://wordpress.org/plugins/add/
2. Faça upload do ZIP
3. Aguarde aprovação (7-14 dias úteis)
4. Após aprovação, você recebe acesso SVN: `https://plugins.svn.wordpress.org/tokzap-whatsapp-otp/`

## Publicação via SVN (após aprovação)

O GitHub Actions já tem o passo de deploy comentado em `release-wordpress.yml`.
Adicione os secrets no repo:
- `SVN_USERNAME` — seu usuário wordpress.org
- `SVN_PASSWORD` — sua senha wordpress.org

Descomente o step e no próximo release ele faz deploy automático.

## Checklist antes de submeter

- [ ] `readme.txt` com seções: Description, Installation, FAQ, Changelog
- [ ] Versão em `tokzap.php` e `readme.txt` sincronizadas
- [ ] Nenhum `error_reporting`, `var_dump` ou `print_r` no código
- [ ] Todas as saídas HTML passam por `esc_html()` / `esc_attr()` / `wp_kses()`
- [ ] Nonces em todos os formulários (`wp_nonce_field` + `check_admin_referer`)
- [ ] Prefix em todas as funções: `tokzap_*`

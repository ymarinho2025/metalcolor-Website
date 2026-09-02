# Auditoria V10

## Correção Asaas User-Agent

Os logs da Vercel mostraram resposta HTTP 400 do Asaas com a mensagem `É obrigatório preencher User-Agent no cabeçalho da requisição`.

A função central `mc_asaas()` agora envia em todas as chamadas autenticadas:

- `Accept: application/json`
- `Content-Type: application/json`
- `User-Agent: MetalColor/1.0 (PHP; sandbox|production)`
- `access_token: <ASAAS_API_KEY>`

O valor pode ser sobrescrito opcionalmente por `ASAAS_USER_AGENT`, mas não é necessário configurá-lo na Vercel.

A correção vale para todas as rotas Asaas que passam por `mc_asaas()`, incluindo criação de checkout.

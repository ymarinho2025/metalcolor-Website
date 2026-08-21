# Metal Color / Usee Kyle — Webapp

Versão visualmente revisada do catálogo, preservando a proposta original do projeto e o fluxo Pix + WhatsApp.

## Principais páginas
- `/` — página inicial
- `/produtos/` — categorias
- `/produtos/tecidos/`
- `/produtos/material-uniforme-dbv/`
- `/produtos/material-uniforme-avt/`
- `/produtos/todos-os-itens/` — catálogo completo com busca
- `/produtos/produto1/?id=...` — detalhe dinâmico
- `/pagamento/` — checkout Pix
- `/sobre-mim/`
- `/contato/`

## Configuração do Pix
Edite `assets/js/payment-config.js` e configure `pixKey`, `pixKeyType` e `pixReceiverName`.

A confirmação do pagamento é manual: o cliente paga no próprio banco e envia o comprovante pelo WhatsApp.

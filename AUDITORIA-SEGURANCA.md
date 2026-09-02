# Auditoria de segurança — Metal Color

Revisão aplicada ao fluxo de autenticação, carrinho, checkout, split, webhook, pedidos, painel administrativo e rastreamento.

## Proteções presentes

- O frontend nunca define o preço cobrado: o backend busca produtos no catálogo oficial e recalcula subtotal.
- O frete escolhido é recotado no backend antes do Checkout.
- A comissão é recalculada no backend e usa valor fixo; frete não entra na base.
- Pagamento só muda para pago pelo Webhook autenticado do Asaas.
- Webhook é idempotente e confere checkout + referência do pedido.
- Compras sem login recebem token secreto de acesso ao pedido; compras logadas são vinculadas ao usuário.
- Sessões usam cookie HttpOnly, Secure e SameSite=Lax.
- Senhas usam scrypt com salt aleatório.
- Endpoints sensíveis possuem rate limiting no banco.
- Dados dinâmicos exibidos em páginas críticas são escapados para reduzir XSS.
- Cabeçalhos CSP, HSTS, nosniff e frame deny configurados.

## Limites e riscos residuais

Nenhum sistema pode ser declarado invulnerável. Segurança também depende de Vercel, Neon, Asaas, Melhor Envio, Correios, credenciais e configuração operacional. Não há verificação de e-mail, recuperação de senha nem 2FA próprio para clientes nesta versão; se isso for necessário, use um provedor de autenticação/e-mail apropriado em vez de improvisar envio de senha.

Antes de produção, faça testes em Sandbox e revisão periódica de dependências e credenciais.

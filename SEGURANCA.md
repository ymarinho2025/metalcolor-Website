# Segurança — Metal Color

## Regra central

Nunca confiar em valores recebidos do frontend. Preço, comissão, taxa e frete usados para cobrança são recalculados no backend.

## Implementado

- Produtos validados contra catálogo mantido no servidor.
- Quantidade e opção validadas.
- Cotação de frete refeita no backend antes do Checkout.
- Split calculado em valor fixo sobre o subtotal de produtos.
- Confirmação financeira exclusivamente por Webhook Asaas.
- Idempotência dos eventos Asaas.
- Sessões em cookies HttpOnly, Secure e SameSite=Lax.
- Senhas com scrypt e salt individual.
- Dashboard restrito a ADMIN.
- Pedido associado ao usuário não pode ser consultado por outra conta.
- Operações administrativas protegidas por autenticação e verificação de origem.
- Chaves apenas em variáveis de ambiente.
- Cabeçalhos HTTP básicos de segurança via `vercel.json`.

## Produção

Nenhuma aplicação é “impossível de alterar”. O objetivo é garantir que adulterações no navegador não alterem a cobrança real. Antes de produção, use Sandbox, credenciais exclusivas, senha forte, logs, backups do banco e testes de fluxo completo.

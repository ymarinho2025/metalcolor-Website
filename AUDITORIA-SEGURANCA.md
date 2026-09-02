# Metal Color v8 — Auditoria técnica

Revisão do projeto PHP completo realizada em 02/09/2026.

## Correções aplicadas

- Corrigida a ordem de carregamento de `runtime-config.js`, `payment-config.js`, carrinho e catálogo. Todos os scripts dependentes usam `defer`, preservando a ordem e evitando catálogo vazio.
- Confirmados 19 produtos em `data/products.json` e respectivas imagens.
- Front controller passou a usar lista explícita de páginas públicas. `config/`, `data/`, `.env` e demais arquivos internos não podem ser executados por rota pública.
- `.env` real e `.git` foram removidos do pacote distribuível. Apenas `.env.example` permanece.
- Autenticação deixou de depender de pacote JWT externo: assinatura HS256 é feita em PHP e as sessões são registradas no PostgreSQL, permitindo revogação no logout.
- Login administrativo exige senha configurada com tamanho mínimo antes do bootstrap.
- Cadastro exige e-mail não vazio e senha mínima de 10 caracteres, alinhando frontend e backend.
- Validação de CPF/CNPJ passou a validar dígitos verificadores.
- Carrinho, subtotal, comissão e frete continuam recalculados no servidor. Valores enviados pelo navegador não são fonte de verdade.
- Preview de preço agora também revalida o frete no servidor pelo CEP + ID do serviço, em vez de confiar no valor enviado pelo navegador.
- Os dois botões de pagamento são bloqueados durante a criação do checkout para reduzir duplicidade acidental.
- Se a criação do Checkout Asaas falhar, o pedido é marcado como erro de pagamento em vez de permanecer como checkout pendente válido.
- O link retornado pelo próprio Asaas é priorizado; há fallback compatível com Checkout Session.
- Webhook continua idempotente e agora impede que evento tardio `CHECKOUT_CREATED`, `CHECKOUT_CANCELED` ou `CHECKOUT_EXPIRED` rebaixe um pedido já `PAID`.
- Token de acesso de pedido convidado só é criado para compra sem login. Compras autenticadas usam a sessão do usuário.
- Token do webhook é recusado se ainda estiver com placeholder/fraco.
- Renderização de catálogo/produto foi endurecida contra injeção de HTML/XSS em dados do catálogo.
- WhatsApp da página de contato passou a vir do `.env`, removendo número fixo divergente no código.
- Todos os links internos e referências de imagens foram verificados.

## Testes estáticos executados

- `php -l` em todos os arquivos PHP: sem erros.
- `node --check` em todos os JavaScripts: sem erros.
- `products.json`, `vercel.json` e `composer.json`: JSON válido.
- 19 produtos encontrados no catálogo.
- Todas as imagens referenciadas existem.
- Nenhuma página `.html` permanece.
- Nenhum script dependente de runtime ficou sem `defer`.
- Nenhum `.env` real foi incluído no pacote final.

## Observações de produção

Nenhum sistema conectado à Internet pode ser declarado como invulnerável. Segurança de produção também depende das credenciais, configuração da Vercel/Neon/Asaas, atualizações, logs, backups e testes reais de Sandbox.

Antes de produção, configure valores aleatórios reais para `JWT_SECRET`, `RATE_LIMIT_SECRET` e `ASAAS_WEBHOOK_TOKEN`; nunca use os placeholders do `.env.example`.

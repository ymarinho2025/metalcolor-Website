# Metal Color — configuração do e-commerce

Esta versão substitui o Pix manual por checkout real do Asaas, adiciona carrinho, consulta de CEP, cotação de frete, pedido persistido, webhook e arquitetura de split de 15%.

## 1. Banco de dados

Use um Postgres/Neon e configure `DATABASE_URL` na Vercel. As tabelas são criadas automaticamente no primeiro uso; o arquivo `database.sql` também pode ser executado manualmente.

## 2. Asaas — primeiro teste com a sua conta

1. Crie/acesse sua conta Sandbox do Asaas.
2. Gere uma API Key de Sandbox.
3. Na Vercel, configure `ASAAS_ENVIRONMENT=sandbox` e `ASAAS_API_KEY`.
4. Deixe `ASAAS_SPLIT_WALLET_YURI` vazio enquanto a cobrança estiver sendo emitida pela sua própria conta. O Asaas não deve receber no split o walletId da própria conta emissora.
5. Configure `PUBLIC_SITE_URL` com a URL pública do deploy.
6. Crie um Webhook no Asaas apontando para `https://SEU-DOMINIO/api/webhooks/asaas`.
7. Configure um token de autenticação seguro (32+ caracteres) no Webhook e salve o mesmo valor em `ASAAS_WEBHOOK_TOKEN`.
8. Assine pelo menos os eventos `CHECKOUT_CREATED`, `CHECKOUT_PAID`, `CHECKOUT_CANCELED` e `CHECKOUT_EXPIRED`.

O retorno do navegador para `/pedido/` nunca marca o pedido como pago. Somente `CHECKOUT_PAID` recebido pelo Webhook muda o status para `PAID`.

## 3. Split definitivo Metal Color + Yuri

O desenho correto para produção é:

- a **conta Asaas da Metal Color** é a emissora da cobrança e fornece `ASAAS_API_KEY`;
- o **walletId da conta do Yuri** vai em `ASAAS_SPLIT_WALLET_YURI`;
- o backend calcula 15% apenas sobre os produtos e envia esse valor como `fixedValue`;
- frete e eventual taxa de pagamento não entram na comissão;
- o saldo líquido que não for enviado no split permanece automaticamente na conta emissora da Metal Color.

Assim que a conta do Erinaldo existir, basta trocar a API Key emissora e preencher o walletId do Yuri. Não é necessário reescrever o checkout.

## 4. Pix e cartão

O site cria um Asaas Checkout hospedado. Dados de cartão não passam pelo servidor da Metal Color.

Por padrão, taxas de pagamento **não são repassadas ao cliente**:

- `PASS_PIX_FEE_TO_CUSTOMER=false`
- `PASS_CARD_FEE_TO_CUSTOMER=false`

Se a estratégia comercial decidir repassar, configure os valores reais vigentes na conta Asaas e habilite as flags. Não dependa dos valores de exemplo para produção, porque planos e promoções podem mudar.

No cartão, quando o repasse está habilitado, o backend usa gross-up para que o percentual seja calculado sobre o valor final cobrado.

## 5. CEP

A página usa ViaCEP para preencher logradouro, bairro, cidade, UF e código IBGE. O código IBGE pode ser enviado ao `customerData.city` do Asaas Checkout.

## 6. Frete — Melhor Envio

O projeto possui dois modos:

- `SHIPPING_MODE=demo`: duas opções fictícias apenas para testar todo o fluxo visual;
- `SHIPPING_MODE=melhorenvio`: chama `POST /api/v2/me/shipment/calculate` do Melhor Envio.

Para usar o Melhor Envio:

1. crie uma conta/aplicativo no Sandbox do Melhor Envio;
2. obtenha um token OAuth válido;
3. configure `MELHOR_ENVIO_ENVIRONMENT=sandbox`;
4. configure `MELHOR_ENVIO_TOKEN`;
5. informe `SHIPPING_ORIGIN_CEP`;
6. informe `MELHOR_ENVIO_USER_AGENT` com nome do app e e-mail técnico;
7. opcionalmente limite serviços em `MELHOR_ENVIO_SERVICES`.

### Muito importante: peso e dimensões

O Melhor Envio precisa de peso, largura, altura e comprimento. Como o catálogo original não informa esses dados, o projeto usa valores padrão configuráveis apenas para permitir o desenvolvimento. **Antes de vender em produção, substitua pelos pesos e dimensões reais dos produtos/pacotes.** Não use os defaults para cobrar frete real sem validar.

## 7. Variáveis da Vercel

Copie `.env.example` e configure as variáveis no projeto da Vercel. Nunca coloque API keys/tokens em `assets/js`, HTML ou GitHub.

## 8. Teste completo

1. `SHIPPING_MODE=demo` para o primeiro teste.
2. Adicione um produto ao carrinho.
3. Abra `/pagamento/`.
4. Informe CEP e endereço.
5. Calcule e selecione frete.
6. Escolha Pix ou cartão.
7. O backend cria o pedido, recalcula preços e cria o Asaas Checkout.
8. Conclua o pagamento no Sandbox.
9. O Asaas chama `/api/webhooks/asaas`.
10. `/pedido/?order=...` atualiza para PAGO.
11. O botão de WhatsApp abre mensagem com número do pedido, total, frete e CEP.

## 9. Produção

Antes de mudar para produção:

- use a conta Asaas da Metal Color como emissora;
- configure o walletId do Yuri para o split;
- troque para `ASAAS_ENVIRONMENT=production` e uma API Key de produção;
- recrie/configure o Webhook de produção;
- configure Melhor Envio de produção e token válido;
- cadastre peso/dimensões reais;
- confira política de frete, devolução, privacidade/LGPD e termos comerciais;
- faça uma compra real de baixo valor e confira conciliação, comissão e frete antes de divulgar.

---

## 9. Login de cliente e painel do vendedor

A versão 3 adiciona autenticação própria com senha armazenada via `scrypt` e sessão em cookie `HttpOnly`, `Secure` e `SameSite=Lax`.

Configure na Vercel:

```env
ADMIN_NAME=Erinaldo - Metal Color
ADMIN_EMAIL=email-do-vendedor@exemplo.com
ADMIN_PASSWORD=use-uma-senha-forte-aqui
```

Na primeira entrada em `/login/` com essas credenciais o usuário administrador é criado automaticamente. Depois, `/admin/` mostra pedidos e clientes.

Clientes criam conta em `/cadastro/` e acessam `/conta/`. O carrinho é salvo localmente e, quando existe sessão, também sincronizado com o banco. O histórico de pedidos permite acompanhar e comprar novamente.

## 10. Expiração do Checkout

O padrão deste projeto é **30 minutos**:

```env
ASAAS_CHECKOUT_EXPIRE_MINUTES=30
```

O Asaas aceita `minutesToExpire` de 10 a 1440 minutos. O pedido recebe o evento `CHECKOUT_EXPIRED` por Webhook e passa para `EXPIRED/PAYMENT_EXPIRED`. O carrinho não é apagado enquanto o pagamento não for confirmado; assim o cliente pode gerar um novo checkout.

## 11. Fluxo de envio

Após `CHECKOUT_PAID`, o pedido passa automaticamente para `AWAITING_SHIPMENT`.

No `/admin/`, o vendedor pode alterar para:

- `PREPARING`
- `SHIPPED`
- `DELIVERED`
- `CANCELED`

Ao marcar `SHIPPED`, informe o código de rastreio. O cliente passa a enxergar o código e o botão **Rastrear pedido**.

## 12. Rastreamento dos Correios

Sem credenciais dos Correios, o site mostra o código cadastrado e abre a página oficial de rastreamento.

Para exibir os eventos diretamente dentro do site, configure:

```env
CORREIOS_IDCORREIOS=
CORREIOS_API_CODE=
```

O backend gera o token pela API Token dos Correios e consulta a API Rastro. Os Correios podem restringir a API Rastro a objetos vinculados ao contrato/remetente. Portanto, a integração direta depende das permissões da conta/contrato da Metal Color.

## 13. Segurança aplicada ao carrinho e pagamentos

O navegador **não decide o preço cobrado**. Antes de criar o Checkout, o backend:

1. recebe apenas IDs, opções e quantidades;
2. procura cada produto em `data/products.json`;
3. usa o preço oficial do servidor;
4. limita quantidades;
5. recalcula o subtotal;
6. recota o frete no backend e valida o serviço escolhido;
7. calcula taxas conforme as variáveis do servidor;
8. calcula 15% somente sobre os produtos;
9. cria a cobrança no Asaas com o total recalculado.

Alterar `localStorage`, JavaScript, requisições no DevTools ou ferramentas como Burp Suite pode mudar apenas o que o navegador exibe/envia; não muda o preço que o backend usa para criar a cobrança.

A confirmação de pagamento é feita apenas pelos eventos assinados/configurados do Webhook do Asaas, nunca pelo redirecionamento do navegador.

Outras proteções presentes: sessão `HttpOnly/Secure/SameSite`, validação de origem em operações críticas, Webhook idempotente, token de Webhook, cabeçalhos HTTP de segurança e controle de acesso por função.

## 14. Sobre o indicador do Asaas

A página do pedido mostra **“Pagamento processado com segurança pelo Asaas”** e muda para **“Pagamento confirmado pelo Asaas”** somente depois do `CHECKOUT_PAID` recebido pelo Webhook. O projeto não cria um selo gráfico que possa ser confundido com certificação oficial da marca Asaas.

## 15. Checklist de segurança antes de produção

1. Gere segredos diferentes e fortes para `ASAAS_WEBHOOK_TOKEN`, `RATE_LIMIT_SECRET` e `ADMIN_PASSWORD`.
2. Não reutilize a API Key do Asaas como token de Webhook.
3. Mantenha `ASAAS_API_KEY` somente nas variáveis da Vercel.
4. Ative autenticação em dois fatores na conta Asaas e no provedor de hospedagem.
5. Se sua infraestrutura permitir IP fixo de saída, considere o IP Whitelisting do Asaas. Em Vercel serverless comum, IP de saída pode variar, então não habilite sem infraestrutura compatível.
6. No Webhook, use token de autenticação e monitore os logs/fila do Asaas.
7. Faça o primeiro deploy em Sandbox e execute Pix e cartão de ponta a ponta.
8. Teste adulteração do carrinho: altere preço, quantidade, frete e comissão no navegador/Burp; o Checkout deve continuar usando os valores recalculados no backend.
9. Teste acesso indevido: um usuário não deve conseguir consultar pedido de outro. Pedidos sem login usam token secreto de acesso gerado no checkout.
10. Cadastre peso e dimensões reais antes de ligar `SHIPPING_MODE=melhorenvio`.
11. Faça backup do Neon/Postgres e restrinja acesso administrativo.
12. Revise periodicamente dependências e rotacione credenciais.

### Mudanças de segurança desta versão

- token secreto por pedido para compras sem login;
- rate limiting persistido no Postgres para login, cadastro, checkout e consultas críticas;
- comparação em tempo constante do token do Webhook;
- validação mais estrita de CPF/CNPJ, e-mail e rastreio;
- confirmação do Webhook vinculada simultaneamente ao `checkout_id` e ao `externalReference`;
- escape de dados exibidos no painel, conta e acompanhamento para reduzir XSS armazenado;
- CSP e cabeçalhos de segurança reforçados;
- URL do Checkout construída localmente a partir do ID retornado pelo Asaas, evitando redirecionamento arbitrário.

---

## Backend PHP no padrão SGC (v5)

Esta versão não usa mais as Serverless Functions Node do diretório `api/`. O backend foi migrado para PHP/PDO/JWT seguindo a organização e a lógica do SGC(3).

Na Vercel, certifique-se de adicionar também:

```env
JWT_SECRET=uma-chave-aleatoria-com-32-ou-mais-caracteres
COOKIE_SECURE=true
COOKIE_DOMAIN=
```

O `vercel.json` encaminha apenas `/api/*` ao front controller `api/index.php`; o restante do site continua sendo entregue como arquivos estáticos.

## v8 — observação de segurança
A v8 cria também `metalcolor_sessions` para sessões revogáveis. O schema é criado automaticamente pelo backend e também está documentado em `database.sql`.

# Auditoria integral — Metal Color v9

Base analisada: `metalcolor-v8-php-auditado.zip` enviada pelo usuário.

## Escopo

Foram revisados todos os arquivos executáveis e de configuração do projeto: PHP, JavaScript, JSON, roteamento Vercel, `.htaccess`, SQL e referências de assets. CSS e imagens foram verificados quanto à presença/referência, mas não possuem lógica financeira/autenticação.

## Correções realizadas

1. **Resposta JSON contaminada por aviso PHP**
   - `config/http.php` chamava `curl_close()`.
   - PHP 8.5 marca `curl_close()` como deprecated; runtimes com `display_errors` podem imprimir `<br><b>Deprecated...</b>` antes do JSON.
   - Removida a chamada e desabilida a exibição de erros no cliente. Erros continuam nos logs.
   - `mc_json()` limpa buffer de saída antes de devolver JSON.
   - Frontend agora trata respostas que não sejam JSON sem mostrar `Unexpected token '<'`.

2. **Exceções antes do bloco de tratamento**
   - A conexão PDO e criação de schema ocorriam antes do `try/catch` principal.
   - Agora banco + schema + validações ficam dentro do tratamento global da API.

3. **`.env` real e `.git` presentes no ZIP recebido**
   - Ambos foram removidos da distribuição v9.
   - O pacote contém apenas `.env.example` sem credenciais reais.

4. **Validação de produção acoplada à senha administrativa**
   - Checkout não depende mais de `ADMIN_PASSWORD` para funcionar.
   - Segredos de núcleo, bootstrap administrativo e pagamento têm validações separadas.

5. **Webhook idempotente porém não atômico**
   - Antes o evento era gravado antes da atualização do pedido. Se a atualização falhasse, a repetição do webhook poderia ser descartada como duplicada.
   - Agora gravação do evento + atualização do pedido ficam na mesma transação PostgreSQL.

6. **Comissão hard-coded como “15%” na descrição do split**
   - Agora a descrição usa dinamicamente `COMMISSION_RATE`.
   - `COMMISSION_RATE` é validada entre `0` e `1`.

7. **Wallet de split sem validação**
   - `ASAAS_SPLIT_WALLET_YURI`, quando preenchida, precisa ser UUID válido.
   - Split só é ativado se existir wallet válida e comissão maior que zero.

8. **Cidade IBGE não validada no checkout**
   - O Asaas recebe `customerData.city` como código IBGE.
   - Checkout agora exige que o CEP tenha carregado o código da cidade antes da criação.

9. **Painel administrativo podia avançar logística de pedido não pago**
   - Status de preparação/envio/entrega agora exigem `status=PAID`.

10. **JSON de entrada inválido era tratado como objeto vazio**
    - `mc_body()` agora retorna erro 400 para JSON inválido.

11. **Catálogo podia falhar silenciosamente**
    - `products.json` ausente/malformado agora gera erro controlado.
    - Estrutura mínima dos produtos é validada.

12. **Compatibilidade sem mbstring**
    - Limitação de strings usa fallback para `substr/strlen` se `mbstring` não estiver disponível.

13. **HTTP externo**
    - Verificação TLS explicitamente habilitada.
    - Redirect automático desabilitado.
    - Erros de serialização JSON são tratados.

## Testes executados

- `php -l` em todos os arquivos PHP: OK.
- `node --check` em todos os JavaScripts: OK.
- `products.json`, `vercel.json` e `composer.json`: JSON válido.
- 19 produtos encontrados.
- Todas as imagens referenciadas pelos 19 produtos existem.
- Ordem `runtime-config.js -> payment-config.js -> scripts dependentes`: OK.
- Nenhuma página `.html`: OK.
- `.env` real no pacote final: NÃO.
- `.git` no pacote final: NÃO.
- Padrão de API Key Asaas real no pacote: NÃO.
- Connection string PostgreSQL real no pacote: NÃO.
- `/runtime-config.js` executado via CLI sem STDERR e com JavaScript válido: OK.
- `/api/health` executado via CLI sem STDERR e com JSON válido: OK.
- Testes unitários simples de catálogo, comissão 15% e CPF: OK.

## Limites da auditoria

Auditoria estática e testes locais reduzem falhas, mas não provam ausência absoluta de vulnerabilidades. O teste final de ponta a ponta depende das credenciais Sandbox reais e do runtime Vercel: criação Checkout Asaas, callback, webhook, persistência Neon e fluxo de pagamento devem ser testados após o redeploy.

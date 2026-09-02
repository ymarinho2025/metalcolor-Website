# Backend Metal Color no padrão lógico do SGC

Esta versão mantém o frontend/e-commerce da Metal Color, mas troca a arquitetura Node das APIs por um backend PHP inspirado diretamente no `SGC(3).zip`:

- `config/load_env.php`: carrega `.env` local sem sobrescrever variáveis da Vercel.
- `config/database.php`: PDO PostgreSQL/Neon, incluindo fallback de endpoint Neon igual ao SGC.
- `config/auth.php`: autenticação por JWT em cookie `HttpOnly`, `Secure` e `SameSite=Lax`; em cada request o usuário é confirmado no banco.
- `password_hash()` / `password_verify()` no lugar de hash manual.
- `metalcolor_user_logins`: auditoria de login por IP.
- `api/index.php`: front controller PHP único para todas as APIs, equivalente ao roteamento central do SGC.
- Vercel executa somente `/api/*` no runtime PHP; páginas e assets continuam estáticos.

## Variáveis novas obrigatórias

Além das variáveis já usadas pelo e-commerce, configure:

```
JWT_SECRET=<32+ caracteres aleatórios>
COOKIE_SECURE=true
COOKIE_DOMAIN=
```

Em localhost, use `COOKIE_SECURE=false`.

## Compatibilidade do frontend

Os endpoints continuam com os mesmos caminhos (`/api/auth/login`, `/api/create-checkout`, `/api/order`, etc.), então o JavaScript atual não precisa ser reescrito.

## Mudança importante de autenticação

A versão Node usava sessões persistidas no banco. Esta versão segue a lógica do SGC: JWT assinado em cookie. A tabela antiga `metalcolor_sessions` pode permanecer no banco, mas não é mais necessária.

Se existirem usuários reais criados pela versão Node com hash `scrypt$...`, essas senhas não são migradas automaticamente. Para um projeto ainda em teste, a forma mais simples é recriar as contas. Novas senhas são salvas com `password_hash()` do PHP.

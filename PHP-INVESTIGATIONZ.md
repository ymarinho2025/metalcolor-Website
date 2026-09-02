# Metal Color — estrutura PHP no padrão InvestigationZ

Esta versão remove as páginas `.html`. Todas as páginas visuais são `.php` e entram pelo mesmo front controller (`api/index.php`), seguindo a ideia usada no InvestigationZ.

## O que mudou

- `index.html` virou `index.php`.
- Todos os `*/index.html` viraram `*/index.php`.
- Não há páginas HTML no projeto.
- `vercel.json` envia todas as rotas não-estáticas para `api/index.php`.
- O front controller resolve a rota e executa apenas arquivos `.php` dentro do projeto.
- `config/page.php` é executado antes de cada página e aplica cabeçalhos, sessão e guardas de autenticação.
- `/runtime-config.js` é gerado dinamicamente em PHP e carrega nome da loja, WhatsApp e catálogo do arquivo servidor `data/products.json`.
- O navegador deixa de ter uma lista de preços duplicada em `payment-config.js`; ele recebe o catálogo publicado pelo PHP.
- Preço final, frete, comissão e cobrança continuam sendo recalculados e validados no backend. O JavaScript nunca é fonte de verdade financeira.

## Rotas

`/` → `index.php`

`/produtos/` → `produtos/index.php`

`/produtos/produto1/` → `produtos/produto1/index.php`

`/login/` → `login/index.php`

`/conta/` → `conta/index.php` (protegida pelo PHP)

`/admin/` → `admin/index.php` (protegida pelo PHP e exige ADMIN)

As APIs continuam em `/api/...` no mesmo `api/index.php`.

# Well Eco Admin

Painel administrativo da **Well Soluções Ambientais** — gestão de coletas, MTR, clientes e equipe.

## Requisitos

- PHP 8.0+
- MySQL/MariaDB (InnoDB)
- Apache com `mod_rewrite`
- Composer

## Setup local (XAMPP)

1. Clone/copie o projeto em `C:\xampp\htdocs\pjt\admin.well.eco`
2. Copie `.env.example` para `.env` e ajuste:
   - `DB_NAME=well_admin`
   - `URL=http://localhost/pjt/admin.well.eco`
3. Instale dependências: `composer install`
4. Execute as migrations em `database/migrations/` (ordem numérica) no banco `well_admin`
5. Acesse: `http://localhost/pjt/admin.well.eco`

### Login inicial (seed)

| Campo | Valor |
|-------|-------|
| E-mail | `admin@well.eco` |
| Senha | `WellEco@2026` |

Altere a senha após o primeiro acesso.

## Banco de dados

| Ambiente | Banco | Uso |
|----------|-------|-----|
| Local novo | `well_admin` | Painel Well Eco |
| Local legado | `well_antigo` | Referência / migração ETL |

## Documentação

- [ARCHITECTURE.md](ARCHITECTURE.md) — arquitetura, pastas, RBAC (leitura obrigatória para IAs)
- [docs/SECURITY.md](docs/SECURITY.md) — autenticação e permissões
- [docs/DATABASE.md](docs/DATABASE.md) — schema e migrations
- [docs/ROADMAP.md](docs/ROADMAP.md) — Fase 2 (API, SINIR, banco)

## Estrutura resumida

```
app/Controller/Admin/   # Controllers do painel web
app/Service/            # Regras de negócio (fonte única)
app/Model/Entity/       # Acesso a dados
routes/admin/           # Rotas HTTP
resources/view/         # Templates HTML
database/migrations/    # SQL versionado
```

## Git

Não commitar `.env`. Repositório GitHub a ser configurado pelo time.

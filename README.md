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
| **Produção (VPS)** | `well_admin` | Sistema oficial em uso |
| Local dev | `well_admin` | Desenvolvimento / testes |
| Arquivo legado | `well_antigo` | Somente referência ou ETL inicial — **não** corrigir produção reimportando daqui |

Migração do painel antigo **concluída**. Correções em dados já lançados: migrations e scripts em `database/` — ver [docs/MIGRACAO_DADOS.md](docs/MIGRACAO_DADOS.md) §15.

## Documentação

- [ARCHITECTURE.md](ARCHITECTURE.md) — arquitetura, pastas, RBAC (leitura obrigatória para IAs)
- [docs/SECURITY.md](docs/SECURITY.md) — autenticação e permissões
- [docs/DATABASE.md](docs/DATABASE.md) — schema e migrations
- [docs/API.md](docs/API.md) — API REST v1 (app coletor FlutterFlow)
- [docs/FLUTTERFLOW.md](docs/FLUTTERFLOW.md) — setup telas e API Calls no FlutterFlow
- [docs/BRANDING.md](docs/BRANDING.md) — paleta de cores Well Coletas (app + painel)
- [docs/ROADMAP.md](docs/ROADMAP.md) — Fase 2 (SINIR, banco, portal cliente)
- [docs/MIGRACAO_DADOS.md](docs/MIGRACAO_DADOS.md) — histórico ETL + **operação em produção (pós-cutover)**

## Estrutura resumida

```
app/Controller/Admin/   # Controllers do painel web
app/Controller/Api/     # API REST /api/v1
app/Service/            # Regras de negócio (fonte única)
app/Model/Entity/       # Acesso a dados
routes/admin/           # Rotas HTTP
resources/view/         # Templates HTML
database/migrations/    # SQL versionado
```

## Git

Não commitar `.env`. Repositório GitHub a ser configurado pelo time.

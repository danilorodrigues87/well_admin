# ARCHITECTURE.md — Well Eco Admin

> Leia este arquivo **antes** de alterar o código. Público: desenvolvedores e agentes de IA.

**Projeto:** `admin.well.eco`  
**Banco local:** `well_admin`  
**Banco legado (migração):** `well_antigo`  
**Stack:** PHP 8 MVC custom · MySQL InnoDB · Bootstrap 5 (SB Admin) · jQuery

---

## 1. Visão do produto

Single-tenant (sem multi-escola). Painel web para:

- **Administradores** — usuários, funções, módulos, cadastros
- **Gestores** — agendamentos, clientes, rotas
- **Coletores** — lançamento de coletas/MTR no browser

**Fase 2:** API REST `/api/v1` (app coletor FlutterFlow), SINIR emissão, banco PIX/boleto.

---

## 2. Bootstrap

| Peça | Onde |
|------|------|
| Entrada | `index.php` → `includes/app.php` |
| Autoload | Composer PSR-4: `App\` → `app/` |
| Env | `App\Common\Environment` → `.env` |
| DB | `App\Model\Db\Database` (PDO) |
| Views | `App\Utils\View` → `resources/view/**/*.html` |
| Rotas | `routes/admin.php` → `routes/admin/*` |
| Sessão | `App\Session\User\Login` — chave `well-eco-user` |

**Constante `URL`:** detectada em `includes/app.php` a partir do request + `.env`.

---

## 3. RBAC (funções e módulos)

```
funcoes ── funcao_modulos ── modulos
   │
usuarios ── usuario_modulos (grant/revoke opcional)
```

- `ModuleGateHelper::podeAcessar($slug, $userSession)` — verifica acesso
- Função com `is_admin=1` → todos os módulos
- Menu lateral: `Admin\Page::getMenu()` filtra por `modulos` efetivos
- Rotas protegidas: `required-admin-login` + `required-module:{slug}`

**Registry de módulos:** `App\Common\SystemModules` (slug, label, link, ícone, grupo).

---

## 4. Camadas — regra de ouro

```
Controller Admin  ──┐
                    ├──► Service (Fase 2+) ──► Entity ──► MySQL
API (futuro)      ──┘
```

- **Controller:** HTTP, render view, JSON AJAX
- **Service:** regra de negócio — `App\Service\ColetaService` (coletas/MTR)
- **Entity:** queries PDO; sempre prepared statements em código novo
- **Nunca** duplicar lógica entre controllers

---

## 5. Segurança

- Senhas: `password_hash` / `password_verify` (bcrypt)
- Admin web: sessão PHP + **CSRF** (`CsrfHelper`) em todos POST
- SQL: prepared statements (`Database::execute($sql, $params)`)
- `.env` nunca no Git

---

## 6. Pastas

```
app/Http/              Router, Request, Response, Middleware
app/Controller/Admin/  Painel web
app/Controller/Autentication/
app/Common/            SystemModules, Environment, Helpers
app/Model/Entity/
app/Session/User/
database/migrations/   SQL numerado — aplicar via phpMyAdmin ou mysql CLI
resources/view/        Templates {{placeholder}}
storage/               Evidências e boletos (fora do Git)
docs/                  Documentação complementar
.cursor/rules/         Regras Cursor Agent
```

---

## 7. Migrations

| Arquivo | Conteúdo |
|---------|----------|
| `001_rbac.sql` | funcoes, modulos, funcao_modulos, usuarios, usuario_modulos |
| `002_seed.sql` | Módulos, funções Admin/Coletor/Gestor, user admin |
| `003_cadastros.sql` | Clientes, tipos_residuos, veículos, rotas |
| `006_coletas.sql` | Coletas/MTR, snapshot, itens, evidências |
| `007_coletas_legacy_prep.sql` | Colunas ETL legado em coletas |
| `008_sinir.sql` | Colunas SINIR em coletas/tipos_residuos + tabela `sinir_envios` |

Novas migrations: prefixo numérico crescente. Atualizar `docs/DATABASE.md`.

### Integração SINIR (Fase A)

```
app/Common/SinirConfig.php          — leitura .env SINIR_*
app/Service/Sinir/
  SinirAuthService.php              — POST /token (Token API WS → acesso)
  SinirGateway.php                  — HTTP cURL para admin.sinir.gov.br
  SinirPayloadBuilder.php           — monta salvarManifestoLote (Fase B)
  SinirService.php                  — badge listagem + auditoria catálogo
```

Envio real em `ColetaService::finalizar()` — **Fase B** (após token do cliente). Doc: `docs/SINIR.md`.

---

## 8. Legado

Referência: `C:\xampp\htdocs\pjt\admin.well.antigo`  
Dump: `wellec99_app.sql` · Banco local: `well_antigo`

Não copiar código legado procedural — reimplementar via MVC + Services.

---

## 9. Changelog

| Data | Alteração |
|------|-----------|
| 2026-09-10 | Scaffold inicial: MVC, RBAC, login, dashboard |
| 2026-09-10 | Etapa 2: CRUDs cadastros + gestão usuários/funções/módulos |
| 2026-09-10 | Fix encoding UTF-8 (modulos); normalização resíduos (residuo_classes, residuo_grupos, FKs em tipos_residuos) |
| 2026-09-10 | Etapa 3: coletas/MTR — wizard coletor, listagem, agendamentos, ColetaService, migration 006 |
| 2026-09-10 | Etapa 4: evidências WebP — `EvidenceStorageService`, `docs/EVIDENCIAS.md`, script ETL download legado |
| 2026-09-10 | Etapa 5: ETL coletas legado — `007_coletas_legacy_prep.sql`, `LegacyPesoParser`, `etl_import_coletas.php` |
| 2026-09-11 | Branding Well (logo/favicon); Etapa 6: impressão MTR `/painel/coletas/mtr/{id}` |
| 2026-09-11 | Paginação compacta (`Pagination::renderNav`, janela ±3 estilo CTI) + filtros `.crud-filter` em todas as listagens CRUD |
| 2026-09-11 | Rotas: gestão de atribuições cliente×coletor em `/painel/rotas/atribuicoes/{id}` + lote sem coletor |
| 2026-09-11 | Fix filtros CRUD (`.crud-filters-row`), lançar coleta com paginação, soft-delete/ativo nos formulários |
| 2026-09-11 | Filtros estilo CTI (`#barra-filtros-lista`, debounce busca) + SweetAlert2 (desativar/sucesso/erro) |
| 2026-09-11 | Fix JS global `well-ajax.js`: ordem de scripts (`listagem` antes do init), filtros POST `acao=listar`, Swal em wizard/coletas/funções |
| 2026-09-11 | Etapa 7: Perfil (`/painel/perfil`) — dados + trocar senha via `PerfilService` |
| 2026-09-11 | Etapa 8: Dashboard KPIs via `DashboardService` |
| 2026-09-11 | Etapa 9: Relatórios coletas + export CSV via `RelatorioService` |
| 2026-09-11 | SINIR: teste auth — `/gettoken` desativado (ago/2026); novo fluxo Token API WS → `POST /token`; doc em `docs/SINIR.md` |
| 2026-09-11 | SINIR Fase A: migration `008_sinir`, services skeleton, códigos SINIR em tipos_residuos, badge na listagem coletas, smoke test CLI |
| 2026-09-11 | Planos: tabela `plano_itens` (saldo incluso + valor excedente/kg), import legado, `PlanoCobrancaService` |
| 2026-09-12 | API REST v1 app coletor: JWT, `routes/api.php`, controllers `App\Controller\Api\*`, `ApiAuthService`, `ColetaApiPresenter`, `docs/API.md` |
| 2026-09-12 | FlutterFlow Fase 2a: App State + API Calls (Login, Auth Me, Clientes) no projeto well-coletas-by2777; guia `docs/FLUTTERFLOW.md` |

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
| `010_clientes_sinir_unidade.sql` | `clientes.sinir_cod_unidade` (gerador no portal MTR) |
| `011_inter_cobrancas.sql` | Cobranças BolePix emitidas via API Banco Inter |
| `012_faturas_inter.sql` | Competência, valor calculado, detalhes JSON, e-mail em `inter_cobrancas` |
| `013_config_sistema.sql` | Config global multa/juros (`config_sistema`) |

Novas migrations: prefixo numérico crescente. Atualizar `docs/DATABASE.md`.

### Integração SINIR

```
app/Common/SinirConfig.php          — leitura .env SINIR_*
app/Service/Sinir/
  SinirAuthService.php              — POST /token (Token API WS → acesso)
  SinirGateway.php                  — HTTP cURL para admin.sinir.gov.br
  SinirPayloadBuilder.php           — manifestoJSONDtos → salvarManifestoLote
  SinirManifestoService.php         — envio, parse resposta, sinir_envios
  SinirCatalogService.php           — listas SINIR + sugestão mapeamento tipos_residuos
  SinirService.php                  — badge, smoke test, reenvio
app/Model/Entity/SinirEnvio.php     — histórico de tentativas
```

`ColetaService::finalizar()` dispara envio SINIR quando `SINIR_ENABLED=true`. Doc: `docs/SINIR.md`.

### Integração Banco Inter + Faturamento

```
app/Common/InterConfig.php              — leitura .env INTER_*
app/Common/CobrancaConfig.php           — multa/juros (.env + config_sistema)
storage/inter/                          — certificado.crt, chave.key (fora do Git)
storage/boletos/                        — PDFs locais (fora do Git)
app/Service/Banco/BancoGatewayInterface.php
app/Service/Inter/
  InterGateway.php                      — HTTP cURL + mTLS
  InterAuthService.php                  — OAuth2 + cache token
  InterCobrancaService.php              — cobranca/v3 (emitir, consultar, cancelar)
  InterPayloadBuilder.php               — Cliente → payload API v3
  InterService.php                      — smoke test CLI
app/Service/FaturamentoService.php      — relatório competência, lote, enriquecer PDF/PIX
app/Service/PlanoCobrancaService.php    — valor plano + excedentes/mês
app/Service/MailService.php             — envio SMTP boleto
app/Controller/Admin/Pagamentos.php     — /painel/pagamentos
app/Model/Entity/InterCobranca.php
app/Model/Entity/ConfigSistema.php
```

Doc: `docs/INTER.md`. Smoke: `php database/scripts/inter_smoke_token.php`.

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
| 2026-09-14 | SINIR Fase B: `SinirManifestoService`, envio em `ColetaService::finalizar()`, reenvio no painel, `clientes.sinir_cod_unidade` |
| 2026-09-14 | Branding Well Coletas: paleta Light/Dark em `panel-theme.css`, tema padrão `prefers-color-scheme`, doc `docs/BRANDING.md` |
| 2026-09-14 | API app coletor: login multi-módulo (`ApiAppModules`), RBAC por rota, `GET /dashboard/resumo`, `GET /agendamentos`, `GET/POST /perfil`, busca em `GET /coletas`; FF: App State RBAC, menu Home, páginas stub |
| 2026-09-15 | Banco Inter: `InterConfig`, services OAuth/cobrança, migration `011_inter_cobrancas`, certificados em `storage/inter/`, doc `docs/INTER.md` |
| 2026-09-15 | Pagamentos: `/painel/pagamentos` — faturamento mensal, lote Inter, multa/juros, PDF/PIX/e-mail; migrations `012`/`013` |
| 2026-09-15 | Fix `LegacyPesoParser`: peso legado `5.200 Kg` = 5,2 kg (não 5200); script `repair_legacy_peso.php` |
| 2026-09-15 | Clientes: migration `010` (sinir_cod_unidade), ViaCEP no cadastro; Planos: fix save (`acao=salvar`), IBAMA via `IbamaCodigoHelper`; normalização `XX.XX.XX` |
| 2026-09-15 | Planos: `plano_itens` só `tipo_residuo_id` (drop nome/cod_ibama), modal simplificado, cobrança por ID, `saldo_compartilhado` por valor excedente |
| 2026-09-15 | Fase 5 planos: drop `clientes.saldo_residuo` (migration `016`), agendamentos exibem saldo do plano; remove `coletas_mensais` do CRUD; webhook Inter `POST /api/v1/webhooks/inter/cobranca` |
| 2026-09-15 | Planos: `gera_credito` em `plano_itens` — recicláveis (papelão, alumínio) descontam mensalidade por kg coletado |
| 2026-09-15 | Backfill coletas legado: `TipoResiduoMatcher` + `ColetaItemLegacyResolver` (embalagem truncada, cliente/plano) + `audit_coleta_itens_tipo.php` — 100% `tipo_residuo_id` |
| 2026-09-15 | SINIR sync: `SinirCatalogService` + `sinir_sync_residuos.php` (listas API → códigos em `tipos_residuos`, `--dry-run`/`--csv`) |
| 2026-09-15 | Coleta wizard: motorista = select de coletores (bloqueado para função Coletor); Tom Select nos resíduos; modal loading na finalização; SINIR não bloqueia HTTP |

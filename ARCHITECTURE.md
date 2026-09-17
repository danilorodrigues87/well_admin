# ARCHITECTURE.md — Well Eco Admin

> Leia este arquivo **antes** de alterar o código. Público: desenvolvedores e agentes de IA.

**Projeto:** `admin.well.eco`  
**Banco local:** `well_admin`  
**Banco legado (migração):** `well_antigo`  
**Stack:** PHP 8 MVC custom · MySQL InnoDB · Bootstrap 5 (SB Admin) · jQuery

---

## 1. Visão do produto

Multitenancy **preparado** (`operadoras` + `operadora_id`); MVP ativo só **Well id=1** (sem Painel Plataforma). Painel web para:

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
| `021_operadoras.sql` | Tabela `operadoras` (tenant); seed Well id=1 |
| `022_operadora_id_tenant.sql` | `operadora_id DEFAULT 1` em dados operacionais; `coleta_sequencia` por operadora |
| `023_operadora_config.sql` | Config por operadora (`operadora_config`) |
| `024_tipos_residuos_complementares.sql` | Tipos nacionais faltantes (agrotóxicos, efluentes, gorduras) |
| `025_cliente_usuarios.sql` | Login portal/API gerador (`cliente_usuarios`) |

Novas migrations: prefixo numérico crescente. Atualizar `docs/DATABASE.md`.

### Multitenancy (operadoras)

```
operadoras (id=1 Well) ── operadora_id ──► usuarios, clientes, coletas, rotas, planos…
App\Common\OperadoraScope::getOperadoraId()  — sessão ou default 1
App\Model\Entity\Operadora                   — branding, SINIR, defaults MTR por tenant
operadora_config                             — multa/juros e chaves editáveis (`OperadoraConfig` entity)
App\Model\Entity\OperadoraConfig             — get/set key-value por operadora_id
/painel/operadora                            — branding + transportador/destinador MTR (admin)
```

- **Catálogo compartilhado:** `tipos_residuos`, `residuo_classes`, `residuo_grupos` (sem `operadora_id`). Edição **somente no Painel Master/Plataforma** (futuro); no admin da operadora permanecem read-only / como estão hoje.
- **Painel Plataforma** (`/plataforma`, CRUD operadoras): postergado até operadora 2+.
- **Portal gerador:** `/gerador` (sessão `well-eco-gerador`) + API `/api/v1/gerador/*` (JWT `tipo=gerador`). Credenciais em `cliente_usuarios`; gestão no admin Clientes.
- Aplicar: `php database/scripts/apply_multitenancy_migrations.php` + `seed_operadora_well.php`.

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

**Modelo financeiro SaaS (decisão):** todos os boletos/PIX são emitidos pela **conta Inter da Well** (único par de credenciais/mTLS). Valores ficam registrados por `operadora_id` + `cliente_id` em `inter_cobrancas`; a Well **repassa** às operadoras assinantes (processo manual ou automatizado futuro). Vantagens: sem tokens/certificados por tenant; possibilidade de **taxa por transação** na plataforma. Repasse e taxas: implementação futura (Painel Plataforma / financeiro scoped).

### Portal gerador

```
/gerador/login              — sessão PHP (cliente_usuarios)
/gerador                    — dashboard (coletas + boletos)
/gerador/coletas            — listagem e MTR (somente do cliente logado)
/gerador/boletos            — cobranças Inter do cliente

/api/v1/gerador/login       — JWT tipo=gerador
/api/v1/gerador/me|coletas|boletos
```

`App\Service\GeradorAuthService`, `GeradorPortalService`, `GeradorScope`, `ClienteUsuario`.

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
| 2026-09-16 | Marca: `CompanyConfig` + `COMPANY_NAME` / `COMPANY_SHORT_NAME` no `.env` — e-mails e boletos usam Well S.A. / Well Soluções Ambientais (não "Well Eco") |
| 2026-09-16 | Pagamentos: sync status Inter + baixa manual na aba Cobranças emitidas; histórico paginado (20/página) e filtro status select |
| 2026-09-16 | Coleta wizard: fluxo em 2 passos — salvar rascunho (fotos/relatório) e depois Gerar MTR (rápido, sem reupload) |
| 2026-09-16 | Rotas Google Maps: geolocalização em `clientes`, `RotaScopeService` (RBAC rota/coletor), painel `/painel/rota-do-dia`, API `/rota-do-dia/*`, `GoogleMapsService` (Geocoding + Routes API) |
| 2026-09-17 | Fix crítico `RotaScopeService::paradasDoDiaQuery` (ordem de bind SQL no JOIN coletor); Rota do dia com seletor de data e empty state explicativo |
| 2026-09-17 | `GoogleMapsService`: resolve links `maps.app.goo.gl` (redirect + `!3d/!4d`); geocode automático ao carregar paradas na Rota do dia |
| 2026-09-17 | Maps: `GOOGLE_MAPS_SERVER_API_KEY` para Routes/Geocoding no PHP; Rota do dia usa SweetAlert2 nos erros |
| 2026-09-17 | Rotas R1: agendamento em lote por rota (`AgendamentoService::agendarRotaEmLote`), listagem com tenant, filtro `rota_id` na Rota do dia |
| 2026-09-17 | UI mobile-first admin: `panel-mobile.css` / `panel-mobile.js` (CRUD cards, modais, filtros, sidebar); dock coletor; login coletor → Rota do dia |
| 2026-09-17 | Rastreamento web: `frota_posicoes`, `rota_dia_parada_status` (035); `/painel/frota/mapa` + API frota; GPS na Rota do dia; PWA (`manifest.webmanifest`, `/sw.js`) |
| 2026-09-16 | Funcionários/Usuários: ação `resetar_senha` — botão chave na listagem redefine senha para `12345678` (hash via `password_hash`) |
| 2026-09-16 | Coletas: `data_recebimento` obrigatória para gerar MTR (rascunho pode ficar sem); doc `docs/MIGRACAO_DADOS.md`; script `repair_coletas_data_recebimento.php`; fallback no ETL |
| 2026-09-16 | Multitenancy Fase 3 (parcial): trait `TenantScoped` — entities clientes/coletas/rotas/planos/veículos/usuários/inter_cobrancas/rota_atribuicoes filtram `operadora_id`; services Dashboard, RotaScope, Relatorio, PlanoCobranca, RotaDoDia |
| 2026-09-16 | Multitenancy Fase 1: migrations `021`–`024`, `OperadoraScope`, `Operadora`, JWT `operadora_id`, ETL `--operadora-id`; catálogo SINIR revisado (58 tipos, 100% mapeados) |
| 2026-09-16 | Financeiro SaaS: boletos via conta Inter única Well + repasse futuro; catálogo resíduos editável só no master (futuro) |
| 2026-09-16 | Portal gerador Fase 6: migration `025`, `/gerador/*`, API `/api/v1/gerador/*`, CRUD acessos em Clientes |
| 2026-09-16 | Multitenancy Fase 4: `OperadoraConfig` entity, `CobrancaConfig`/`ColetaDefaults`/`CompanyConfig` por tenant; admin `/painel/operadora` |
| 2026-09-16 | Portal gerador refinado: badge portal em Clientes, `/gerador/perfil` (trocar senha), paginação coletas/boletos, filtro status, evidências na web |
| 2026-09-16 | UI portal gerador: template padrão admin (sidebar, tema dark/light, Chart.js), dashboard KPIs+gráficos, detalhe coleta/boleto, datas BR, valor cobrado = admin |
| 2026-09-16 | Dashboard admin: KPIs visuais + gráficos coletas/faturamento/status (Chart.js `dashboard-charts.js`) |
| 2026-09-16 | Suporte/termos/ajuda/contratos: migrations `027`–`033`; tickets `/painel/suporte`; ajuda `/painel/ajuda`; termos aceite admin+gerador; `/privacidade`; contratos comerciais operadora↔cliente (`clientes_contratos`, assinatura portal gerador) |
| 2026-09-16 | UX contratos/ajuda: tickets ocultos do menu; Central de ajuda com artigo por módulo (`034` + `seed_help_modulos.php`); `/painel/contratos` com listagem e criação; botão contrato em Clientes; preview em iframe |
| 2026-09-15 | Coleta wizard: motorista = select de coletores (bloqueado para função Coletor); Tom Select nos resíduos; modal loading na finalização; SINIR não bloqueia HTTP |

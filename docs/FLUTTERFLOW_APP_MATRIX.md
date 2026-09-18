# Matriz App Well Coletas — FlutterFlow × API × RBAC

> Inventário gerado após `sync_project` (force) em **2026-09-18 13:00 UTC** — projeto `well-coletas-by2777` (1030 arquivos YAML). Pastas `components/*_comp` vêm do YAML `folders`.

## Configuração atual (General)

| Item | Valor |
|------|--------|
| App | Well Coletas |
| Initial page | **SplashPage** |
| Routing | Habilitado (`wellcoletas://admin.well.eco.br`) |
| Nav Bar global FF | **Desligada** (cada tela traz `BottomNav` próprio) |
| API base (App State) | `https://admin.well.eco.br/api/v1` |
| API Calls no FF | **18** (sem rota/frota) |

## Páginas no projeto (14)

| # | Página FF | Scaffold ID | Bottom nav | Papel |
|---|-----------|-------------|------------|--------|
| 1 | SplashPage | Scaffold_splsh01 | Não | Gate token → Login ou app |
| 2 | LoginPage | Scaffold_7zli0let | Não | Auth (parcialmente ligado) |
| 3 | Dashboard | Scaffold_fs0lmdne | BottomNav | Início / KPIs / atalhos |
| 4 | CollectionsList | Scaffold_2lf7puaq | BottomNav2 + FAB | Lista coletas/MTR |
| 5 | CollectionDetail | Scaffold_ukmzuhl4 | BottomNav3 | Detalhe coleta |
| 6 | NewCollectionClientSelection | Scaffold_y00hrwko | Não | Escolher cliente |
| 7 | CollectionWizardStep12 | Scaffold_cfjqs6at | Não | Wizard transporte + resíduos |
| 8 | CollectionWizardFinalize | Scaffold_5bfhuarf | Não | Wizard finalizar + evidências |
| 9 | RouteOfTheDay | Scaffold_92jb2sfk | BottomNav4 | Rota do dia + mapa |
| 10 | FleetMap | Scaffold_t85tsu07 | BottomNav5 | Mapa frota (gestor) |
| 11 | UserProfileSettings | Scaffold_gx004zvy | Não | Perfil + configurações |
| 12 | PerfilPage | Scaffold_perfil1 | Não | **Stub antigo** — substituir por UserProfileSettings |
| 13 | ProjectDocumentation | Scaffold_anbtcnvs | Não | Só design — não navegar em produção |

**Nota:** Não há página dedicada **Veículos**, **Agendamentos**, **Termos**, **Privacidade** ou **Sobre o app** — veículos entram no wizard (catálogo); agendamentos só como chip no Dashboard; legal provavelmente dentro de UserProfileSettings (SettingsItem).

---

## Matriz principal: Página → API → RBAC

Legenda API: **OK** = call já existe no FF · **API** = endpoint existe no PHP, falta call no FF · **PLAN** = endpoint a implementar no backend (Fase 1 do plano) · **—** = estático/local

| Página FF | Módulo RBAC (slug) | API / dados | Calls FF hoje | Ações principais |
|-----------|-------------------|-------------|---------------|------------------|
| **SplashPage** | — | App State `authToken` | — | Navigate Login / Dashboard |
| **LoginPage** | (login exige ≥1 módulo app) | `POST /auth/login` | WellAdmin Login | Gravar token, userId, userName; **faltam** isAdmin, funcaoNome, userModulesCsv, operadora |
| **Dashboard** | `dashboard` | `GET /dashboard/resumo` | Dashboard Resumo | KPIs; gráfico BarChart precisa **PLAN** `graficos.coletas_por_mes`; atalhos RBAC |
| **Dashboard** (chip Nova Coleta) | `coleta_nova` | — | — | → NewCollectionClientSelection |
| **Dashboard** (chip Minhas Coletas) | `coletas` | — | — | → CollectionsList |
| **Dashboard** (chip Agendamentos) | `agendamentos` | `GET /agendamentos` | Agendamentos | **Falta página** AgendamentosList |
| **Dashboard** (chip Ver Rota) | `rota_dia` | — | — | → RouteOfTheDay |
| **Dashboard** (ActivityItem) | `dashboard` / `rota_dia` | resumo ou paradas | — | Dados mock — ligar API |
| **CollectionsList** | `coletas` | `GET /coletas` | Coletas Listar | Filtros status, busca, paginação |
| **CollectionsList** (FAB) | `coleta_nova` | — | — | → NewCollectionClientSelection |
| **CollectionDetail** | `coletas` | `GET /coletas/{id}` | Coleta Detalhe | Evidências: `GET .../evidencias/{ordem}` **sem call FF** |
| **CollectionDetail** (continuar) | `coleta_nova` | — | — | → Wizard se rascunho |
| **NewCollectionClientSelection** | `coleta_nova` | `GET /clientes/coleta` | Clientes Coleta | escopo pendentes/todos; tap → `POST /coletas` |
| **NewCollectionClientSelection** | `coleta_nova` | `POST /coletas` | Coleta Criar | → activeColetaId → Wizard |
| **CollectionWizardStep12** | `coleta_nova` | `GET /coletas/{id}` | Coleta Detalhe | On load |
| **CollectionWizardStep12** | `coleta_nova` | `PATCH .../transporte` | Coleta Transporte | + `data_recebimento` no JSON |
| **CollectionWizardStep12** | `coleta_nova` | Catálogos | Veículos, Tipos, Tratamentos | Dropdowns |
| **CollectionWizardStep12** | `coleta_nova` | `POST/DELETE .../itens` | Add/Remove Item | Resíduos |
| **CollectionWizardFinalize** | `coleta_nova` | `POST .../finalizar` | Finalizar | Multipart evidências **config manual FF** |
| **CollectionWizardFinalize** | `coleta_nova` | `POST .../cancelar` | Cancelar | |
| **RouteOfTheDay** | `rota_dia` | `GET /rota-do-dia/paradas` | **API** (sem FF) | Query `data`, gestor: `coletor_id`, `rota_id` **PLAN** paridade web |
| **RouteOfTheDay** | `rota_dia` | `POST .../otimizar`, `salvar-ordem` | **API** | Google Map + polyline |
| **RouteOfTheDay** | `rota_dia` | Switch GPS | `POST /frota/posicao` | **API** |
| **RouteOfTheDay** | `rota_dia` | Paradas coletado/pulado | `POST .../parada-status` | **PLAN** |
| **RouteOfTheDay** | `rota_dia` | — | — | url_launcher → Maps (`maps_url`) |
| **FleetMap** | `frota_mapa` | `GET /frota/posicoes` | **API** | Gestor/admin; atualização manual (sem polling agressivo) |
| **FleetMap** | `frota_mapa` | Google Maps markers | — | Integração FF Maps |
| **UserProfileSettings** | `perfil` | `GET /perfil` | Perfil | |
| **UserProfileSettings** | `perfil` | `POST /perfil/senha` | Perfil Senha | |
| **UserProfileSettings** | — | Tema, links legais | — | Termos: URL `/painel/termos-de-uso` ou web `/privacidade` |
| **PerfilPage** | — | — | — | **Remover ou redirecionar** para UserProfileSettings |

### Módulos RBAC (`ApiAppModules`)

Slugs que liberam login e gates no app:

`dashboard`, `coletas`, `coleta_nova`, `agendamentos`, `rota_dia`, `frota_mapa`, `clientes`, `rotas`, `relatorios`, `perfil`

| Persona | Slugs típicos |
|---------|----------------|
| Coletor | dashboard, coletas, coleta_nova, rota_dia, perfil |
| Gestor | + agendamentos, frota_mapa, (relatorios futuro) |
| Admin | todos (via `is_admin`) |

Condição FF: `userModulesCsv` Contains `{slug}` ou `isAdmin == true`.

---

## Componentes por pasta `components/*_comp` (46)

Organização no editor = pasta; vínculo técnico = `folders.widgetClassKeyToFolderKey` + instância na página (`get_page_summary` / `find_component_usages`).

| Pasta FF | Página | Componentes (nome FF · Container ID) |
|----------|--------|--------------------------------------|
| **Dashboard_comp** | `Dashboard` | ActivityItem · `hgtz50tg` · BottomNav · `pq8b5pn2` · BottomNavChild · `xosmedj9` · KpiCard · `p9vk0ltt` · NavItem · `jiambidn` |
| **CollectionsList_comp** | `CollectionsList` | BottomNav2 · `9j3rmotz` · BottomNavChild2 · `5orij9yl` · Button · `23ysw360` · CollectionCard · `vb6d8jex` · NavItem2 · `0q4d5g0v` · TextField · `7u8zms64` |
| **CollectionDetail_comp** | `CollectionDetail` | BottomNav3 · `rir8wq1y` · BottomNavChild3 · `jx1bzwlq` · Button2 · `4pm8ck4f` · DataRow · `cy5dwiti` · DetailSectionHeader · `3jf5yod6` · NavItem3 · `fa8y3kpp` · WasteItem · `z9201fbq` |
| **NewCollectionClientSelection_comp** | `NewCollectionClientSelection` | ClientCard · `yr54qmp6` · TextField2 · `32sj71ck` |
| **CollectionWizardStep12_comp** | `CollectionWizardStep12` | Button3 · `lwo6ha1t` · StepIndicator · `qwwjw26m` · TextField3 · `gijjnops` · WasteItem2 · `73lasyci` |
| **CollectionWizardFinalize_comp** | `CollectionWizardFinalize` | Button4 · `f6nkf1rw` · EvidenceSlot · `de7zitxu` · StepIndicator2 · `n2nn8i4f` · TextField4 · `8tvrda2r` |
| **RouteOfTheDay_comp** | `RouteOfTheDay` | BottomNav4 · `fn9h4a0h` · BottomNavChild4 · `i9j2g9p8` · Button5 (Otimizar) · `a3kulbgs` · NavItem4 · `qoi0qaa5` · RouteStop · `9787xt3g` · SwitchComponent · `w8lft5y9` |
| **FleetMap_comp** | `FleetMap` | BottomNav5 · `6rtpkuka` · BottomNavChild5 · `l5mmogz8` · Button6 (Enquadrar) · `8yuzhdyd` · CollectorTile · `1f8lk3dp` · NavItem5 · `xak19s3n` |
| **UserProfileSettings_comp** | `UserProfileSettings` | Button7 · `6qr3pqla` · SettingsGroup · `cppuucx4` · SettingsGroupChild · `rvgd0zit` · SettingsGroupChild2 · `o3l0qe7f` · SettingsGroupChild3 · `4hb65pj1` · SettingsItem · `q5ae4e0s` · SwitchComponent2 · `8gyse5wz` |

**Notas**

- `NavItem` / `NavItem2`…`NavItem5` = itens do bottom nav por tela (pares com `BottomNav` + `BottomNavChild*`).
- Vários `ButtonN` são CTAs distintos por fluxo (não duplicar lógica ao unificar nav).
- Para o agente: `get_component_summary({ componentName })` ou YAML `component/id-Container_*`.

---

## Lacunas vs prompt original (Visily / produto)

| Esperado | Status no FF |
|----------|----------------|
| Bottom nav: Início, Coletas, Rotas, Veículos | Parcial: Dashboard, CollectionsList, RouteOfTheDay, FleetMap têm nav; **sem aba Veículos** |
| Veículos (listagem) | **Ausente** — usar catálogo no wizard ou criar `VehiclesListPage` + `GET /catalogos/veiculos` |
| Agendamentos (tela) | **Ausente** — só chip no Dashboard |
| Perfil no dropdown | UserProfileSettings OK; unificar com avatar do header |
| Termos / Privacidade / Sobre | Provável em SettingsItem — confirmar links WebView |
| MainShell / página `page` wrapper | **Não encontrada** no sync — páginas estão soltas em `pages/` |
| Navegação ligada | **Nenhuma** navigate cacheada no Dashboard (ações ainda não wired) |

---

## API Calls — gap analysis

### Já no FlutterFlow (18)

Auth, Dashboard, Agendamentos, Clientes Coleta, Coletas CRUD + wizard, Catálogos (veículos, tipos, tratamentos), Perfil.

### Backend existe, falta criar no FF

| Endpoint | Módulo |
|----------|--------|
| `GET /rota-do-dia/paradas` | rota_dia |
| `POST /rota-do-dia/otimizar` | rota_dia |
| `POST /rota-do-dia/salvar-ordem` | rota_dia |
| `POST /frota/posicao` | rota_dia |
| `GET /frota/posicoes` | frota_mapa |

### Implementado no backend (2026-09-18) — falta call no FF

| Endpoint | Módulo |
|----------|--------|
| `GET /rota-do-dia/paradas?data&coletor_id&rota_id` | rota_dia |
| `POST /rota-do-dia/parada-status` | rota_dia |
| `GET /catalogos/coletores` | rota_dia |
| `GET /dashboard/resumo` + `graficos.coletas_por_mes`, KPI `paradas_hoje` | dashboard |
| Login `user.operadora_id`, `operadora_nome` | auth |

Multitenant **já funciona** na API via JWT `operadora_id` + `OperadoraScope` — falta expor no JSON do login para o app.

---

## Ordem recomendada de configuração (FF)

1. **Login** completo → App State RBAC → navigate **Dashboard** (não HomePage antiga).
2. **Unificar BottomNav** (1 componente, 4 abas: Dashboard, CollectionsList, RouteOfTheDay, FleetMap condicional `frota_mapa`).
3. **Dashboard** ← Dashboard Resumo + conditional chips.
4. **CollectionsList** ← Coletas Listar + FAB → NewCollectionClientSelection.
5. **Wizard** chain ← APIs coleta + catálogos.
6. **RouteOfTheDay** ← novas API calls + Maps + GPS.
7. **FleetMap** ← Frota Posicoes (gestor).
8. **UserProfileSettings** ← Perfil; remover **PerfilPage** stub.
9. Criar **AgendamentosPage** (opcional MVP: WebView ou list simples).
10. Criar **VehiclesListPage** ou remover aba Veículos do design.

---

## Divisão agente (repo PHP) vs você (FF)

| Agente | Você |
|--------|------|
| Endpoints PLAN + docs/API.md | Action flows, Navigate, ListView JSON paths |
| operadora no login JSON | Completar Login App State (modulos_csv, isAdmin) |
| Testes curl `/api/v1/*` | Unificar BottomNav, FAB, RBAC visibility |
| | Google Maps API key em Integrations |
| | Multipart Finalizar |
| | sync_project após mudanças grandes |

---

## Referências

- [docs/FLUTTERFLOW.md](FLUTTERFLOW.md) — login manual, ListView
- [docs/API.md](API.md) — contratos REST
- [docs/BRANDING.md](BRANDING.md) — tokens cor / Well S.A.

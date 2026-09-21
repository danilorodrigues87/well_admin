# Migração e tratamento de dados — Well Admin

> **Manutenção:** atualizar este documento sempre que houver novo script ETL/backfill, regra de negócio que afete dados legados, ou correção retroativa. Registrar também no changelog de `ARCHITECTURE.md`.

**Última revisão:** 2026-09-19 (cutover final + purge MTR teste)  
**Banco legado (somente leitura):** `well_antigo` (dump `wellec99_app.sql`)  
**Banco novo:** `well_admin`

---

## Índice

1. [Ordem de execução (migração inicial)](#1-ordem-de-execução-migração-inicial)
2. [Regras de negócio que afetam dados](#2-regras-de-negócio-que-afetam-dados)
3. [Cadastros base (004)](#3-cadastros-base-004)
4. [Resíduos — classes, grupos, encoding (005)](#4-resíduos--classes-grupos-encoding-005)
5. [Coletas / MTR (006–007 + ETL)](#5-coletas--mtr-006007--etl)
6. [Planos e cobrança (009–017)](#6-planos-e-cobrança-009017)
7. [Códigos IBAMA e SINIR (014, 008)](#7-códigos-ibama-e-sinir-014-008)
8. [Clientes — geolocalização (018)](#8-clientes--geolocalização-018)
9. [Hooks em tempo de execução (on-save)](#9-hooks-em-tempo-de-execução-on-save)
10. [Pendências conhecidas](#10-pendências-conhecidas)
11. [Checklist pós-migração](#11-checklist-pós-migração)
12. [Multitenancy — operadora_id (021–024)](#12-multitenancy--operadora_id-021024)
13. [Catálogo SINIR — revisão tipos_residuos](#13-catálogo-sinir--revisão-tipos_residuos)
14. [Cutover final — projeto antigo × admin novo](#14-cutover-final--projeto-antigo--admin-novo)

---

## 1. Ordem de execução (migração inicial)

| # | Ação | Arquivo |
|---|------|---------|
| 1 | Migrations base RBAC + seed + cadastros | `001`–`003` |
| 2 | Import cadastros legado | `004_import_legacy.sql` |
| 3 | Normalização resíduos + encoding | `005_*.sql` + `005b_fix_encoding_remap.php` (Windows) |
| 4 | Schema coletas | `006_coletas.sql` |
| 5 | Coluna legacy_manifesto | `007_coletas_legacy_prep.sql` |
| 6 | Import coletas/MTR | `etl_import_coletas.php` |
| 7 | Corrigir pesos legado | `repair_legacy_peso.php` |
| 8 | Backfill tipo_residuo_id em itens | `backfill_coleta_itens_tipo.php` |
| 9 | **Corrigir data_recebimento ausente** | `repair_coletas_data_recebimento.php` |
| 10 | Download evidências Cloudinary | `etl_download_evidencias.php` |
| 11 | Planos estruturados | `009` → `import_plano_itens_legacy.php` → `backfill_plano_itens_tipo.php` → `015`/`015b` |
| 12 | Normalizar IBAMA | `014` + `normalize_cod_ibama.php` |
| 13 | SINIR códigos | `008` + `010` → `sinir_sync_residuos.php` |
| 14 | Drop saldo_residuo cliente | `016` |
| 15 | Crédito recicláveis | `017` |
| 16 | Geocode clientes | `018` + `geocode_clientes.php` |
| 17 | **Multitenancy** (Well id=1) | `021`–`023` + `apply_multitenancy_migrations.php` |
| 18 | Tipos resíduo complementares | `024_tipos_residuos_complementares.sql` |
| 19 | Revisão catálogo SINIR | `apply_sinir_revisao_fixes.php` (se ainda não aplicado) |

---

## 2. Regras de negócio que afetam dados

### Coletas — `data_recebimento` e recebimento no destinador

| Campo | Significado |
|-------|-------------|
| `situacao_recebimento` | `pendente` ou `recebido` — resíduo chegou ao destinador final |
| `data_recebimento` | Data em que o resíduo foi recebido no destinador |

**Regra atual (2026-09-16):**

- **Rascunho:** pode salvar transporte e rascunho **sem** `data_recebimento`.
- **Finalizar / gerar MTR:** `data_recebimento` **obrigatória** (`ColetaService::assertDataRecebimentoParaFinalizar`).
- Se a data estiver preenchida na finalização, `situacao_recebimento` é ajustada para `recebido` automaticamente.
- Coletas **finalizadas** não são editáveis no painel — correções só via script SQL ou reabertura futura (não implementada).

**Legado / migração:**

- Muitos registros importados têm `data_recebimento` NULL.
- **Import ETL:** se legado não tem data, usa `data_coleta` como fallback (`etl_import_coletas.php`).
- **Repair pós-import:** `repair_coletas_data_recebimento.php` — `UPDATE` onde `data_recebimento IS NULL AND status='finalizada'` → copia `data_coleta`.
- `situacao_recebimento` legado: campo `situacao` = `'true'` → `recebido`; demais → `pendente` (independente da data).

### Pesos legado

- Formato texto em `well_antigo.coletas.peso` (ex.: `"Papelão - 5.200 Kg"`).
- **Regra corrigida:** ponto decimal = kg real (`5.200` = **5,2 kg**, não 5200).
- Parser: `LegacyPesoParser`.
- Re-importar itens após fix: `repair_legacy_peso.php`.

### Planos

- Legado: texto livre `saldo_residuo` + `valor_exced` por plano.
- Novo: `plano_itens` com `tipo_residuo_id`, `saldo_incluso_kg`, `valor_excedente`.
- `valor_excedente < 0` no legado → migration `017` marca `gera_credito=1` e usa valor absoluto.
- `clientes.saldo_residuo` removido (`016`) — saldo vem do plano via `PlanoCobrancaService`.

### RBAC rotas (2026-09-16)

- Coletor vê apenas clientes da sua rota (`RotaScopeService`).
- `rota_atribuicoes.coletor_id` importado como NULL em `004` — **atribuir coletores manualmente** após migração.

---

## 3. Cadastros base (004)

**Arquivo:** `database/migrations/004_import_legacy.sql`

| Origem legado | Destino | Transformações |
|---------------|---------|----------------|
| `clientes` | `clientes` | IDs preservados; `desativado`→`inativo`; `0000-00-00`→NULL |
| `funcionarios` | `usuarios` | Função Coletor; skip e-mail duplicado |
| `planos`, `veiculos`, `lista_rotas`, `tipos_de_residuos` | tabelas homônimas | IDs preservados |
| `rotas` | `rotas` + `rota_atribuicoes` | Sem `coletor_id` no legado |

**Pendência:** mapear coletores às rotas no painel `/painel/rotas/atribuicoes/{id}`.

---

## 4. Resíduos — classes, grupos, encoding (005)

**Arquivos:** `005_encoding_residuos_normalizados.sql`, `005b_fix_encoding_remap.php`

- Cria `residuo_classes` e `residuo_grupos`.
- Mapeia 8 variantes de classe legado → 5 classes canônicas.
- Remove colunas `classe`/`grupo` de `tipos_residuos`.
- **Windows:** executar `005b` se acentos corromperem.

**Matcher runtime:** `TipoResiduoMatcher` + `ColetaItemLegacyResolver` (nomes truncados no parser).

---

## 5. Coletas / MTR (006–007 + ETL)

### Schema (`006_coletas.sql`)

Campos relevantes: `status` (rascunho/finalizada/cancelada), `data_coleta`, `situacao_recebimento`, `data_recebimento`, `legacy_manifesto`.

### Import (`etl_import_coletas.php`)

| Legado | Novo | Regra |
|--------|------|-------|
| `manifesto` | `numero_mtr`, `legacy_manifesto` | UNIQUE legacy |
| `data_coleta` | `data_coleta` | `validDate()` |
| `data_recebimento` | `data_recebimento` | `validDate()`; **se NULL → `data_coleta`** |
| `situacao` | `situacao_recebimento` | `'true'` → recebido |
| `peso` | `coleta_itens` | `LegacyPesoParser` |
| — | `status` | sempre `finalizada` |
| — | `coletor_id` | `ETL_COLETOR_ID` (.env, default 1) |
| — | `operadora_id` | `--operadora-id=1` (default Well; usar em imports futuros de outras operadoras) |

Flags: `--dry-run`, `--limit=N`, `--purge-local`, `--operadora-id=N`.

### Scripts de correção

| Script | O que faz |
|--------|-----------|
| `repair_legacy_peso.php` | Re-parse pesos com parser corrigido |
| `backfill_coleta_itens_tipo.php` | Preenche `tipo_residuo_id` NULL |
| `audit_coleta_itens_tipo.php` | Diagnóstico (não altera dados) |
| `repair_coletas_data_recebimento.php` | `data_recebimento ← data_coleta` onde NULL |
| `etl_download_evidencias.php` | Cloudinary → WebP local |
| `purge_coletas_mtr_teste.php` | Remove faixa de MTR teste; reajusta `coleta_sequencia` |

---

## 6. Planos e cobrança (009–017)

| Script / migration | Função |
|--------------------|--------|
| `import_plano_itens_legacy.php` | Parse texto legado → `plano_itens` |
| `backfill_plano_itens_tipo.php` | Match `tipo_residuo_id` antes do refactor |
| `015` / `015b` | FK obrigatória, drop colunas redundantes |
| `016` | Drop `clientes.saldo_residuo` |
| `017` | `gera_credito` para excedente negativo |

Parser: `LegacyPlanoParser`.

---

## 7. Códigos IBAMA e SINIR (014, 008)

| Ferramenta | Função |
|------------|--------|
| `IbamaCodigoHelper` | Normaliza para `XX.XX.XX`; inválido → NULL |
| `normalize_cod_ibama.php` | Batch em `tipos_residuos` |
| `sinir_sync_residuos.php` | API SINIR → tra/tie/tia/cla/uni |
| `sinir_revisao_csv.php` | Lista manual ~40 casos edge |

---

## 8. Clientes — geolocalização (018)

**Migration:** `latitude`, `longitude`, `maps_link`, `geocode_status`.

| Origem | Quando |
|--------|--------|
| `geocode_clientes.php` | Batch pós-migração |
| Save em `Clientes` controller | On-save automático |
| `RotaDoDiaService` | Lazy se pendente |

Requer `GOOGLE_MAPS_API_KEY` no `.env`.

---

## 9. Hooks em tempo de execução (on-save)

| Trigger | Serviço | Efeito |
|---------|---------|--------|
| Salvar cliente | `GoogleMapsService` | Geocode; status pendente/ok/erro |
| Salvar tipo resíduo | `IbamaCodigoHelper` | Normaliza cod_ibama |
| Finalizar coleta | `ColetaService` | Exige `data_recebimento`; gera MTR |
| Salvar transporte (rascunho) | `ColetaService::salvarTransporte` | Data recebimento opcional |

---

## 10. Pendências conhecidas

- [ ] Executar `repair_coletas_data_recebimento.php` após import completo de coletas legado
- [ ] Atribuir coletores às rotas (`rota_atribuicoes.coletor_id` NULL pós-004)
- [x] Revisar tipos SINIR — catálogo 58/58 com mapeamento completo (2026-09-16)
- [ ] Geocode clientes sem coordenadas (`geocode_clientes.php`)
- [ ] Coletas finalizadas legado **não editáveis** — ajustes retroativos só via script
- [ ] Evidências: ETL pode falhar em URLs Cloudinary expiradas (log em `storage/logs/evidencias_etl.log`)
- [ ] **Go-live:** aplicar migrations `021`–`024` em produção antes do cutover (`apply_multitenancy_migrations.php`)
- [x] Fase 3 multitenancy (parcial): entities + services principais com filtro `operadora_id`
- [x] Fase 4: `OperadoraConfig` entity, `CobrancaConfig` por tenant, `/painel/operadora`
- [ ] Fase 3 restante: FaturamentoService, controllers API pontuais

---

## 11. Checklist pós-migração

```bash
# 1. Coletas importadas
php database/scripts/etl_import_coletas.php --dry-run
php database/scripts/etl_import_coletas.php

# 2. Pesos corrigidos
php database/scripts/repair_legacy_peso.php --dry-run
php database/scripts/repair_legacy_peso.php

# 3. Tipos nos itens
php database/scripts/backfill_coleta_itens_tipo.php --dry-run
php database/scripts/backfill_coleta_itens_tipo.php

# 4. Data recebimento ausente → data coleta
php database/scripts/repair_coletas_data_recebimento.php --dry-run
php database/scripts/repair_coletas_data_recebimento.php

# 5. Evidências
php database/scripts/etl_download_evidencias.php --limit=100

# 6. Planos
php database/scripts/import_plano_itens_legacy.php

# 7. Geocode
php database/scripts/geocode_clientes.php --limit=50

# 8. Multitenancy (Well operadora 1 — obrigatório antes do go-live)
php database/scripts/apply_multitenancy_migrations.php --dry-run
php database/scripts/apply_multitenancy_migrations.php

# 9. Catálogo SINIR (se ambiente novo ou legado desatualizado)
php database/scripts/apply_sinir_revisao_fixes.php --dry-run
php database/scripts/apply_sinir_revisao_fixes.php
php database/scripts/sinir_sync_residuos.php --dry-run
```

**Auditoria:**

```bash
php database/scripts/audit_coleta_itens_tipo.php
php database/scripts/audit_sinir_revisao_uso.php
```

**Validação multitenancy pós-migration:**

```sql
SELECT operadora_id, COUNT(*) FROM clientes GROUP BY operadora_id;
SELECT operadora_id, COUNT(*) FROM coletas GROUP BY operadora_id;
SELECT * FROM coleta_sequencia;
SELECT id, nome FROM operadoras;
```

Esperado em cutover Well: tudo em `operadora_id = 1`.

---

## 12. Multitenancy — operadora_id (021–024)

**Objetivo:** preparar isolamento por operadora sem alterar UX (Well = id 1).

| Migration | Conteúdo |
|-----------|----------|
| `021_operadoras.sql` | Tabela `operadoras`; seed id=1 |
| `022_operadora_id_tenant.sql` | Coluna `operadora_id DEFAULT 1` + FK; `coleta_sequencia` keyed por operadora |
| `023_operadora_config.sql` | `operadora_config` (copia `config_sistema` → operadora 1) |
| `024_tipos_residuos_complementares.sql` | Tipos nacionais (agrotóxicos, efluentes, gorduras) |

**Scripts:**

```bash
php database/scripts/apply_multitenancy_migrations.php
php database/scripts/seed_operadora_well.php   # roda automaticamente após apply
```

**Dados legado (`004`, ETL coletas):** registros importados **antes** da migration 022 recebem `operadora_id=1` via `DEFAULT` no `ALTER`. Reimport ETL deve usar `--operadora-id=1`.

**Novos cadastros:** controllers/services passam a gravar `operadora_id` da sessão (`OperadoraScope::getOperadoraId()`).

**O que NÃO muda no legado:**

- Dump `well_antigo` — somente leitura; não tem `operadora_id`.
- IDs preservados (`clientes.id`, `coletas.legacy_manifesto`) — inalterados.

**Produção (go-live):**

1. Backup `well_admin`.
2. `apply_multitenancy_migrations.php` (janela de manutenção curta).
3. `seed_operadora_well.php` com `.env` de produção (SINIR, branding, Inter).
4. Validar contagens SQL acima.
5. Smoke login admin + uma coleta teste.

---

## 13. Catálogo SINIR — revisão tipos_residuos

**Arquivos:** `storage/sinir_revisao_sugestoes.csv`, `apply_sinir_revisao_fixes.php`, `audit_sinir_revisao_uso.php`

**Ações aplicadas (2026-09-16):**

- Exclusão de 12 tipos inválidos/duplicados (locações, teste, sem uso).
- Correção IBAMA/tra/tie/tia/cla nos tipos com coletas/planos.
- Padronização de nomes (sentence case PT).
- Remapeamento coleta_itens `#43 teste` → `#78 Outros resíduos urbanos`.
- Desativação `#58` limpeza banheiros (serviço legado — 5 coletas históricas).
- Inclusão tipos complementares na migration `024`.

**Estado atual:** 58 tipos ativos, **58/58** com mapeamento SINIR completo.

**Ambiente novo:** rodar `apply_sinir_revisao_fixes.php` após import `004` se catálogo vier do legado sem revisão.

---

## 14. Cutover final — projeto antigo × admin novo

Cenário: banco do **projeto antigo** já alinhado ao schema atual (`well_admin`); **admin novo** (código deste repositório) vai para produção (ex.: VPS). Evitar choque entre **MTRs de teste** no ambiente novo e **MTRs reais** vindos do legado/ETL.

### Pontos de conflito (checklist)

| Área | Risco | Mitigação |
|------|--------|-----------|
| **`numero_mtr` UNIQUE** | Testes 11917–11921 ocupam faixa que o legado pode reimportar | Purge testes **antes** do ETL ou import do dump legado |
| **`legacy_manifesto` UNIQUE** | ETL ignora manifesto duplicado; MTR local sem legacy pode bloquear número | Purge só coletas **sem** `legacy_manifesto` (script avisa se tiver) |
| **`coleta_sequencia.ultimo_mtr`** | Após purge/import, próximo MTR errado | Script de purge reajusta; pós-ETL: `GREATEST(MAX(numero_mtr), sequencia)` |
| **IDs `clientes` / catálogos** | Preservados no `004` — não misturar dump parcial | Um único caminho: dump completo **ou** ETL a partir de `well_antigo`, não os dois sobrepostos |
| **Migrations 027–036** | Código novo exige tabelas (suporte, contratos, frota, help) | Rodar scripts `apply_migrations_027_033.php`, `034`, `035`, `036` no banco de cutover |
| **Multitenancy 021–024** | Obrigatório antes do go-live | `apply_multitenancy_migrations.php` + `seed_operadora_well.php` |
| **Usuários / senhas** | Seed local ≠ produção | Manter usuários do banco real; não sobrescrever com `002_seed` |
| **Inter / SINIR / `.env`** | Credenciais só no servidor | VPS: `.env` produção; SINIR só com IP que completa TLS (VPS direto ou relay) |
| **Evidências Cloudinary** | URLs expiradas no legado | `etl_download_evidencias.php` pós-import (log em `storage/logs/`) |
| **Flutter / API** | App aponta para URL antiga | Atualizar `apiBaseUrl` após DNS do admin novo |

### Remover MTRs de teste (11917–11921)

No servidor onde está o banco **antes** de importar coletas reais ou fazer merge final:

```bash
cd ~/admin.well.eco.br   # ou caminho do projeto
php database/scripts/purge_coletas_mtr_teste.php --dry-run
php database/scripts/purge_coletas_mtr_teste.php --from=11917 --to=11921
```

Validação:

```sql
SELECT numero_mtr, id, legacy_manifesto FROM coletas WHERE numero_mtr BETWEEN 11917 AND 11921;
SELECT operadora_id, ultimo_mtr FROM coleta_sequencia WHERE operadora_id = 1;
SELECT COALESCE(MAX(numero_mtr), 0) AS max_mtr FROM coletas WHERE operadora_id = 1;
-- ultimo_mtr deve ser >= max_mtr (idealmente iguais após purge)
```

### Ordem sugerida — migração final (uma janela)

1. **Backup** completo `well_admin` (+ arquivos `storage/` se já houver evidências locais).
2. **Purge** MTRs teste (comando acima).
3. Aplicar migrations pendentes **034–036** (se ainda não).
4. Se faltarem coletas legado no banco atual: `etl_import_coletas.php` (sem `--purge-local` salvo se souber o que apaga).
5. Repairs: `repair_legacy_peso`, `backfill_coleta_itens_tipo`, `repair_coletas_data_recebimento`.
6. **Deploy** código admin novo na VPS; `.env`; `composer install --no-dev`.
7. Smoke: login, listagem coletas, imprimir MTR legado, **uma** coleta teste com MTR **11922+** (após confirmar sequência).
8. DNS `admin.well.eco.br` → VPS; desativar painel antigo.

### O que **não** fazer

- Importar dump legado **por cima** de coletas de teste sem purge (colisão `numero_mtr`).
- Rodar `002_seed.sql` em banco que já tem usuários reais.
- Habilitar `SINIR_ENABLED=true` antes do teste de rede na VPS.

---

## Histórico de alterações deste documento

| Data | Alteração |
|------|-----------|
| 2026-09-19 | Seção 14 cutover final; script `purge_coletas_mtr_teste.php` (MTR 11917–11921) |
| 2026-09-16 | Multitenancy: seções 12–13; migrations 021–024; ETL `--operadora-id`; checklist go-live; catálogo SINIR revisado |
| 2026-09-16 | Criação: inventário completo ETL/backfill; regra `data_recebimento` obrigatória no MTR; script `repair_coletas_data_recebimento.php`; fallback no import |

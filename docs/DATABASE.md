# Banco de dados — well_admin

## Conexão local

```
DB_HOST=localhost
DB_NAME=well_admin
DB_USER=root
DB_PASS=
```

## Tabelas Fase 1 (RBAC)

| Tabela | Descrição |
|--------|-----------|
| `funcoes` | Papéis (Admin, Coletor, Gestor) |
| `modulos` | Módulos do menu (slug único) |
| `funcao_modulos` | N:N função ↔ módulo |
| `usuarios` | Login (FK funcao_id) |
| `usuario_modulos` | Override grant/revoke por usuário |

## Aplicar migrations

```bash
mysql -u root well_admin < database/migrations/001_rbac.sql
mysql -u root well_admin < database/migrations/002_seed.sql
```

## Tabelas cadastros (`003_cadastros.sql`)

| Tabela | Descrição |
|--------|-----------|
| `planos` | Planos de serviço |
| `residuo_classes` | Classes/categorias NBR (normalizado, ID) |
| `residuo_grupos` | Grupos por classe (código A/B/E…, FK classe_id) |
| `tipos_residuos` | Catálogo de resíduos (FK classe_id + grupo_id) |
| `veiculos` | Frota |
| `rotas` | Rotas de coleta |
| `clientes` | Clientes (endereço inline na Fase 1) |
| `rota_atribuicoes` | Cliente ↔ rota ↔ coletor (uso na Etapa 3) |

## Migrations adicionais

| Arquivo | Conteúdo |
|---------|----------|
| `004_import_legacy.sql` | Import cadastros de `well_antigo` |
| `005_encoding_residuos_normalizados.sql` | Tabelas residuo_* + FKs |
| `005b_fix_encoding_remap.php` | Corrige UTF-8 no Windows + remapeia IDs (`php database/migrations/005b_fix_encoding_remap.php`) |

**Importante:** migrations SQL com acentos devem ser aplicadas via PHP ou `mysql --default-character-set=utf8mb4` — PowerShell corrompe UTF-8.

## Coletas / MTR (`006_coletas.sql`)

| Tabela | Descrição |
|--------|-----------|
| `coleta_sequencia` | Sequencial do número MTR |
| `coletas` | Cabeçalho (status: rascunho/finalizada/cancelada) |
| `coleta_snapshot` | Dados imutáveis gerador/transportador/destinador |
| `coleta_itens` | Resíduos coletados (FK tipo_residuo_id) |
| `coleta_evidencias` | Fotos em `storage/coletas/{id}/` |

## Coletas legado (Etapa 5)

| Arquivo | Conteúdo |
|---------|----------|
| `007_coletas_legacy_prep.sql` | Coluna `legacy_manifesto` em `coletas` |
| `database/scripts/etl_import_coletas.php` | Import ~11.515 MTRs de `well_antigo` |
| `database/scripts/etl_download_evidencias.php` | Baixa ~1.270 fotos Cloudinary → WebP local |

```bash
mysql -u root well_admin < database/migrations/007_coletas_legacy_prep.sql
php database/scripts/etl_import_coletas.php --purge-local
php database/scripts/etl_download_evidencias.php
```

Legado para ETL: banco `well_antigo` (dump `wellec99_app.sql`).

## SINIR — integração MTR (`008_sinir.sql`)

| Tabela / coluna | Descrição |
|-----------------|-----------|
| `coletas.sinir_man_numero` | Número MTR retornado pelo SINIR |
| `coletas.sinir_codigo_barras` | Código de barras do manifesto SINIR |
| `coletas.sinir_status` | `pendente` / `enviado` / `erro` (listagem) |
| `coletas.sinir_enviado_em` | Timestamp do último envio bem-sucedido |
| `tipos_residuos.tra_codigo` … `uni_codigo` | Mapeamento códigos API (`codigoTecnologia`, `codigoTipoEstado`, etc.) |
| `clientes.sinir_cod_unidade` | Código unidade do gerador no portal MTR (`010_clientes_sinir_unidade.sql`) |
| `sinir_envios` | Histórico de tentativas (payload JSON, erro, tentativa) |

```bash
mysql -u root well_admin < database/migrations/008_sinir.sql
mysql -u root well_admin < database/migrations/010_clientes_sinir_unidade.sql
php database/scripts/sinir_smoke_token.php
```

Smoke test exige `SINIR_INTEGRATION_TOKEN` no `.env` (gerado no portal MTR).

## Banco Inter — cobranças (`011_inter_cobrancas.sql`)

| Tabela / coluna | Descrição |
|-----------------|-----------|
| `inter_cobrancas` | Cobranças BolePix emitidas via API Inter |
| `inter_cobrancas.codigo_solicitacao` | ID retornado pela API Inter |
| `inter_cobrancas.cliente_id` | FK opcional para `clientes` |
| `inter_cobrancas.status` | `PENDENTE`, `PAGO`, `CANCELADO`, etc. |
| `inter_cobrancas.payload_request` / `payload_response` | JSON da requisição/resposta |
| `inter_cobrancas.competencia` | Mês faturado (`YYYY-MM`), unique com `cliente_id` |
| `inter_cobrancas.valor_calculado` | Total automático antes de ajuste manual |
| `inter_cobrancas.detalhes_json` | Breakdown plano + excedentes |
| `inter_cobrancas.multa_mora_json` | Multa/juros aplicados na emissão |
| `inter_cobrancas.email_enviado_em` / `email_erro` | Controle de envio SMTP |

**Config global:** `config_sistema` — chaves `cobranca.multa_*` e `cobranca.mora_*` (migration `013`).

```powershell
Get-Content database\migrations\011_inter_cobrancas.sql | C:\xampp\mysql\bin\mysql.exe -u root well_admin
Get-Content database\migrations\012_faturas_inter.sql | C:\xampp\mysql\bin\mysql.exe -u root well_admin
Get-Content database\migrations\013_config_sistema.sql | C:\xampp\mysql\bin\mysql.exe -u root well_admin
php database/scripts/inter_smoke_token.php
```

Smoke test exige `INTER_CLIENT_ID`, `INTER_CLIENT_SECRET` e `INTER_CONTA_CORRENTE` no `.env` + certificados em `storage/inter/`. Ver `docs/INTER.md`.

## Planos — itens de resíduo (`009` + `015`)

| Tabela / coluna | Descrição |
|-----------------|-----------|
| `plano_itens` | Regra comercial por resíduo (FK `tipo_residuo_id` NOT NULL) |
| `plano_itens.tipo_residuo_id` | FK → `tipos_residuos` (UNIQUE com `plano_id`) |
| `plano_itens.saldo_incluso` | Quantidade incluída no plano (kg/l/un) |
| `plano_itens.valor_excedente` | Preço por unidade acima do saldo (R$) |
| `plano_itens.saldo_compartilhado` | `1` = saldo somado em pool com itens de mesmo `valor_excedente` |
| `plano_itens.gera_credito` | `1` = reciclável: desconto na mensalidade = kg coletados × tarifa (ignora saldo) |

Nome e código IBAMA vêm de `JOIN tipos_residuos` — não duplicados em `plano_itens`.

Scripts: `backfill_plano_itens_tipo.php`, `import_plano_itens_legacy.php`

Cobrança mensal (regra): `valor_mensal` + excedentes via `PlanoCobrancaService::calcularMes(cliente_id, 'YYYY-MM')`.

## Clientes — saldo do plano (`016`)

| Alteração | Descrição |
|-----------|-----------|
| `clientes.saldo_residuo` | **Removido** — saldo incluso vem de `plano_itens` via plano do cliente |

```powershell
Get-Content database\migrations\016_drop_cliente_saldo_residuo.sql | C:\xampp\mysql\bin\mysql.exe -u root well_admin
```

Backfill legado: `php database/scripts/backfill_coleta_itens_tipo.php` (preenche `coleta_itens.tipo_residuo_id`).  
Auditoria: `php database/scripts/audit_coleta_itens_tipo.php` (opcional `--csv=storage/audit_coleta_tipo.csv`).  
Nomes truncados (`1 Sacos de`, `1 Granel de`…): `App\Common\Helpers\ColetaItemLegacyResolver` usa cliente, plano e contexto da coleta.

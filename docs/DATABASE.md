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
| `tipos_residuos.tra_codigo` … `uni_codigo` | Mapeamento códigos API (`traCodigo`, `tieCodigo`, etc.) |
| `sinir_envios` | Histórico de tentativas (payload JSON, erro, tentativa) |

```bash
mysql -u root well_admin < database/migrations/008_sinir.sql
php database/scripts/_test_sinir_token.php
```

Smoke test exige `SINIR_INTEGRATION_TOKEN` no `.env` (gerado no portal MTR). Apagar `_test_sinir_token.php` após validar.

## Planos — itens de resíduo (`009_plano_itens.sql`)

| Tabela / coluna | Descrição |
|-----------------|-----------|
| `plano_itens` | Saldo incluso e valor excedente por resíduo (substitui texto legado) |
| `plano_itens.saldo_incluso` | Quantidade incluída no plano (kg/l/un) |
| `plano_itens.valor_excedente` | Preço por unidade acima do saldo (R$) |

Import legado: `php database/scripts/import_plano_itens_legacy.php`

Cobrança mensal (regra): `valor_mensal` + excedentes via `PlanoCobrancaService::calcularMes(cliente_id, 'YYYY-MM')`.

# CollectionsList — listagem de coletas (API)

Página: **CollectionsList** · `Scaffold_dwij8cyv` · API **WellAdmin Coletas Listar** (`GET /coletas`).

## Contrato (backend)

Query: `page`, `per_page` (máx. 50, default 15), `status` (`rascunho` | `finalizada` | `cancelada` ou vazio = todos), `busca` (nome/cidade do cliente).

Resposta (`success: true`):

| JSON Path | Uso |
|-----------|-----|
| `$.data.items[]` | Lista (campos: `id`, `numero_mtr`, `cliente_nome`, `status`, `data_coleta`, `hora`) |
| `$.data.pagination.page` | Página atual |
| `$.data.pagination.last_page` | Total de páginas |
| `$.data.pagination.total` | Total de registros |

Coletor vê apenas coletas com `coletor_id` = usuário logado.

## Page State

| Campo | Tipo | Default |
|-------|------|---------|
| `busca` | String | `""` |
| `filterStatus` | String | `""` (todos) |
| `pageCurrent` | Integer | `1` |
| `totalPages` | Integer | `1` |
| `isLoading` | Boolean | `false` |
| `coletaItems` | List\<ColetaListItem\> (Data Struct) | Preenchido via Custom Function `parseColetaItemsFromApi` |

## Fluxo de dados

1. **On Page Load** → API com App State `apiBaseUrl` / `authToken` + Page State `pageCurrent`, `filterStatus`, `busca` → atualiza `coletaItems` e paginação.
2. **ListView** → *Generate Children from Variable* = Page State `coletaItems` (`List<ColetaListItem>`). **Passo manual no editor** — o MCP não grava isso de forma que o painel Issues aceite; tentativas via YAML geram *Value Key invalid* e *Generator variable does not exist*.
3. **Custom Function** `parseColetaItemsFromApi(apiJson)` converte o JSON Body da API em structs (MTR, status label, data/hora formatados).

### Passo manual — ListView + textos do card (obrigatório)

A API + `parseColetaItemsFromApi` já preenchem **Page State → `coletaItems`** (confira no Debug Panel). O ListView **não repete** o card sozinho enquanto **Value** estiver **UNSET**.

#### A) Gerar um card por item (`ListView_qsfh7n67`)

1. Página **CollectionsList** → Widget Tree → clique no **ListView** (pai do card), **não** no Container do card.
2. Painel direito → aba **Generating Children from Variable** (ícone de lista/quadradinhos — **não** use **Backend Query** neste ListView; a API já roda no On Page Load / chips / busca).
3. Preencha:
   - **Variable Name:** `coletaItem` (qualquer nome; use o mesmo nos textos abaixo).
   - **Value:** ícone de lápis → **Page State** → **`coletaItems`** (`List` de **`ColetaListItem`**).
4. **Save**.

Depois do Save, o template do card vira “filho gerado” e os bindings passam a oferecer **`coletaItem`** / **GENERATOR_VARIABLE**.

#### B) Ligar os textos do card

Em cada **Text** dentro do card (ainda com o ListView selecionado ou filho):

| Onde no card | Binding |
|--------------|---------|
| Linha do MTR (ex. “Nº MTR …”) | **Combine Text** ou Text = **GENERATOR_VARIABLE** (`ListView`) → **`display_mtr`** *(Data Struct Field)* — ou prefixo fixo “Nº MTR ” + campo |
| Nome do cliente | **GENERATOR_VARIABLE** → **`cliente_nome`** |
| Data/hora (ícone calendário) | **GENERATOR_VARIABLE** → **`data_hora`** |
| Chip de status | **GENERATOR_VARIABLE** → **`status_label`** |

Caminho típico no seletor de variável: **Widget State / Generator** → item da lista → **Available Options** → campo do struct (`display_mtr`, etc.).

#### C) Conferir

- Debug: `coletaItems` com `Item [0]`, `Item [1]`…
- Run/Test: vários cards iguais ao template, com textos diferentes.
- Se só aparecer **um** card estático: **Value** ainda UNSET ou Save não foi feito.

#### D) Outros

- **Custom Function** `parseColetaItemsFromApi`: [FLUTTERFLOW_PARSE_COLETA_FUNCTION.md](FLUTTERFLOW_PARSE_COLETA_FUNCTION.md).
- **Action Output:** nome **único** por widget que chama a API (`coletasResultPageLoad`, `coletasResultChipTodos`, …).

3. **Busca** → **TextField nativo** `TextField_clbus01`. **On Change:** `actsrchp1` (`busca` ← Widget State) → Wait 600 ms → `actsrchapi` (`busca` ← Page State) → `actsrchprs` / `coletasResultSearch`. Sem `initialText` ligado a `busca` (evita conflito com digitação). Backend: `nome_fantasia`, `razao_social`, `cidade`; se só dígitos, também `numero_mtr`.
4. **Chips** → alteram `filterStatus` e recarregam (página 1). Valores **exatos** (API + ícone ✓):
   | Chip | `filterStatus` | Visibilidade do check |
   |------|----------------|------------------------|
   | Todos | `""` (vazio) | `filterStatus` **Is Empty** |
   | Rascunho | `rascunho` | **Equal To** `rascunho` |
   | Finalizada | `finalizada` | **Equal To** `finalizada` |
   | Cancelada | `cancelada` | **Equal To** `cancelada` |
   Não use rótulos da UI (`Rascunho`, `Finalizado`) na condição — só os slugs acima.
5. **Setas** → página anterior / próxima (com limite 1 … `totalPages`).
6. **Toque no card** (`Row_xco81ne5`) → Bottom Sheet **CardCom4** (`Container_r51yjnte`). Parâmetros a partir de **GENERATOR_VARIABLE** `coletaItem` / struct `ColetaListItem`:
   | Parâmetro CardCom4 | Campo struct |
   |--------------------|--------------|
   | `id` | `id` |
   | `title` | `cliente_nome` |
   | `subtitle` | `data_hora` |
   | `status` | `status_label` |

7. **Ver detalhes** (CardCom4 → botão nativo `Container_iegeo1mr`): executa
   **Navigate direto** para **CollectionDetail** com **`coletaId`** ← parâmetro
   `id` do componente. Não feche o bottom sheet antes da navegação: isso descarta
   o contexto que contém o parâmetro.
8. **CollectionDetail** `On Init`: **WellAdmin Coleta Detalhe** recebe o route
   param `coletaId` e preenche os Page States com `$.data.resumo.*`,
   `$.data.coleta.cliente_nome` e os três primeiros elementos de
   `$.data.itens[]`. A tela mostra relatório/coleta, cliente, status, data/hora,
   peso total, veículo, motorista, destinador, recebimento, resíduos e relatório.
   Cards de resíduos sem conteúdo ficam ocultos. **Imprimir / PDF / MTR:** ainda
   sem ação (próxima etapa).

## Teste

Deploy PHP em produção; Test Mode com token de coletor. `GET https://admin.well.eco.br/api/v1/coletas?page=1&per_page=15&status=&busca=`

Matriz: [FLUTTERFLOW_APP_MATRIX.md](FLUTTERFLOW_APP_MATRIX.md).

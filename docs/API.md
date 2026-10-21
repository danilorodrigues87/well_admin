# API REST — App Coletor (v1)

Base URL: `{URL}/api/v1`  
Autenticação: `Authorization: Bearer {token}` (JWT)

## Configuração (.env)

| Variável | Descrição |
|----------|-----------|
| `API_ENABLED` | `true`/`false` — desliga login e endpoints |
| `API_JWT_SECRET` | Chave HS256 (mín. 32 caracteres) |
| `API_JWT_TTL` | Validade do token em segundos (padrão 86400) |
| `API_CORS_ORIGINS` | `*` ou lista separada por vírgula |

## Formato de resposta

Sucesso:
```json
{ "success": true, "data": { ... } }
```

Erro:
```json
{ "success": false, "error": { "code": "invalid_credentials", "message": "..." } }
```

## RBAC (módulos)

Login exige ao menos um módulo operacional do app (`dashboard`, `coletas`, `coleta_nova`, `agendamentos`, `clientes`, `rotas`, `relatorios`, `perfil`).

Cada rota autenticada exige o módulo correspondente via middleware `required-api-module:{slug}`.

Resposta `user` inclui: `modulos[]`, `modulos_csv` (slugs separados por vírgula, útil no FlutterFlow), `is_admin`, `funcao_nome`, `operadora_id` (MVP: sempre `1` — Well).

JWT inclui claim `operadora_id` (desde migration multitenancy `022`).

## Endpoints

### Saúde

`GET /health` — sem autenticação

### Autenticação

`POST /auth/login`
```json
{ "email": "coletor@well.eco", "password": "senha" }
```
Resposta: `{ token, expires_at, user }`  
Requer ao menos um módulo operacional do app.

`GET /auth/me` — usuário autenticado (qualquer módulo do app)

### Dashboard

`GET /dashboard/resumo` — módulo `dashboard`

Resposta (`data`):

```json
{
  "meta": { "schema_version": 2, "hoje": "2026-09-30" },
  "kpis": {
    "hoje": "2026-09-30",
    "coletas_hoje": 3,
    "coletas_mes": 12,
    "rascunhos": 2,
    "paradas_hoje": 8,
    "urgentes": 3,
    "atrasados": 5,
    "clientes_ativos": 120,
    "solicitacoes_pendentes": 1,
    "mtr_sinir_pendente": 4,
    "rotas_com_coleta_hoje": 2
  },
  "graficos": {
    "coletas_por_mes": { "labels": ["Abr/26", "Mai/26"], "values": [10, 12] },
    "coletas_por_status": { "labels": ["Finalizada", "Rascunho"], "values": [40, 2] }
  },
  "cards": [
    {
      "key": "coletas_hoje",
      "module": "coletas",
      "label": "Coletas finalizadas hoje",
      "hint": "Ver coletas de hoje",
      "value": 3,
      "accent": "primary"
    }
  ],
  "atalhos": [
    { "slug": "coleta_nova", "label": "Nova coleta", "target": "NewCollectionClientSelection" }
  ],
  "atividades": [
    {
      "tipo": "coleta",
      "titulo": "Cliente Exemplo",
      "subtitulo": "Coleta finalizada",
      "data_hora": "29/09/2026 14:30",
      "ref_id": 99
    }
  ]
}
```

- **KPIs** alinhados ao dashboard web (seção Operação), sem financeiro.
- **`cards` / `atalhos`:** filtrados pelos módulos do usuário (`modulos` no JWT recarregado no auth).
- **`graficos.coletas_por_status`:** omitido se o usuário não tem módulo `coletas`.
- Coletor (função Coletor): KPIs, gráficos e atividades filtrados por `coletor_id`; admin/gestor vê totais da operadora.
- **`atividades`:** até 10 coletas finalizadas mais recentes. `data_hora` vem em `dd/mm/aaaa` e, quando há hora, `dd/mm/aaaa HH:mm`.

### Agendamentos

`GET /agendamentos?page=1&per_page=20&busca=` — módulo `agendamentos`

Clientes ativos ordenados por prioridade e `proxima_coleta`. Cada item inclui `plano_nome` e `saldo_plano` (soma do saldo incluso em `plano_itens` — substitui o antigo `saldo_residuo` por cliente).

Coletor: vê clientes **vinculados a alguma rota** (mesmo pool cadastral; coletor não é fixado em `rota_atribuicoes`). Ordenação por prioridade e `proxima_coleta`.

### Rota do dia

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| GET | `/rota-do-dia/paradas` | `rota_dia` | Paradas do dia (query: `data`, gestor: `coletor_id`, `rota_id`) |
| GET | `/rota-do-dia/rotas` | `rota_dia` | Rotas cadastrais com agendamento na data (query: `data`) → `rotas[]` com `id`, `nome`, `paradas` |
| POST | `/rota-do-dia/otimizar` | `rota_dia` | Otimizar ordem (body/query: `data`, `coletor_id`, `rota_id`; body: `origin_lat`, `origin_lng`, `cliente_ids[]` opcional) |
| POST | `/rota-do-dia/salvar-ordem` | `rota_dia` | Persistir ordem manual (`ordem[]`: `cliente_id`, `ordem`; query/body: `data`, `coletor_id`) |
| POST | `/rota-do-dia/parada-status` | `rota_dia` | Status da parada (`cliente_id`, `status`: `pendente` \| `coletado` \| `pulado`; query/body: `data`, `coletor_id`) |

Resposta `GET paradas`: `paradas`, `coletor_id`, `data`, `total`, `rota_id`, `sem_rota` (`true` se a operadora não tem clientes em rotas cadastrais).

Sem `rota_id` (>0): API devolve lista vazia (`total: 0`) e `message` pedindo seleção — mesmo comportamento do painel web (não mistura paradas de todas as rotas).

Coletor **não** é definido na rota: `coletor_id` na query identifica quem opera (ordem/status do dia); paradas vêm do cadastro rota×cliente + filtros urgentes/vencidos. Nova coleta: `POST /coletas` / fluxo rascunho grava `coletas.coletor_id`.

Resposta `otimizar`: `paradas`, `polyline` (encoded), `distancia_metros`, `duracao_segundos`, `origin`, `maps_dir_url` (URL Google Maps Directions multi-parada), `static_map_url` (Google Static Maps com pinos + polyline; exige Static Maps API na chave de servidor).

Resposta `GET paradas` também inclui `static_map_url` (pinos sem polyline).

Paradas incluem `status_parada`, `maps_url`, coordenadas quando disponíveis.

### Catálogos (gestor)

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| GET | `/catalogos/coletores` | `rota_dia` | Lista coletores ativos (`id`, `nome`, `email`) |

### Frota (GPS)

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| POST | `/frota/posicao` | `rota_dia` | Registrar posição (`latitude`, `longitude`; opcional: `accuracy_m`, `heading`, `speed_mps`) |
| GET | `/frota/posicoes` | `frota_mapa` | Última posição por coletor (`?minutos=120` opcional) |

### Perfil

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| GET | `/perfil` | `perfil` | Dados do usuário logado |
| POST | `/perfil/senha` | `perfil` | Trocar senha |

Body trocar senha:
```json
{
  "senha_atual": "...",
  "nova_senha": "...",
  "confirmacao": "..."
}
```

### Clientes para coleta

`GET /clientes/coleta?page=1&per_page=15&busca=&prioridade=&escopo=pendentes` — módulo `coleta_nova`

- `escopo`: `pendentes` (default) ou `todos`
- Mesma regra de rotas do painel web (`ColetaService::clientesParaColetaPaginado`)

### Catálogos

| Endpoint | Módulo | Conteúdo |
|----------|--------|----------|
| `GET /catalogos/veiculos` | `coleta_nova` | Veículos ativos |
| `GET /catalogos/tipos-residuos` | `coleta_nova` | Tipos de resíduo ativos |
| `GET /catalogos/tratamentos` | `coleta_nova` | Lista de tratamentos |
| `GET /catalogos/transportadoras` | `coleta_nova` | Transportadoras ativas (`id`, `nome`, `cnpj`, `is_padrao`, `label`) |
| `GET /catalogos/destinadores` | `coleta_nova` | Destinadores ativos (`id`, `nome`, `cnpj`, `endereco`, `is_padrao`, `label`) |
| `GET /catalogos/coletores` | `rota_dia` | Coletores ativos (`id`, `nome`, `email`) — também usado no wizard |

### Coletas

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| GET | `/coletas?busca=&status=&page=&per_page=&data_inicio=&data_fim=&situacao_recebimento=&filtro_mtr=` | `coletas` | Listar (coletor vê só as suas). Sem data válida, o intervalo é o mês atual. Uma data com hora (`2026-10-21 00:00:00`) vale pelo dia. Sem status, oculta canceladas. `situacao_recebimento`: `pendente` ou `recebido`. `filtro_mtr`: `com_mtr`, `sem_mtr` ou `mtr_pendente`. Cada item traz `data_hora_label` em `dd/mm/aaaa HH:mm` |
| POST | `/coletas` | `coleta_nova` | Iniciar rascunho `{ "cliente_id": 1, "data_coleta": "2026-10-05", "client_uuid": "uuid-v4" }` (`data_coleta` opcional; `client_uuid` opcional — idempotência offline/retry; servidor gera se omitido) |
| GET | `/coletas/{id}` | `coletas` | Detalhe completo (wizard) |
| PATCH | `/coletas/{id}/transporte` | `coleta_nova` | Salvar etapa 1 (transporte) e/ou etapa 4 (destinador) |
| POST | `/coletas/{id}/itens` | `coleta_nova` | Adicionar resíduo |
| DELETE | `/coletas/{id}/itens/{itemId}` | `coleta_nova` | Remover resíduo |
| POST | `/coletas/{id}/relatorio` | `coleta_nova` | Salvar rascunho etapa 3 (relatório + fotos; **não** finaliza) |
| POST | `/coletas/{id}/assinatura` | `coleta_nova` | Assinatura do cliente (JSON `assinatura` = data-URL PNG/JPEG) |
| POST | `/coletas/{id}/finalizar` | `coleta_nova` | Finalizar (multipart, fotos opcionais). **Requer `data_recebimento` preenchida** (PATCH transporte antes). |
| POST | `/coletas/{id}/cancelar` | `coleta_nova` | Cancelar relatório (rascunho ou finalizada). Se houver MTR no SINIR (`sinir_status=enviado`), cancela o manifesto antes. Body JSON opcional: `{ "justificativa": "..." }` (obrigatória na prática quando há MTR; default no servidor se vazia). |
| POST | `/coletas/{id}/mtr` | `coleta_nova` | Gerar MTR no SINIR, a mesma checagem do painel (`podeGerarMtr` + pré-check). Só coleta finalizada, sem MTR já emitido. Resposta `data.message` e `data.numero_mtr`. |
| GET | `/coletas/{id}/evidencias/{ordem}` | `coletas` | Imagem da evidência |
| GET | `/coletas/{id}/assinatura` | `coletas` | Imagem da assinatura do cliente. Exige o token. 404 se não houver arquivo |
| GET | `/coletas/{id}/pdf` | `coletas` | PDF do relatório (Dompdf, mesmo layout do painel) |
| GET | `/coletas/{id}/comprovante` | `coletas` | PDF estreito de 80 mm (cliente, data, resíduos, número do relatório e a marca). Mesma regra de acesso do PDF |

Query `busca` em listagem: filtra por nome/cidade do cliente.

### Detalhe da coleta — resposta

`GET /coletas/{id}` mantém os blocos operacionais `coleta`, `snapshot`, `itens`,
`evidencias` e `tratamentos`. Para consumo direto no app, também retorna:

- `itens[].quantidade_label`: quantidade formatada (inteiro sem casas, senão
  três) e unidade em maiúsculas (ex.: `12,500 KG` ou `6 KG`);
- `itens[].nome_label`: nome truncado em 48 caracteres para cards do app;
  `itens[].nome` permanece completo;
- `resumo.titulo`, `cliente`, `status_label`, `data_hora_label`;
- `resumo.peso_total_kg` e `peso_total_label`;
- `resumo.numero_relatorio_label` e `numero_mtr_label`;
- `resumo.transportador_label`, `veiculo_label`, `motorista_label`,
  `destinador_label`, `recebimento_label` e `tratamento_label`;
- `resumo.relatorio_label`, `itens_count`, `evidencias_count`,
  `pode_imprimir`, `pode_gerar_mtr` e `pode_cancelar`;
- `coleta.assinatura_url`: rota autenticada da assinatura, ou `null` quando não há arquivo. `resumo.data_hora_label` e `resumo.recebimento_label` já saem em `dd/mm/aaaa`.

O peso total soma somente itens cuja unidade seja `kg`.

### Cancelar coleta

`POST /coletas/{id}/cancelar` marca `status=cancelada`. Se a coleta tiver MTR
ativo no SINIR, o servidor chama `cancelarManifesto` antes; falha no SINIR
impede o cancelamento local. Resposta `data.message`, `data.sinir_cancelado` e
`data.coleta` (detalhe atualizado).

### PDF do relatório

`GET /coletas/{id}/pdf` devolve `application/pdf` (attachment) gerado com
**Dompdf** a partir do mesmo template HTML do painel
(`/painel/coletas/mtr/{id}`). Requer módulo `coletas` e
`ColetaMtrHelper::podeImprimirRelatorio` (não cancelada). No app, o card da lista e o botão Compartilhar do detalhe usam a mesma ação: baixam esse PDF com o token e abrem o compartilhamento do celular.

### Comprovante 80 mm

`GET /coletas/{id}/comprovante` devolve `application/pdf` com largura de 80 mm: marca (`CompanyConfig::name()`), número do relatório, cliente, data em `dd/mm/aaaa`, resíduos e quantidades. Mesmo acesso do PDF do relatório. No app, o botão Imprimir do detalhe baixa esse arquivo com o token e abre o compartilhamento do celular, de onde sai a impressão.

### Finalizar — requisitos

- Rascunho com ao menos 1 resíduo e motorista salvos (PATCH transporte).
- **`data_recebimento` obrigatória** — data de chegada do resíduo no destinador final. Rascunho pode ficar sem data; finalização retorna erro 422 se ausente.
- Preferível salvar etapa 3 antes via `POST /coletas/{id}/relatorio` (texto e/ou fotos).
- Multipart opcional no finalizar: `evidencia_1`, `evidencia_2`, `evidencia_3` (JPEG/PNG/WebP, máx. 5 MB cada)

### PATCH transporte — campos (JSON)

Aplica **etapa 1** e/ou **etapa 4** conforme o que vier no body (não exige os dois de uma vez).

**Etapa 1 — transporte**

| Campo | Obrigatório | Notas |
|-------|-------------|-------|
| `transportadora_id` | sim (se salvar etapa 1) | FK ativa |
| `motorista_nome` | sim (se salvar etapa 1) | Nome exibido no snapshot |
| `coletor_id` | não | Se coletor ativo, atualiza `coletas.coletor_id` |
| `veiculo_id` | não | Snapshot marca/modelo/placa |
| `data_coleta` | não | `Y-m-d` |
| `hora` | não | `H:i` ou `H:i:s` |

**Etapa 4 — destinador**

| Campo | Obrigatório | Notas |
|-------|-------------|-------|
| `destinador_id` | sim (se salvar etapa 4) | FK ativa |
| `tratamento` | não | Catálogo `/catalogos/tratamentos` |
| `observacao_destinador` | não | Snapshot |
| `situacao_recebimento` | não | O app não envia. No rascunho o painel ignora este campo. O recebimento de verdade é `sinir_recebido_em`, gravado só quando o painel confirma no SINIR |
| `data_recebimento` | para finalizar | `Y-m-d`. Data de encerramento do relatório; não significa que o SINIR já recebeu |

No detalhe, `resumo.encerramento_label` é a data (`dd/mm/aaaa` ou `—`) e `resumo.sinir_recebimento_label` é `Recebido no SINIR` ou `Aguardando SINIR`. `resumo.recebimento_label` junta os dois. O app do coletor emite MTR só pelo botão da lista (`POST /coletas/{id}/mtr`), na coleta já finalizada. Não recebe no SINIR e não libera CDF.

### POST relatório (rascunho etapa 3)

`POST /coletas/{id}/relatorio` — JSON ou multipart.

- Campo `relatorio` (texto)
- Arquivos opcionais: `evidencia_1`, `evidencia_2`, `evidencia_3`
- Exige motorista. Não exige resíduo: há coleta só com relatório ou foto
- Exige relatório **ou** ao menos uma foto (nova ou já existente)
- **Não** muda status (permanece `rascunho`)
- Resposta: `itens`, `evidencias`, `fotos_recebidas`, `motorista`, `rascunho_conferido`, `coleta` (detalhe)

### POST assinatura

`POST /coletas/{id}/assinatura` — JSON `{ "assinatura": "data:image/png;base64,..." }` (aceita também `assinatura_data_url`).

### GET assinatura

`GET /coletas/{id}/assinatura` devolve os bytes da imagem, com o mesmo acesso de `GET /coletas/{id}/evidencias/{ordem}`. O app precisa enviar o token; um `Image` de rede sem o Bearer não abre o arquivo.

## API Gerador (portal cliente)

Base: `{URL}/api/v1/gerador`  
Autenticação: JWT com claim `tipo=gerador` (mesmo `API_JWT_SECRET` / TTL do coletor).

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/gerador/login` | Body: `email`, `password` (ou `senha`) |
| GET | `/gerador/me` | Perfil + `cliente_id`, `cliente_nome` |
| GET | `/gerador/coleta-solicitacoes` | Lista solicitações + resumo de cota do plano |
| POST | `/gerador/coleta-solicitacoes` | Body: `data_desejada`, `motivo` |
| POST | `/gerador/coleta-solicitacoes/{id}/cancelar` | Cancela solicitação pendente |
| GET | `/gerador/coletas` | Coletas do cliente (query `page`, `per_page`) |
| GET | `/gerador/coletas/{id}` | Detalhe (itens, snapshot, evidências). `mtr_oficial_disponivel` e `cdf_disponivel` só ficam verdadeiros depois que o admin libera o documento |
| GET | `/gerador/coletas/{id}/evidencias/{ordem}` | Imagem |
| GET | `/gerador/boletos` | Cobranças Inter do cliente |
| GET | `/gerador/boletos/{id}` | Detalhe boleto (linha digitável, PIX, `pdf_url`) |

Escopo: usuário só vê dados do `cliente_id` vinculado à credencial. Boletos emitidos pela conta Inter Well (repasse à operadora: processo futuro).

## FlutterFlow

Guia passo a passo das telas: [FLUTTERFLOW.md](FLUTTERFLOW.md)

## Segurança

- HTTPS em produção
- Token JWT stateless (sem refresh no MVP)
- Ownership: coletor só acessa coletas onde `coletor_id` = seu ID
- Admin (`is_admin=1`) bypass ownership

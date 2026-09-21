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

Resposta:
```json
{
  "kpis": {
    "coletas_mes": 12,
    "rascunhos": 2,
    "urgentes": 3,
    "atrasados": 5,
    "paradas_hoje": 8,
    "hoje": "2026-09-18"
  },
  "graficos": {
    "coletas_por_mes": {
      "labels": ["Abr/26", "Mai/26"],
      "values": [10, 12]
    }
  }
}
```

Coletor vê KPIs e gráfico filtrados por `coletor_id`; admin/gestor vê totais da operadora.

### Agendamentos

`GET /agendamentos?page=1&per_page=20&busca=` — módulo `agendamentos`

Clientes ativos ordenados por prioridade e `proxima_coleta`. Cada item inclui `plano_nome` e `saldo_plano` (soma do saldo incluso em `plano_itens` — substitui o antigo `saldo_residuo` por cliente).

Coletor: vê clientes **vinculados a alguma rota** (mesmo pool cadastral; coletor não é fixado em `rota_atribuicoes`). Ordenação por prioridade e `proxima_coleta`.

### Rota do dia

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| GET | `/rota-do-dia/paradas` | `rota_dia` | Paradas do dia (query: `data`, gestor: `coletor_id`, `rota_id`) |
| POST | `/rota-do-dia/otimizar` | `rota_dia` | Otimizar ordem (body/query: `data`, `coletor_id`, `rota_id`; body: `origin_lat`, `origin_lng`, `cliente_ids[]` opcional) |
| POST | `/rota-do-dia/salvar-ordem` | `rota_dia` | Persistir ordem manual (`ordem[]`: `cliente_id`, `ordem`; query/body: `data`, `coletor_id`) |
| POST | `/rota-do-dia/parada-status` | `rota_dia` | Status da parada (`cliente_id`, `status`: `pendente` \| `coletado` \| `pulado`; query/body: `data`, `coletor_id`) |

Resposta `GET paradas`: `paradas`, `coletor_id`, `data`, `total`, `rota_id`, `sem_rota` (`true` se a operadora não tem clientes em rotas cadastrais).

Coletor **não** é definido na rota: `coletor_id` na query identifica quem opera (ordem/status do dia); paradas vêm do cadastro rota×cliente + filtros urgentes/vencidos. Nova coleta: `POST /coletas` / fluxo rascunho grava `coletas.coletor_id`.

Resposta `otimizar`: `paradas`, `polyline` (encoded), `distancia_metros`, `duracao_segundos`.

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

### Coletas

| Método | Endpoint | Módulo | Ação |
|--------|----------|--------|------|
| GET | `/coletas?busca=&status=&page=&per_page=` | `coletas` | Listar (coletor vê só as suas) |
| POST | `/coletas` | `coleta_nova` | Iniciar rascunho `{ "cliente_id": 1 }` |
| GET | `/coletas/{id}` | `coletas` | Detalhe completo (wizard) |
| PATCH | `/coletas/{id}/transporte` | `coleta_nova` | Salvar transporte/destinador |
| POST | `/coletas/{id}/itens` | `coleta_nova` | Adicionar resíduo |
| DELETE | `/coletas/{id}/itens/{itemId}` | `coleta_nova` | Remover resíduo |
| POST | `/coletas/{id}/finalizar` | `coleta_nova` | Finalizar (multipart, fotos opcionais). **Requer `data_recebimento` preenchida** (PATCH transporte antes). |
| POST | `/coletas/{id}/cancelar` | `coleta_nova` | Cancelar rascunho |
| GET | `/coletas/{id}/evidencias/{ordem}` | `coletas` | Imagem da evidência |

Query `busca` em listagem: filtra por nome/cidade do cliente.

### Finalizar — requisitos

- Rascunho com ao menos 1 resíduo e motorista salvos (PATCH transporte).
- **`data_recebimento` obrigatória** — data de chegada do resíduo no destinador final. Rascunho pode ficar sem data; finalização retorna erro 422 se ausente.
- Multipart opcional: `evidencia_1`, `evidencia_2`, `evidencia_3` (JPEG/PNG/WebP, máx. 5 MB cada)

### PATCH transporte — campos (JSON)

Espelha o wizard web: `veiculo_id`, `relatorio`, `tratamento`, `situacao_recebimento`, `data_recebimento`, `transportador_nome`, `transportador_cnpj`, `motorista_nome`, `destinador_*`, etc.

## API Gerador (portal cliente)

Base: `{URL}/api/v1/gerador`  
Autenticação: JWT com claim `tipo=gerador` (mesmo `API_JWT_SECRET` / TTL do coletor).

| Método | Rota | Descrição |
|--------|------|-----------|
| POST | `/gerador/login` | Body: `email`, `password` (ou `senha`) |
| GET | `/gerador/me` | Perfil + `cliente_id`, `cliente_nome` |
| GET | `/gerador/coletas` | Coletas do cliente (query `page`, `per_page`) |
| GET | `/gerador/coletas/{id}` | Detalhe (itens, snapshot, evidências) |
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

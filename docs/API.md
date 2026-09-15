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

Resposta `user` inclui: `modulos[]`, `modulos_csv` (slugs separados por vírgula, útil no FlutterFlow), `is_admin`, `funcao_nome`.

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
    "atrasados": 5
  }
}
```

Coletor vê KPIs filtrados por `coletor_id`; admin vê totais.

### Agendamentos

`GET /agendamentos?page=1&per_page=20&busca=` — módulo `agendamentos`

Clientes ativos ordenados por prioridade e `proxima_coleta`. Cada item inclui `plano_nome` e `saldo_plano` (soma do saldo incluso em `plano_itens` — substitui o antigo `saldo_residuo` por cliente).

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
| POST | `/coletas/{id}/finalizar` | `coleta_nova` | Finalizar (multipart, fotos opcionais) |
| POST | `/coletas/{id}/cancelar` | `coleta_nova` | Cancelar rascunho |
| GET | `/coletas/{id}/evidencias/{ordem}` | `coletas` | Imagem da evidência |

Query `busca` em listagem: filtra por nome/cidade do cliente.

### Finalizar — campos multipart

- `evidencia_1`, `evidencia_2`, `evidencia_3` (JPEG/PNG/WebP, máx. 5 MB cada)

### PATCH transporte — campos (JSON)

Espelha o wizard web: `veiculo_id`, `relatorio`, `tratamento`, `situacao_recebimento`, `data_recebimento`, `transportador_nome`, `transportador_cnpj`, `motorista_nome`, `destinador_*`, etc.

## FlutterFlow

Guia passo a passo das telas: [FLUTTERFLOW.md](FLUTTERFLOW.md)

## Segurança

- HTTPS em produção
- Token JWT stateless (sem refresh no MVP)
- Ownership: coletor só acessa coletas onde `coletor_id` = seu ID
- Admin (`is_admin=1`) bypass ownership

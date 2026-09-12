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

## Endpoints

### Saúde

`GET /health` — sem autenticação

### Autenticação

`POST /auth/login`
```json
{ "email": "coletor@well.eco", "password": "senha" }
```
Resposta: `{ token, expires_at, user }`  
Requer módulo `coleta_nova` na função do usuário.

`GET /auth/me` — usuário autenticado

### Clientes para coleta

`GET /clientes/coleta?page=1&per_page=15&busca=&prioridade=&escopo=pendentes`

- `escopo`: `pendentes` (default) ou `todos`
- Mesma regra de rotas do painel web (`ColetaService::clientesParaColetaPaginado`)

### Catálogos

| Endpoint | Conteúdo |
|----------|----------|
| `GET /catalogos/veiculos` | Veículos ativos |
| `GET /catalogos/tipos-residuos` | Tipos de resíduo ativos |
| `GET /catalogos/tratamentos` | Lista de tratamentos |

### Coletas

| Método | Endpoint | Ação |
|--------|----------|------|
| GET | `/coletas` | Listar (coletor vê só as suas) |
| POST | `/coletas` | Iniciar rascunho `{ "cliente_id": 1 }` |
| GET | `/coletas/{id}` | Detalhe completo (wizard) |
| PATCH | `/coletas/{id}/transporte` | Salvar transporte/destinador |
| POST | `/coletas/{id}/itens` | Adicionar resíduo |
| DELETE | `/coletas/{id}/itens/{itemId}` | Remover resíduo |
| POST | `/coletas/{id}/finalizar` | Finalizar (multipart, fotos opcionais) |
| POST | `/coletas/{id}/cancelar` | Cancelar rascunho |
| GET | `/coletas/{id}/evidencias/{ordem}` | Imagem da evidência |

### Finalizar — campos multipart

- `evidencia_1`, `evidencia_2`, `evidencia_3` (JPEG/PNG/WebP, máx. 5 MB cada)

### PATCH transporte — campos (JSON)

Espelha o wizard web: `veiculo_id`, `relatorio`, `tratamento`, `situacao_recebimento`, `data_recebimento`, `transportador_nome`, `transportador_cnpj`, `motorista_nome`, `destinador_*`, etc.

## FlutterFlow

Guia passo a passo das telas: [FLUTTERFLOW.md](FLUTTERFLOW.md)

**Já no projeto `well-coletas-by2777`:**
- App State: `authToken`, `userName`, `userId`, `apiBaseUrl`, `activeColetaId`
- API Calls: Login, Auth Me, Clientes Coleta, catálogos (veículos, tipos, tratamentos), coletas CRUD completo (iniciar, detalhe, transporte, itens, finalizar, cancelar)

1. Passar `baseUrl` = App State `apiBaseUrl` em cada call
2. Passar `authToken` = App State `authToken` nas calls autenticadas
3. Login → salvar response fields no App State → navegar para Home

## Segurança

- HTTPS em produção
- Token JWT stateless (sem refresh no MVP)
- Ownership: coletor só acessa coletas onde `coletor_id` = seu ID
- Admin (`is_admin=1`) bypass ownership

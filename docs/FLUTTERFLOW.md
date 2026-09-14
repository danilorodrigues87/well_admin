# FlutterFlow — App Coletor Well Coletas

Projeto: **well-coletas-by2777** (`Well Coletas`)

## Já configurado via API/MCP

### App State (persistido)

| Variável | Tipo | Uso |
|----------|------|-----|
| `authToken` | String | JWT após login |
| `userName` | String | Nome do coletor |
| `userId` | Integer | ID do usuário |
| `apiBaseUrl` | String | Base da API (default **produção**) |
| `activeColetaId` | Integer | ID da coleta em andamento (wizard) |
| `isAdmin` | Boolean | Flag admin (`$.data.user.is_admin` no login) |
| `funcaoNome` | String | Nome da função do usuário |
| `userModulesCsv` | String | Slugs RBAC separados por vírgula (ex.: `dashboard,coletas,coleta_nova`) |

### API Calls criadas

| Nome | Método | URL |
|------|--------|-----|
| **WellAdmin Login** | POST | `[baseUrl]/auth/login` |
| **WellAdmin Auth Me** | GET | `[baseUrl]/auth/me` |
| **WellAdmin Clientes Coleta** | GET | `[baseUrl]/clientes/coleta?...` |
| **WellAdmin Catalogo Veiculos** | GET | `[baseUrl]/catalogos/veiculos` |
| **WellAdmin Catalogo Tipos Residuos** | GET | `[baseUrl]/catalogos/tipos-residuos` |
| **WellAdmin Catalogo Tratamentos** | GET | `[baseUrl]/catalogos/tratamentos` |
| **WellAdmin Coletas Listar** | GET | `[baseUrl]/coletas?...` |
| **WellAdmin Coleta Iniciar** | POST | `[baseUrl]/coletas` → `coletaId` |
| **WellAdmin Coleta Detalhe** | GET | `[baseUrl]/coletas/[coletaId]` |
| **WellAdmin Coleta Transporte** | PATCH | `[baseUrl]/coletas/[coletaId]/transporte` |
| **WellAdmin Coleta Add Item** | POST | `[baseUrl]/coletas/[coletaId]/itens` |
| **WellAdmin Coleta Remove Item** | DELETE | `[baseUrl]/coletas/[coletaId]/itens/[itemId]` |
| **WellAdmin Coleta Finalizar** | POST | `[baseUrl]/coletas/[coletaId]/finalizar` → `numeroMtr` |
| **WellAdmin Coleta Cancelar** | POST | `[baseUrl]/coletas/[coletaId]/cancelar` |
| **WellAdmin Dashboard Resumo** | GET | `[baseUrl]/dashboard/resumo` |
| **WellAdmin Agendamentos** | GET | `[baseUrl]/agendamentos?page=&per_page=&busca=` |
| **WellAdmin Perfil** | GET | `[baseUrl]/perfil` |
| **WellAdmin Perfil Senha** | POST | `[baseUrl]/perfil/senha` |

Todas usam variável `baseUrl` → mapear para **App State `apiBaseUrl`** em cada chamada.

**Coletas Listar** inclui query `busca` além de `status`, `page`, `per_page`.

Contratos completos: [API.md](API.md)

---

## Sprint 2.1 — Telas Login + Splash + Home ✅ (via MCP)

**Página inicial:** `SplashPage` (`Scaffold_splsh01`)

| Página | Função |
|--------|--------|
| **SplashPage** | Logo + loading; redireciona para Home (com token) ou Login |
| **LoginPage** | E-mail/senha → API Login → salva App State (incl. RBAC) → Home |
| **HomePage** | Menu: Lançar coleta, Minhas coletas, Dashboard, Agendamentos, Perfil, Sair |
| **ClientesColetaPage** | ListView API clientes + filtros + iniciar coleta → wizard |
| **ColetaWizardPage** | Mínima: carrega detalhe da coleta (cliente + status) |
| **ColetasListPage** | Stub: título (ligar API + filtros — Sprint 2.2) |
| **DashboardPage** | Stub (ligar **WellAdmin Dashboard Resumo**) |
| **AgendamentosPage** | Stub (ligar **WellAdmin Agendamentos**) |
| **PerfilPage** | Stub (ligar **WellAdmin Perfil** + senha) |

### O que já está ligado

- **SplashPage** — `On Init State`: se `authToken` preenchido → Home, senão → Login
- **LoginPage** — botão Entrar → **WellAdmin Login** → grava App State (token, user, RBAC) → Home
- **HomePage** — navegação para as páginas acima; logout limpa sessão → Login

### Sprint 2.2 — Clientes + Wizard ✅ (via MCP)

**ClientesColetaPage**

- Filtros **Pendentes** / **Todos** (page state `escopo`)
- Campo **Buscar** → atualiza page state `busca`
- **ListView** com Backend Query **WellAdmin Clientes Coleta** (`busca`, `escopo`, paginação)
- Tap no cliente → **WellAdmin Coleta Criar** → grava `activeColetaId` → **ColetaWizardPage**

**ColetaWizardPage**

- `On Page Load` → **WellAdmin Coleta Detalhe** (`activeColetaId`) → exibe cliente e status

**Correção API:** `WellAdmin Coleta Criar` — JSON Path `coletaId` = `$.data.coleta.id`

#### Passo manual no editor (ListView — obrigatório)

A API MCP **não consegue** gravar *Generate Children from Variable* no ListView. Se a lista aparecer vazia no Test/Run:

1. Abra **ClientesColetaPage** → widget **ListView**
2. Backend Query já deve estar em **WellAdmin Clientes Coleta**
3. **Generate Children from Variable**:
   - Source: resultado da query (API Response)
   - **JSON Body** → JSON Path: `$.data.items`
4. Salve e teste de novo

Os textos **ItemNome** / **ItemCidade** já usam `GENERATOR_VARIABLE` com `$.nome_fantasia` e `$.cidade`.

### RBAC no editor (visibilidade condicional)

Use App State `userModulesCsv` com função **Contains**:
- Botão "Lançar coleta" → contém `coleta_nova`
- "Minhas coletas" → contém `coletas`
- "Dashboard" → contém `dashboard`
- "Agendamentos" → contém `agendamentos`
- "Perfil" → contém `perfil`

Admin (`isAdmin=true`) normalmente já tem todos os slugs no CSV após login.

### Login — configurar no editor (obrigatório)

A automação via MCP **não consegue** gravar Predefined Paths nem Custom Actions de forma estável. Siga no FlutterFlow:

**1. Apagar lixo criado pelo MCP (se existir erros de Custom Action):**

- **Custom Code → Custom Actions** → apague `wellAdminLogin`
- **Custom Data Types** → apague `WellLoginResult` (se existir)

**2. Botão Entrar → Action Flow:**

| # | Action | Configuração |
|---|--------|--------------|
| 1 | **WellAdmin Login** | Output = `loginResult`; vars: `baseUrl`←App State, email/senha←TextFields |
| 2 | **Conditional** | Ver opções A ou B abaixo |
| 3a (TRUE) | **Update App State** | Ver opções A ou B abaixo |
| 3b (TRUE) | **Navigate** | HomePage |
| 4 (FALSE) | **SnackBar** | "Login inválido" |

**3. Test API no painel:** com o admin em `https://admin.well.eco.br`, o **Test API** do FlutterFlow deve funcionar (proxy alcança URL pública). Para dev local, veja seção 5.

#### Se **Predefined Path → authToken** não aparece no dropdown

Isso é comum quando a API foi criada via MCP. Use **JSON Path customizado** (funciona igual):

**Opção A — Conditional (recomendada):**

1. Action Output → `loginResult` (da action **WellAdmin Login** do passo 1)
2. **API Response Options** → **JSON Body**
3. Em vez de *Predefined Path*, escolha **JSON Path** (custom)
4. Digite: `$.data.token`
5. Condição: **Is Set and Not Empty** (⚠️ **não** use "Is Not Set" — isso inverte a lógica e deixa entrar com senha errada)

**Opção B — Conditional mais simples:**

1. Na condição, procure **API Call Succeeded** / **Response Succeeded** (condição nativa da action de API)
2. Não precisa de JSON Path

**Update App State (TRUE branch)** — para cada campo use **JSON Path custom**:

| App State | Action Output | JSON Body → JSON Path |
|-----------|---------------|------------------------|
| `authToken` | `loginResult` | `$.data.token` |
| `userName` | `loginResult` | `$.data.user.nome` |
| `userId` | `loginResult` | `$.data.user.id` |
| `isAdmin` | `loginResult` | `$.data.user.is_admin` |
| `funcaoNome` | `loginResult` | `$.data.user.funcao_nome` |
| `userModulesCsv` | `loginResult` | `$.data.user.modulos_csv` |

**Forçar Predefined Paths a aparecer (opcional):**

1. **API Calls → WellAdmin Login**
2. Aba **JSON Paths** — confirme `authToken`, `userName`, `userId`
3. **Add** de novo se faltar; clique **Save**
4. Feche e reabra o Action Flow do botão Entrar

### Ajustes opcionais no editor

- Adicionar logo/imagem da marca no lugar do ícone recycling
- Campo `isLoading` no login (spinner no botão)
- **HomePage On Page Load** → **WellAdmin Auth Me** (validar token expirado)
- Trocar snackbar "em breve" por navegação à `ClientesColetaPage` (Sprint 2.2)

### 5. URL da API — dev vs produção

**Default atual (App State + Test API de todos os endpoints):** `https://admin.well.eco.br/api/v1`

| Ambiente | `apiBaseUrl` |
|----------|----------------|
| **Produção (padrão)** | `https://admin.well.eco.br/api/v1` |
| **Test API** no painel FlutterFlow | Usa a URL de produção acima (proxy FF alcança HTTPS público) |
| XAMPP no PC (Test Mode **web** no browser) | `http://localhost/pjt/admin.well.eco/api/v1` |
| Celular/PDA na mesma rede Wi‑Fi | `http://SEU_IP_LAN/pjt/admin.well.eco/api/v1` |

No PDA/celular **não use `localhost`** — use o IP da máquina (ex.: `192.168.1.10`).

### 6. Por que o **Test API** do FlutterFlow retorna `null` com `localhost`

O botão **Test API** (em API Calls → WellAdmin Login) **não roda no seu PC**. O FlutterFlow usa um proxy nos servidores deles. Quando você coloca `http://localhost/...`, o proxy tenta conectar em `127.0.0.1:80` **na máquina do FlutterFlow**, não no seu XAMPP.

Erro típico no Raw Body:

```text
Not found because of proxy error: Error: connect ECONNREFUSED 127.0.0.1:80
```

Isso **não é bug da API Well Eco** — a requisição nem chega ao PHP. Confirme no PC com:

```powershell
curl.exe -s http://localhost/pjt/admin.well.eco/api/v1/health
```

**Como testar de verdade:**

| O que você quer testar | Solução |
|------------------------|---------|
| Painel **Test API** no FlutterFlow | Expor o XAMPP com túnel (ngrok, Cloudflare Tunnel) e usar a URL pública em `baseUrl` |
| App no **Test Mode → Web** (browser) | `localhost` funciona — o browser roda no seu PC |
| App no **celular/emulador** | IP LAN da máquina (`ipconfig` → IPv4), mesma rede Wi‑Fi |

**Exemplo com ngrok (Test API no painel FF):**

1. Instale [ngrok](https://ngrok.com/) e rode: `ngrok http 80`
2. Copie a URL HTTPS (ex.: `https://abc123.ngrok-free.app`)
3. No Test API, use: `https://abc123.ngrok-free.app/pjt/admin.well.eco/api/v1`
4. Atualize também App State `apiBaseUrl` enquanto estiver usando o túnel

CORS: com `API_CORS_ORIGINS=*` no `.env` (padrão), ngrok funciona sem ajuste extra.

---

## Sprint 2.2 — ClientesColetaPage (fazer no editor)

### 1. Criar página `ClientesColetaPage`

**Page State:**
- `busca` (String)
- `escopo` (String, default `pendentes`)
- `page` (Integer, default 1)
- `clientesJson` (JSON) — resposta bruta da API
- `isLoading` (Boolean)

**Layout:**
- AppBar: "Selecionar cliente" + botão voltar
- TextField de busca (debounce opcional) → atualiza `busca` e recarrega
- Toggle/chips: `pendentes` | `todos` → atualiza `escopo`
- **ListView** com dados de `clientesJson`

**On Page Load:**
1. Backend Call **WellAdmin Clientes Coleta**
   - `baseUrl` = App State `apiBaseUrl`
   - `authToken` = App State `authToken`
   - `page` = Page State `page`
   - `per_page` = 20
   - `busca` = Page State `busca`
   - `escopo` = Page State `escopo`
2. Update Page State `clientesJson` = API Response Body

**ListView item** (JSON Path por item em `$.data.items`):
- Título: `nome_fantasia`
- Subtítulo: `cidade` + `uf` + prioridade (badge se `prioridade` > 0)
- Tap → fluxo abaixo

### 2. Tap no cliente — iniciar coleta

1. Backend Call **WellAdmin Coleta Iniciar**
   - `cliente_id` = item.id
   - `baseUrl`, `authToken` como sempre
2. Update App State `activeColetaId` = response `coletaId`
3. Navigate → `ColetaWizardPage` (Sprint 2.3)

### 3. Teste rápido no painel API

Antes de ligar à UI, teste **WellAdmin Coleta Iniciar** com um `cliente_id` válido e token de coletor.

> **Atenção:** o **Test API** do painel FlutterFlow **não alcança `localhost`**. Use túnel (ngrok) ou teste pelo **Test Mode Web** no browser. Ver seção 6 acima.

> **Fotos na finalização:** o endpoint **WellAdmin Coleta Finalizar** está sem multipart por enquanto (MVP sem fotos). Para evidências, configure multipart manualmente no editor: campos `evidencia_1`, `evidencia_2`, `evidencia_3`.

---

## Sprint 2.3 — ColetaWizardPage (próximo)

Wizard com 3 abas (TabBar), espelhando o painel web:

| Aba | API | Campos principais |
|-----|-----|-------------------|
| Transporte | **WellAdmin Coleta Transporte** | veículo, transportador, motorista, destinador |
| Resíduos | **WellAdmin Coleta Add/Remove Item** + catálogo tipos | tipo, quantidade, unidade |
| Finalizar | **WellAdmin Coleta Finalizar** ou **Cancelar** | resumo + confirmar |

On Page Load: **WellAdmin Coleta Detalhe** com `coletaId` = App State `activeColetaId`.
Catálogos: **Veiculos**, **Tipos Residuos**, **Tratamentos** nas dropdowns.

---

## Checklist de teste

- [ ] Splash redireciona para Login sem token
- [ ] Login com usuário **Coletor** (função com módulo Lançar Coleta)
- [ ] Token salvo — reabrir app vai direto para Home
- [ ] Logout limpa sessão e volta ao Login
- [ ] Home lista clientes (Sprint 2.2)
- [ ] Coleta finalizada aparece no painel web `/painel/coletas`

## Usuário de teste

Use um funcionário com função **Coletor** cadastrado em `/painel/funcionarios` (não precisa ser admin).

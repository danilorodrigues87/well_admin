# Dashboard — conexões básicas (layout no FF Design)

Você refaz a tela no FlutterFlow (colar do FF Design). Este doc cobre só **API + dados**, sem componentes customizados.

## Pré-requisito backend

- Deploy do PHP atual no VPS (`GET /dashboard/resumo` schema v2).
- Teste: `Authorization: Bearer <token>` em `https://admin.well.eco.br/api/v1/dashboard/resumo`.

## API Call no FlutterFlow

Criar ou conferir **WellAdmin Dashboard Resumo**:

| Campo | Valor |
|-------|--------|
| Método | GET |
| URL | `[baseUrl]/dashboard/resumo` |
| Header | `Authorization: Bearer [authToken]` |
| Variáveis | `baseUrl` ← App State `apiBaseUrl`, `authToken` ← App State `authToken` |

Referência local: `storage/ff-yaml/api-wdash1.yaml`.

## On Page Load (mínimo)

1. **API Call** WellAdmin Dashboard Resumo → output `dashboardResult`.
2. (Opcional) **Update Page State** ou textos estáticos ligados ao output — ver JSON paths abaixo.

Não use **Expanded** dentro de Column scrollable nos KPIs (quebra layout no Flutter).

## JSON paths úteis (`success: true`)

| UI simples | JSON Path | Tipo |
|------------|-----------|------|
| Finalizadas hoje | `$.data.kpis.coletas_hoje` | int |
| Rascunhos | `$.data.kpis.rascunhos` | int |
| Urgentes (rota) | `$.data.kpis.urgentes` | int |
| Paradas hoje | `$.data.kpis.paradas_hoje` | int |
| Gráfico barras — labels | `$.data.graficos.coletas_por_mes.labels` | list string |
| Gráfico barras — valores | `$.data.graficos.coletas_por_mes.values` | list double |
| Lista dinâmica de cards | `$.data.cards` | list (campos: `label`, `value`, `hint`) |
| Atalhos (chips) | `$.data.atalhos` | list (`label`, `slug`, `target`) |
| Atividades | `$.data.atividades` | list (`titulo`, `subtitulo`, `data_hora`, `ref_id`) |

Contrato completo: [API.md](API.md) seção Dashboard.

## Header (sem componente)

Ligar textos do topo ao **App State** do login:

- Nome: `userName`
- Função: `funcaoNome`

## Navegação pós-login

Splash / Login → **Dashboard** (não HomePage legada).

## RBAC (depois)

Esconder chips com `userModulesCsv` Contains `coleta_nova`, `coletas`, `agendamentos`, `rota_dia`, etc.

Matriz geral: [FLUTTERFLOW_APP_MATRIX.md](FLUTTERFLOW_APP_MATRIX.md).

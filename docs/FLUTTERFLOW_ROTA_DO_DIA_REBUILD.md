# RouteOfTheDay — estado atual (`Scaffold_74bj9gpe`)

## O que foi ajustado (2026-10-03)

| Item | Status |
|------|--------|
| **Otimizar** abre Google Maps sozinho | **Removido** — só atualiza lista/`mapPoints`/`encodedPolyline` + snackbar |
| Marcadores no mapa nativo | Bind `mapPoints` + `valueKey` (remonta ao carregar) + centro no 1º ponto |
| Polyline no mapa | Custom Widget **`RotaMapPolyline`** criado (ver § abaixo) — **colocar na página pelo editor** |
| Botões da ListView | Já configurados (ver tabela) |

## Marcadores (mapa nativo — já na página)

O Google Map nativo continua no slot do mapa com:

- **Markers LatLng** → Page State `mapPoints`
- **valueKey** baseado em `mapPoints.length` (força rebuild após o load)
- **Centro inicial** → 1º ponto de `mapPoints` (fallback Cascavel)
- Interação ligada (`allowInteraction: true`)

Se ainda não aparecer pino: cliente sem lat/lng na API (badge “Sem GPS” no web).

## Custom Widget `RotaMapPolyline` (linha da rota)

Criado em Custom Code (`rotmap1`): desenha **marcadores + polyline** verde após otimizar.

**No editor FlutterFlow (obrigatório — MCP não grava o type do nó Custom):**

1. Custom Code → compile **RotaMapPolyline** (adicione dependency `google_maps_flutter` se pedir)
2. Na página RouteOfTheDay → no container **Map** (altura 240), **substitua** o Google Map nativo pelo widget **RotaMapPolyline**
3. Bind:
   - `markers` → Page State **`mapPoints`**
   - `encodedPolyline` → Page State **`encodedPolyline`**
   - width = Infinity / height = 240

Depois de **Otimizar**, a API grava a polyline em `encodedPolyline` e a linha aparece no custom map.

## Botões da ListView (por cliente)

| Botão | Ícone | Ação | Status |
|-------|-------|------|--------|
| Directions | `directions_rounded` | `openRotaDirections(maps_url)` da parada | **OK** |
| Coletado | `add_task_rounded` (verde) | `POST parada-status` status=`coletado` → reload paradas/`mapPoints` | **OK** |
| Pulado | `block_rounded` | `POST parada-status` status=`pulado` → reload | **OK** |
| Coletar / nova coleta | — | Não existe no app (não há página wizard NewCollection) | **Pendente** (só no web) |

Ícone **my_location** sobre o mapa (`IconButton_14qhfy43`): **sem ação** ainda.  
Ícone **directions** verde sobre o mapa: abre Google Maps com **toda** a sequência (`buildRotaDirUrl`).

## Fluxo

1. On Load → rotas → 1ª rota → paradas → `paradaItems` + `mapPoints`
2. DropDown + refresh → troca clientes
3. **Otimizar** → API → reordena lista/markers + salva `encodedPolyline` (**não** abre Maps)
4. Botão verde do mapa → Google Maps (navegação)
5. Directions na linha → Maps daquela parada

## API

- `GET /rota-do-dia/paradas` exige `rota_id` > 0
- `POST /rota-do-dia/otimizar` → `paradas`, `polyline`, `maps_dir_url`
- `POST /rota-do-dia/parada-status`

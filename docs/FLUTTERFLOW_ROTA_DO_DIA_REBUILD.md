# RouteOfTheDay — estado atual (`Scaffold_74bj9gpe`)

Alinhado ao painel web (`rota-mapa.js`): selecionar rota → carregar paradas → markers a partir de lat/lng.

## Já ligado (MCP) — 2026-10-02

| Item | Detalhe |
|------|---------|
| Page State | `rotaDataIso`, `rotaId`, `totalParadas`, `paradaItems`, `rotaNomes`, `rotaIds`, **`mapPoints`** (`List<LatLng>`) |
| Custom Functions | `parseRotaParadasFromApi`, `parseRotaNomesFromApi`, `parseRotaIdsFromApi`, `firstRotaIdFromApi`, `paradaItemsToLatLngs`, `buildRotaDirUrl` |
| On Page Load | hoje → rotas → **1ª rota** (`firstRotaIdFromApi`) → paradas → `paradaItems` + `mapPoints` |
| Date picker | ISO + reload rotas/paradas + mapPoints |
| **Seleção de rota** | **Lista horizontal de chips** (`ListView_rotarow1`) — tap no chip → `rotaId` + API paradas + lista/mapPoints (substitui DropDown; MCP não grava `ON_SELECTED`) |
| Refresh | `IconButton_rotapl1` recarrega paradas da `rotaId` atual |
| Otimizar | GPS → API → reload lista/mapPoints |
| Lista | Generate from `paradaItems` |
| Status ✓ / block | API `parada-status` → reload |
| Google Maps keys | android / ios / web |
| Centro do mapa | Cascavel (−24.9555, −53.4552), `markerType: LAT_LNG` |

## Manual no editor (marcadores — MCP não expõe o bind)

O widget nativo GoogleMap **não aceita** no YAML o campo de lista de markers (`placesValue`, `markerLatLngs`, etc. → `Unknown field name`). O Page State **`mapPoints` já é preenchido** em todo reload — falta ligar no UI:

1. Seleciona **Google Map** → Properties → **Num Markers: Multiple** → **Marker Type: LatLng**
2. **Markers LatLng** → From Variable → Page State **`mapPoints`**

Sem isso o mapa fica só com tiles (sem pinos).

### Traçar rota (polyline / Directions)

| Ação | Como |
|------|------|
| Abrir rota completa no Google Maps | Launch URL → Custom Function **`buildRotaDirUrl`**(`paradaItems`) |
| Navegar 1 parada | Icon `directions` → `paradaItem.maps_url` |

(O MCP só aceita `launchUrl.url` literal — bind dinâmico no editor.)

## API

- `GET /rota-do-dia/paradas` **exige** `rota_id` > 0 (igual ao web). Sem rota → `paradas: []`.
- `GET /rota-do-dia/rotas`
- `POST /rota-do-dia/otimizar`
- `POST /rota-do-dia/parada-status`

## Como testar

1. Hot restart no Test Mode / app.
2. Abrir Rota do Dia → chips das rotas do dia devem aparecer; a 1ª já carrega paradas.
3. Tocar outro chip → lista de clientes deve trocar na hora.
4. Após bind manual de `mapPoints` no Google Map → pinos nas paradas com lat/lng.

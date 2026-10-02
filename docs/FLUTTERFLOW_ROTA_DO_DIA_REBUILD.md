# RouteOfTheDay — estado atual (`Scaffold_74bj9gpe`)

## Marcadores + traçar rota (obrigatório no editor)

O MCP **não grava** o bind de markers do GoogleMap nativo (`placesValue` / `markerLatLngs` → rejeitados). O Page State **`mapPoints`** já é preenchido em todo reload.

### 1) Ligar pinos (2 cliques)

1. Seleciona **Google Map** (`Map Google Map`)
2. Properties → **Num Markers: Multiple** → **Marker Type: LatLng**
3. **Markers LatLng** → From Variable → Page State **`mapPoints`**

Sem isso o mapa fica só com tiles (Cascavel).

`allowInteraction` está **false** de propósito: no Test Mode web o mapa rouba todos os cliques. No app mobile você pode religar depois.

### 2) Traçar rota (já ligado)

| Botão | Ação |
|-------|------|
| Ícone **directions** (verde, sobre o mapa) | Custom Action `openRotaDirections` + `buildRotaDirUrl(paradaItems)` → abre Google Maps com a sequência de paradas |
| Ícone **directions** na linha da parada | Abre `maps_url` daquela parada |
| **Otimizar** | GPS (fallback Cascavel) → API → atualiza lista/`mapPoints` → snackbar → **abre Google Maps** com `maps_dir_url` (ou `buildRotaDirUrl`) |
| Ícone **directions** (verde) | Abre Google Maps com a sequência atual de paradas |

> O widget Google Map nativo do FlutterFlow **não desenha polyline**. A “rota”
> operacional é o trajeto no Google Maps (igual ao botão navegar do painel web).
> Após Otimizar, o app deve abrir o Maps automaticamente.

Polyline desenhada **dentro** do widget GoogleMap do FF não existe sem Custom Widget — o equivalente operacional é abrir no Google Maps (como “navegar” no painel web).

## Fluxo alinhado ao painel web

1. On Load / data → rotas → 1ª rota → paradas → `paradaItems` + `mapPoints`
2. DropDown rota + botão verde refresh → troca clientes
3. Otimizar (GPS) → nova ordem
4. Directions → Google Maps com waypoints

## API

- `GET /rota-do-dia/paradas` exige `rota_id` > 0
- `POST /rota-do-dia/otimizar`
- `POST /rota-do-dia/parada-status`

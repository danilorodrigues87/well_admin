# RouteOfTheDay — estado atual (`Scaffold_74bj9gpe`)

Tela recriada. Wiring via MCP em 2026-10-02.

## Já ligado

| Item | Detalhe |
|------|---------|
| Page State | `rotaDataIso`, `rotaId`, `totalParadas`, `paradaItems` |
| Custom Function | `parseRotaParadasFromApi` (`rtpp01`) → `List<RotaParadaItem>` |
| **On Page Load** | hoje → `rotaData` → API Paradas → `paradaItems` + `totalParadas` |
| **Date picker** (ícone calendário) | Date + setFormField `d/M/y` + `rotaDataIso` `yyyy-MM-dd` + Paradas |
| **Otimizar** | lê DropDown → API Otimizar → Paradas → lista |
| **ListView** | `generatorVariable` = `paradaItems`; textos ordem/nome/endereço/status |
| Contador | `N paradas` dinâmico |
| GPS Switch | `shareGpsEnabled` + Request Location |
| Bottom nav | Início / Coletas / Rotas |
| Navegações externas | Dashboard + CollectionsList → `Scaffold_74bj9gpe` |

## Manual no editor (confirmar / completar)

1. **ListView** — se Issues acusar *Value Key* / *Generator variable*:  
   Generate Children from Variable → Page State **`paradaItems`**.  
   Bindings dos textos já apontam para `paradaItem` (GENERATOR_VARIABLE).

2. **Otimizar — GPS origem**  
   Em `origin_lat` / `origin_lng`: **Current Device Location** (Latitude / Longitude).  
   Sem isso a API responde 422. O MCP não grava LatLng.

3. **DropDown de rotas dinâmico**  
   Options from Variable ← `WellAdmin Rota Rotas` → `$.data.rotas[]`  
   Label `nome`, value `id`. Opção estática `0` = Todas já existe.

4. **Botões da parada** (mapa / coletado / pulado)  
   - Maps: Launch URL ← `paradaItem.maps_url`  
   - Status: API `WellAdmin Rota Parada Status` (`coletado` / `pulado`) → reload Paradas

5. **Header** — Text do nome ← App State `userName` (se ainda estático)

6. **Não** colocar 2× Expanded na mesma Row dos filtros.

## APIs usadas

- `GET /rota-do-dia/paradas`
- `POST /rota-do-dia/otimizar` (falta origin no editor)
- `GET /rota-do-dia/rotas` (opções do dropdown — manual)
- `POST /rota-do-dia/parada-status` (manual nos botões)

# RouteOfTheDay — MVP em uso (`Scaffold_74bj9gpe`)

## Decisão de produto (2026-10-06)

**Não** fazer navegação/GPS contínuo dentro do app.  
O Google Maps externo é o navegador do motorista.

### Mínimo em uso
| Recurso | Como |
|---------|------|
| Pinos no mapa | GoogleMap **nativo** FF + `mapPoints` |
| Sequência | Lista com **`N. Nome`** (`ordem`) |
| Traçar rota / ajustar paradas | Botão **AbrirRotaMaps** → Google Maps app (`buildRotaDirUrl`) |
| Origem da rota | `collectorLat`/`collectorLng` (botão my_location) se preenchidos; senão 1ª parada |
| Otimizar / status / Coletar | Já existentes (API PHP) |

### Fora do MVP (não bloquear uso)
- Custom Widget `google_maps_flutter` (quebra APK no builder FF)
- Polyline interativa no app (usar Maps externo; Static Map opcional depois)
- Tracking GPS contínuo no mapa do app

## Dependências
**Sem** Custom Pub `google_maps_flutter`.  
Custom Widget `RotaMapPolyline` permanece como stub (não usar na página).

## Validação
1. Gerar APK (sem dep Maps custom).
2. Carregar rota → ver pinos + lista `1. … 2. …`.
3. my_location (opcional) → Abrir rota no Maps → origem no coletor + waypoints.
4. Otimizar / Coletar / status como já testado.

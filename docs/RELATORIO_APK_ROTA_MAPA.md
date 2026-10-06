# Relatório técnico — Rota do Dia / mapa / falha de APK (FlutterFlow)

**Data:** 2026-10-06  
**Projeto FlutterFlow:** `well-coletas-by2777`  
**Página:** `RouteOfTheDay` (`Scaffold_74bj9gpe`)  
**Backend:** Well Admin PHP (`admin.well.eco`) — API Rota do Dia já funcional  
**Objetivo deste documento:** transferir contexto completo para outra IA analisar opções de destravar **build de APK** e mapa utilizável.

---

## 1. Objetivo de produto

Na tela **Rota do Dia** do app do coletor:

1. Ver paradas da rota selecionada
2. Ver **mapa com pinos** das paradas
3. Ver **polyline** da rota otimizada (encoded polyline vinda do PHP)
4. Opcionalmente ver posição do **coletor** (GPS → marker)
5. Otimizar / reordenar / pular / marcar coletado / coletar → wizard
6. Abrir Google Maps externo (opcional) para navegação

**Regra de aceite do mapa (acordada):** Local Run / APK / dispositivo real.  
**Test Mode no browser NÃO é critério** — `google_maps_flutter` é mobile-first e falha com frequência no web.

**Bloqueio atual:** não é possível gerar **APK**. Sem APK (e sem Local Run desktop funcionando), não há como validar o mapa no celular.

---

## 2. Stack e contexto

| Camada | Tecnologia |
|--------|------------|
| App | FlutterFlow (`well-coletas-by2777`) |
| Backend | PHP 8+ XAMPP / VPS, banco `well_admin` |
| API mapa | PHP gera `encoded_polyline`, `static_map_url`, `maps_dir_url` via `GoogleMapsService` / `RotaDoDiaService` |
| Edição FF | MCP FlutterFlow + API oficial `updateProjectByYaml` |
| Ambiente do usuário | Windows 10/11; FlutterFlow Desktop instalado mas **não inicia** (abre e fecha); sem Flutter/Dart CLI local |

Documentação no repo:

- `docs/FLUTTERFLOW_ROTA_DO_DIA_REBUILD.md`
- `docs/FLUTTERFLOW_MCP_GUIA.md` (armadilhas MCP)
- `ARCHITECTURE.md` (changelog app)

---

## 3. O que já funciona (backend / app sem mapa custom)

### API PHP (ok)

- `GET /rota-do-dia/rotas`
- `GET /rota-do-dia/paradas?rota_id=`
- `POST /rota-do-dia/otimizar`
- `POST /rota-do-dia/parada-status`
- `POST /rota-do-dia/salvar-ordem`
- `POST /coletas` `{ cliente_id }` (iniciar coleta)
- Respostas incluem polyline / URLs Maps / `static_map_url` quando aplicável
- App **não** deve chamar Google Directions direto — formatação e rota vêm do PHP

### App FlutterFlow (lógica de negócio — em grande parte ok)

- On Load: carrega rotas → seleciona → paradas → Page State `paradaItems`, `mapPoints`, `encodedPolyline`
- **Otimizar:** atualiza lista + polyline + snackbar; **não** abre Maps sozinho
- Botão verde no mapa: Custom Action `openRotaDirections` → Maps externo
- GPS / permissão LOCATION → `collectorLat` / `collectorLng`
- Coletar: `POST /coletas` → navega `CollectionDetail`
- Custom Functions: `parseRotaParadasFromApi`, `paradaItemsToLatLngs`, `moveRotaParada`, etc.
- Custom Action: `salvarRotaOrdem`
- ↑/↓ na lista: funções prontas; IconButtons podem ainda precisar ligação no editor

### Google Maps keys no FF

Arquivo `google-maps` com `androidKey` / `iosKey` / `webKey` configurados.

---

## 4. Evolução do mapa (o que tentamos)

### Fase A — GoogleMap nativo do FlutterFlow

- Bind de `markers` → `mapPoints`
- `valueKey` para forçar rebuild
- Centro dinâmico no 1º ponto

**Resultado:** no **Test Mode / browser**, markers frequentemente **não aparecem** (mapa cinza ou sem pinos). Speculação: limitação web/CanvasKit do FF. Aceite real exige dispositivo.

### Fase B — Static Map (imagem)

- Page State `staticMapUrl`
- Custom Function `buildRotaStaticMapUrl`
- API passa `static_map_url`
- Widget Image na página como fallback

**Resultado:** paliativo visual no Test Mode. Não é mapa interativo (zoom/pan/markers nativos).

### Fase C — Custom Widget `RotaMapPolyline` (`rotmap1`) — estado atual

**Decisão:** concentrar mapa em um Custom Widget com `google_maps_flutter`:

- Decode de `encodedPolyline`
- Markers coloridos (início verde / meio violeta / fim laranja)
- Marker azul do coletor (`collectorLat`/`collectorLng`)
- `fitBounds` após load/update
- Parâmetro DIMENSIONS único: `width` (Infinity × height 240)

**Na página hoje:** container do mapa `Container_2r2p63ix` → filho `Container_uz29caov` = **Custom Widget `RotaMapPolyline`** com binds:

| Parâmetro | Bind |
|-----------|------|
| width/height | Infinity × 240 |
| markers | `mapPoints` |
| encodedPolyline | `encodedPolyline` |
| collectorLat / collectorLng | page state |

**Dependência Custom Pub:**

```yaml
pubspecDependencies:
  - name: google_maps_flutter
    version: 2.12.2   # pin exato (sem ^)
```

Histórico de versões tentadas na dep:

1. `^2.9.0` — Issues reclamou desatualizado; `^` pode resolver até 2.18+
2. `2.5.3` (exata) — tentativa de evitar Kotlin metadata novo demais
3. `2.12.2` (exata) — versão que o painel Issues do FF pediu (“upgrade to 2.12.2”)

**Código atual:** import `google_maps_flutter as gmaps`; usa `zIndexInt` (API 2.12); sem `import material` no corpo (FF injeta boilerplate); tipo `List<LatLng>?` é o LatLng do FlutterFlow (boilerplate).

---

## 5. Problemas encontrados (cronologia)

### 5.1 Height do Custom Widget

- Erros: “Height not specified” / “parameter height not set properly”
- Causa: parâmetro `height` separado como WidgetProperty DIMENSIONS (errado)
- Fix: um único parâmetro DIMENSIONS (`width`) com width=Infinity, height=240 + `enforceCustomWidgetDimensions: true`

### 5.2 Test Mode session

- `test_mode_codegen exit=2` / `HTTP 410 Session is deleted`
- Irrelevante para mapa; sessão Test Mode morta

### 5.3 Analyzer Errors no Custom Widget

1. `uri_does_not_exist: google_maps_flutter` → dep ausente; adicionada em Custom Pub Dependencies
2. `unchecked_use_of_nullable_value` em `.position` → reescrito com loop explícito
3. Aviso `duplicate_import` de `material.dart` → FF já injeta; import duplicado removido do nosso código
4. Issues: “Custom widgets have not been compiled” → usuário deve clicar ✓ Compile; aviso permanece se não compilar

### 5.4 FlutterFlow Desktop (Local Run)

- Usuário baixou `flutterflow-latest-windows-setup`
- App **não inicia** (tenta abrir e fecha)
- Sem Local Run, só restam: Test Mode (ruim para mapa) e **APK cloud**

### 5.5 APK Build Failed (bloqueio principal)

- Popup genérico: “APK Build Failed” + link `https://docs.flutterflow.io/troubleshooting/`
- Esse link é guia geral (Debug Info, console, Read More) — **não identifica o erro do projeto**
- O texto detalhado do **Read More** (parte vermelha) **nunca foi capturado** com sucesso
- Issues do projeto (último reportado pelo usuário): **0 Errors, 4 Warnings**:
  1. Request Permissions (Switch) not supported on Web
  2. Request Permissions (IconButton) not supported on Web
  3. Custom code using outdated `google_maps_flutter` (antes do pin 2.12.2)
  4. **Custom widgets have not been compiled or validated**

Hipóteses internas (sem log do build):

| ID | Hipótese | Probabilidade |
|----|----------|---------------|
| A | Conflito `google_maps_flutter` custom vs build Android/Kotlin do FF cloud | Alta |
| B | Widget custom não compilado bloqueia export APK | Alta |
| C | Conflito versão com Google Maps gerenciado pelo FF (keys em `google-maps`) | Média |
| D | Erro Dart/API no widget no analyze do builder | Média |
| E | Outro erro de projeto (ação incompleta etc.) | Baixa (0 errors no Issues) |

Referências externas conhecidas:

- FlutterFlow Help: Build failures por package conflicts
- Community: Kotlin metadata 2.3.0 vs plugin 2.1.0 em builds Maps (`google_maps_flutter_android`)
- Issues GitHub FF: custom Google Map widgets cinza / builds falhando

---

## 6. Limitações de ferramenta (importante para outra IA)

1. **MCP `update_project_yaml`:** pode retornar `success: true` sem gravar — sempre confirmar com `sync_project(force)` + releitura
2. **Custom Functions/Actions:** NUNCA gravar pelo caminho `*.dart` do MCP — corrompe; usar API oficial `.../function-code` ou `.../action-code` sem sufixo
3. **Custom Widget na página:** colocar nó CustomWidget via API/MCP é frágil; no projeto o nó `Container_uz29caov` **já está** com `customWidgetIdentifier: rotmap1`
4. **Widget nested page nodes:** API oficial às vezes não atualiza nós de página (issue FF #6579)
5. **Sem Flutter CLI** na máquina do usuário → não dá `flutter build apk` local para ver o log Gradle
6. **Desktop FF não abre** → Local Run indisponível

---

## 7. Estado atual (snapshot 2026-10-06)

| Item | Estado |
|------|--------|
| Backend Rota do Dia | OK |
| Página RouteOfTheDay lógica | OK (lista, otimizar, ações) |
| Slot do mapa | `RotaMapPolyline` custom na página |
| Dep `google_maps_flutter` | `2.12.2` exact |
| Compile widget no editor | Usuário reportou ✓ sem feedback claro; Issues ainda citava “not compiled” |
| Test Mode | Inadequado para validar mapa |
| Local Run / Desktop | Quebrado (app fecha) |
| APK cloud | **Falha** — log detalhado **ausente** |
| Static Map fallback | Código/API existem; pode ter sido removido da UI ao colocar Custom Widget |
| Prioridade do usuário | **APK precisa compilar**; mapa fancy é secundário se bloquear build |

Arquivo preparado para colar o erro (ainda vazio de conteúdo útil):

`storage/apk_build_error.txt`

---

## 8. Opções a avaliar (pedido explícito)

### Opção 1 — Destravar APK removendo o Custom Widget Maps (recomendada se build for prioridade)

1. Remover `RotaMapPolyline` da página RouteOfTheDay
2. Remover (ou esvaziar) `custom-code-dependencies` → `google_maps_flutter`
3. Colocar de volta:
   - **GoogleMap nativo FF** com `mapPoints`, **ou**
   - **Image** com `staticMapUrl` / `static_map_url` da API
4. Navegação: botão existente → Google Maps externo (`openRotaDirections` / `maps_url`)
5. Gerar APK e validar fluxo de coleta no celular
6. Depois, se necessário, mapa interativo em branch/experimento separado

**Prós:** maximiza chance de APK; alinhado ao “sem APK não adianta”.  
**Contras:** polyline interativa / caminhão no mapa ficam de fora (Static Map pode mostrar rota como imagem).

### Opção 2 — Manter Custom Widget e obter o log real do APK

1. Compilar widget (✓) até Issues limpar “not compiled”
2. Build APK → **Read More** → copiar texto completo (`Error:`, `FAILURE`, `Version solving`, Kotlin, etc.)
3. Com o log: pin de versão, Gradle via Deploy from GitHub, ou remoção de conflito

**Prós:** pode preservar mapa interativo.  
**Contras:** sem log estamos no escuro; Desktop local não ajuda.

### Opção 3 — Download code + build local (Android Studio / Flutter)

1. FlutterFlow → Download code / GitHub
2. `flutter pub get` + `flutter build apk` na máquina (precisa instalar Flutter SDK)
3. Ver log Gradle completo
4. Ajustar `android/` se for Kotlin/minSdk

**Prós:** evidência runtime completa.  
**Contras:** setup pesado; usuário ainda não tem Flutter instalado.

### Opção 4 — WebView / URL do Static Map ou Maps embed

- Custom Widget simples com `WebView` ou só Image
- Sem `google_maps_flutter` nativo

**Prós:** APK mais simples.  
**Contras:** UX e API keys / políticas Google; WebView no FF também tem pegadinhas.

### Opção 5 — Aceitar GoogleMap nativo no APK (mesmo frágil no Test Mode)

- Markers `mapPoints` podem funcionar no APK mesmo falhando no browser
- Polyline: não confiável nativamente → Static Map ao lado, ou só Maps externo após otimizar

**Prós:** zero dep custom Maps.  
**Contras:** sem polyline nativa estável; Test Mode continua enganoso.

### Opção 6 — Contatar support@flutterflow.io

- Com Bug Report Code do popup APK + projeto ID
- Especialmente se log mostrar Kotlin metadata mismatch (conhecido como limitação da imagem de build do FF)

---

## 9. O que a outra IA deve pedir / checar primeiro

1. **Texto completo do Read More** do “APK Build Failed” (obrigatório para diagnóstico fino)
2. Screenshot atual do painel **Issues** (Errors vs Warnings)
3. Se Custom Widget está marcado **Exclude from compilation** (deve estar OFF)
4. Se ainda existe GoogleMap nativo **além** do Custom Widget (duplicata)
5. Conteúdo de Project Dependencies (só `google_maps_flutter 2.12.2`?)
6. Decisão de produto: **APK primeiro** vs **mapa interativo primeiro**

---

## 10. Artefatos úteis no repo / FF

| Artefato | Onde |
|----------|------|
| Estratégia mapa | `docs/FLUTTERFLOW_ROTA_DO_DIA_REBUILD.md` |
| Armadilhas MCP | `docs/FLUTTERFLOW_MCP_GUIA.md` |
| Widget key | `custom-widgets/id-rotmap1` + `.../widget-code` |
| Nó na página | `page/.../node/id-Container_uz29caov` |
| Deps | `custom-code-dependencies` |
| Captura erro APK | `storage/apk_build_error.txt` (preencher) |
| Changelog | `ARCHITECTURE.md` entradas 2026-10-02/03 |

---

## 11. Resumo executivo (1 parágrafo)

O backend e o fluxo de Rota do Dia no FlutterFlow estão avançados; o mapa interativo foi migrado para o Custom Widget `RotaMapPolyline` (`google_maps_flutter` 2.12.2) porque o GoogleMap nativo falha no Test Mode. Isso desbloqueou o desenho teórico (pinos + polyline + coletor), mas **impediu validação prática**: Desktop Local Run não abre e o **APK cloud falha** sem log detalhado capturado. A hipótese principal é conflito/compilação do pacote Maps custom no builder do FlutterFlow. A opção mais segura para o negócio agora é **remover o Custom Widget Maps e a dep**, voltar a Static Map ou GoogleMap nativo, gerar APK, e só depois reintroduzir mapa interativo com evidência de build.

---

## 12. Isolamento APK (2026-10-06)

### FASE 2 — remover instância na página
**Alteração:** `Container_uz29caov` → `MapPlaceholder` (sem Custom Widget na árvore).  
**Resultado APK (usuário):** **FALHOU** ainda.

### FASE 2b — tirar `google_maps_flutter` do build
**Alterações:**
1. Removido `custom-code-dependencies` / `google_maps_flutter`.
2. `RotaMapPolyline` virou **stub** sem import de Maps.

**Resultado APK (usuário, 2026-10-06 ~10:58):** **PASSOU**

### Conclusão raiz
O bloqueio do APK era o **Custom Widget Maps + dependência `google_maps_flutter`** no builder cloud do FlutterFlow (não a lógica PHP, Page State, permissões Web, etc.).

### FASE 4 — GoogleMap mínimo (2026-10-06)
**Tentativa:** dep `2.5.3` + GoogleMap ponto fixo + widget na página.  
**Resultado APK (usuário):** **FALHOU**

### Conclusão FASE 4
`google_maps_flutter` via **Custom Widget / Custom Pub Dependency** **não é viável** neste projeto no builder cloud do FlutterFlow (mesmo widget mínimo).

### Rollback imediato (estado APK-safe restaurado)
1. Dep Maps removida de novo.
2. `RotaMapPolyline` = stub sem Maps.
3. Página = `MapPlaceholder` nativo.

**Validação APK após rollback:** **PASSOU** (usuário).

### FASE 5 — GoogleMap nativo FF (2026-10-06)
**Alteração:** slot do mapa com **`GoogleMap_ka4jr991`** nativo + `mapPoints`.  
**Resultado:** APK gerou; mapa abriu no celular (usuário).

### MVP produto (2026-10-06) — sem navegação in-app
Acordo: Google Maps **externo** navega; app só gerencia sequência + pinos.

**Alterações:**
1. Lista: título `N. Nome` (`ordem` + `nome_fantasia`).
2. `buildRotaDirUrl(items, originLat, originLng)` — origem = coletor se GPS/my_location preenchido.
3. Botão AbrirRotaMaps passa `collectorLat`/`collectorLng`.

**Não feito (de propósito):** polyline custom no app; tracking GPS contínuo.

*Gerado para handoff entre agentes / IAs.*

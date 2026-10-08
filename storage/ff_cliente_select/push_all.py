"""
Espelha o painel web (/painel/coleta/nova):
1) Tela NewCollectionClientSelection — lista clientes → POST criar → Wizard
2) FAB CollectionsList → essa tela
3) Wizard etapa 1: dropdowns/data/hora dos catálogos/API (sem placeholder)
"""
from __future__ import annotations

import json
import urllib.request
from pathlib import Path

PROJECT = "well-coletas-by2777"
TOKEN = json.loads(Path(r"C:\Users\Meu PC\.cursor\mcp.json").read_text(encoding="utf-8"))[
    "mcpServers"
]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]

WIZARD = "Scaffold_4rp06q2q"
LIST = "Scaffold_dwij8cyv"
NCCS = "Scaffold_nccs01ab"
STRUCT = "clcitm1"
FN_PARSE = "pclt01"


def push(files: dict[str, str]) -> None:
    body = json.dumps({"projectId": PROJECT, "fileKeyToContent": files}, ensure_ascii=False).encode()
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/updateProjectByYaml",
        data=body,
        headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=180) as resp:
        raw = resp.read().decode()
        print("push", resp.status, raw[:240], "files=", len(files))
        data = json.loads(raw)
        if not data.get("success"):
            raise SystemExit(f"push failed: {raw}")


# Page metadata completo (estado atual + listas de catálogo etapa 1)
WIZARD_PAGE_META = f"""name: CollectionWizard
params:
  cwcolid:
    identifier:
      name: coletaId
      key: cwcolid
    defaultValue:
      serializedValue: "0"
    dataType:
      scalarType: Integer
      nonNullable: true
node:
  key: {WIZARD}
classModel:
  stateFields:
    - parameter:
        identifier:
          name: currentStep
          key: cwstep
        defaultValue:
          serializedValue: "1"
        dataType:
          scalarType: Integer
          nonNullable: true
      serializedDefaultValue:
        - "1"
    - parameter:
        identifier:
          name: clienteNome
          key: cwcnom
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: clienteEndereco
          key: cwcend
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: transportadoraId
          key: cwtrid
        defaultValue:
          serializedValue: "0"
        dataType:
          scalarType: Integer
          nonNullable: true
      serializedDefaultValue:
        - "0"
    - parameter:
        identifier:
          name: veiculoId
          key: cwveid
        defaultValue:
          serializedValue: "0"
        dataType:
          scalarType: Integer
          nonNullable: true
      serializedDefaultValue:
        - "0"
    - parameter:
        identifier:
          name: coletorId
          key: cwclid
        defaultValue:
          serializedValue: "0"
        dataType:
          scalarType: Integer
          nonNullable: true
      serializedDefaultValue:
        - "0"
    - parameter:
        identifier:
          name: motoristaNome
          key: cwmotn
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: dataColeta
          key: cwdcol
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: horaColeta
          key: cwhora
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: isSaving
          key: cwsav
        defaultValue:
          serializedValue: "false"
        dataType:
          scalarType: Boolean
          nonNullable: true
      serializedDefaultValue:
        - "false"
    - parameter:
        identifier:
          name: residuos
          key: cwresi
        dataType:
          listType:
            scalarType: DataStruct
          subType:
            dataStructIdentifier:
              name: ColetaResiduoItem
              key: cdditm1
    - parameter:
        identifier:
          name: tipoResiduoNomes
          key: cwtinom
        dataType:
          listType:
            scalarType: String
          nonNullable: true
    - parameter:
        identifier:
          name: tipoResiduoIds
          key: cwtiids
        dataType:
          listType:
            scalarType: Integer
          nonNullable: true
    - parameter:
        identifier:
          name: relatorioSalvo
          key: cwrelok
        defaultValue:
          serializedValue: "false"
        dataType:
          scalarType: Boolean
          nonNullable: true
      serializedDefaultValue:
        - "false"
    - parameter:
        identifier:
          name: relatorioResumo
          key: cwrelrs
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: destinadorNomes
          key: cwdnom
        dataType:
          listType:
            scalarType: String
          nonNullable: true
    - parameter:
        identifier:
          name: destinadorIds
          key: cwdids
        dataType:
          listType:
            scalarType: Integer
          nonNullable: true
    - parameter:
        identifier:
          name: tratamentoOpcoes
          key: cwtopc
        dataType:
          listType:
            scalarType: String
          nonNullable: true
    - parameter:
        identifier:
          name: dataRecebimento
          key: cwdrec
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: transportadoraNomes
          key: cwtrnom
        dataType:
          listType:
            scalarType: String
          nonNullable: true
    - parameter:
        identifier:
          name: transportadoraIds
          key: cwtrids
        dataType:
          listType:
            scalarType: Integer
          nonNullable: true
    - parameter:
        identifier:
          name: veiculoNomes
          key: cwvenom
        dataType:
          listType:
            scalarType: String
          nonNullable: true
    - parameter:
        identifier:
          name: veiculoIds
          key: cwveids
        dataType:
          listType:
            scalarType: Integer
          nonNullable: true
    - parameter:
        identifier:
          name: coletorNomes
          key: cwclnom
        dataType:
          listType:
            scalarType: String
          nonNullable: true
    - parameter:
        identifier:
          name: coletorIds
          key: cwclids
        dataType:
          listType:
            scalarType: Integer
          nonNullable: true
"""

# ─── 1) DataStruct ClienteColetaItem ─────────────────────────────────────────
STRUCT_YAML = f"""identifier:
  name: ClienteColetaItem
  key: {STRUCT}
fields:
  - identifier:
      name: id
      key: clcf01
    dataType:
      scalarType: Integer
    description: ""
  - identifier:
      name: nome_fantasia
      key: clcf02
    dataType:
      scalarType: String
    description: ""
  - identifier:
      name: detalhe
      key: clcf03
    dataType:
      scalarType: String
    description: ""
description: Cliente para iniciar coleta (espelha painel)
"""

FN_META = f"""identifier:
  name: parseClientesColetaFromApi
  key: {FN_PARSE}
arguments:
  - identifier:
      name: apiJson
      key: pclt02
    dataType:
      scalarType: JSON
      nonNullable: false
returnParameter:
  dataType:
    listType:
      scalarType: DataStruct
    subType:
      dataStructIdentifier:
        name: ClienteColetaItem
        key: {STRUCT}
tests:
  - parameterValues: {{}}
"""

FN_CODE = """
  if (apiJson == null) {
    return [];
  }
  Map<String, dynamic> root;
  if (apiJson is String) {
    root = jsonDecode(apiJson) as Map<String, dynamic>;
  } else if (apiJson is Map) {
    root = Map<String, dynamic>.from(apiJson);
  } else {
    return [];
  }
  final data = root['data'];
  if (data is! Map) {
    return [];
  }
  final items = data['items'];
  if (items is! List) {
    return [];
  }
  final out = <ClienteColetaItemStruct>[];
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final cidade = m['cidade']?.toString() ?? '';
    final uf = m['uf']?.toString() ?? '';
    final prox = m['proxima_coleta']?.toString() ?? '';
    final prio = m['prioridade']?.toString() ?? '';
    final loc = [if (cidade.isNotEmpty) cidade, if (uf.isNotEmpty) uf].join('/');
    final bits = <String>[];
    if (loc.isNotEmpty) bits.add(loc);
    if (prox.isNotEmpty) bits.add('Prox: $prox');
    if (prio == 'urgente') bits.add('Urgente');
    out.add(
      ClienteColetaItemStruct(
        id: m['id'] is int ? m['id'] as int : int.tryParse('${m['id']}') ?? 0,
        nomeFantasia: m['nome_fantasia']?.toString() ?? '',
        detalhe: bits.join(' · '),
      ),
    );
  }
  return out;
"""

# ─── 2) Wizard: state + catalogs + bind dropdowns/date ───────────────────────
INIT_TRIGGER = f"""rootAction:
  key: cwinit1
  action:
    key: actcw001
  followUpAction:
    key: cwinit2
    action:
      key: actcw002
    followUpAction:
      key: cwinit3
      action:
        key: actcw003
      followUpAction:
        key: cwinit4
        action:
          key: actcw004
        followUpAction:
          key: cwinit5
          action:
            key: actcw005
          followUpAction:
            key: cwinit6
            action:
              key: actcw006
            followUpAction:
              key: cwinit7
              action:
                key: actcw007
              followUpAction:
                key: cwinit8
                action:
                  key: actcw008
                followUpAction:
                  key: cwinit9
                  action:
                    key: actcw009
                  followUpAction:
                    key: cwinit10
                    action:
                      key: actcw010
                    followUpAction:
                      key: cwinit11
                      action:
                        key: actcw011
                      followUpAction:
                        key: cwinit12
                        action:
                          key: actcw012
                        followUpAction:
                          key: cwinit13
                          action:
                            key: actcw013
                          followUpAction:
                            key: cwinit14
                            action:
                              key: actcw014
trigger:
  triggerType: ON_INIT_STATE
"""


def api_catalog(action_key: str, endpoint_name: str, endpoint_key: str, out_name: str) -> str:
    return f"""database:
  apiCall:
    endpointIdentifier:
      name: {endpoint_name}
      key: {endpoint_key}
    variables:
      - variableIdentifier:
          name: baseUrl
          key: bsurl1
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: apiBaseUrl
                key: ast004
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: authToken
          key: tok001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: authToken
                key: ast001
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {WIZARD}
outputVariableName: {out_name}
key: {action_key}
"""


def update_lists(
    action_key: str,
    api_action: str,
    api_out: str,
    nomes_name: str,
    nomes_key: str,
    ids_name: str,
    ids_key: str,
) -> str:
    return f"""localStateUpdate:
  updates:
    - fieldIdentifier:
        name: {nomes_name}
        key: {nomes_key}
      setValue:
        variable:
          source: FUNCTION_CALL
          functionCall:
            values:
              - variable:
                  source: ACTION_OUTPUTS
                  baseVariable:
                    actionOutput:
                      outputVariableIdentifier:
                        name: {api_out}
                      actionKeyRef:
                        key: {api_action}
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: {WIZARD}
                  actionComponentKeyRef: {{}}
            customFunction:
              name: parseDestinadorNomesFromApi
              key: wdssn01
          nodeKeyRef:
            key: {WIZARD}
    - fieldIdentifier:
        name: {ids_name}
        key: {ids_key}
      setValue:
        variable:
          source: FUNCTION_CALL
          functionCall:
            values:
              - variable:
                  source: ACTION_OUTPUTS
                  baseVariable:
                    actionOutput:
                      outputVariableIdentifier:
                        name: {api_out}
                      actionKeyRef:
                        key: {api_action}
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: {WIZARD}
                  actionComponentKeyRef: {{}}
            customFunction:
              name: parseDestinadorIdsFromApi
              key: wdssi01
          nodeKeyRef:
            key: {WIZARD}
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: {action_key}
"""


def dropdown_bound(key: str, name: str, hint: str, labels_name: str, labels_key: str, ids_name: str, ids_key: str) -> str:
    return f"""key: {key}
type: DropDown
props:
  padding: {{}}
  dropDown:
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
      height:
        pixelsValue:
          inputValue: 48
    textStyle:
      themeStyle: BODY_MEDIUM
      colorValue:
        inputValue:
          themeColor: PRIMARY_TEXT
    hintText:
      textValue:
        inputValue: {hint}
        mostRecentInputValue: {hint}
    optionsHasKeyValues: true
    valueType:
      dataType:
        scalarType: Integer
    fillColorValue:
      inputValue:
        themeColor: SECONDARY_BACKGROUND
      mostRecentInputValue:
        themeColor: SECONDARY_BACKGROUND
    elevationValue:
      inputValue: 2
      mostRecentInputValue: 2
    borderRadiusValue:
      inputValue: 8
      mostRecentInputValue: 8
    borderColorValue:
      inputValue:
        themeColor: ALTERNATE
      mostRecentInputValue:
        themeColor: ALTERNATE
    borderWidthValue:
      inputValue: 1
      mostRecentInputValue: 1
    hidesUnderlineValue:
      inputValue: true
      mostRecentInputValue: true
    optionsLabels:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: {labels_name}
              key: {labels_key}
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: {WIZARD}
    optionsValues:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: {ids_name}
              key: {ids_key}
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: {WIZARD}
name: {name}
"""


def text_state(key: str, field: str, field_key: str, style: str = "BODY_MEDIUM") -> str:
    return f"""key: {key}
type: Text
props:
  text:
    themeStyle: {style}
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: {field}
              key: {field_key}
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: {WIZARD}
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    heightValue:
      inputValue: 1.47
name: Text
"""


# Continuar: ler IDs dos dropdowns (como o painel lê o <select>)
ACT_TRN = f"""database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Transporte
      key: wtrn01
    variables:
      - variableIdentifier:
          name: baseUrl
          key: bsurl1
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: apiBaseUrl
                key: ast004
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: authToken
          key: tok001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: authToken
                key: ast001
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: coletaId
          key: colid1
        variable:
          source: WIDGET_CLASS_PARAMETER
          baseVariable:
            widgetClass:
              paramIdentifier:
                name: coletaId
                key: cwcolid
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: transportadora_id
          key: tpid01
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: Integer
          nodeKeyRef:
            key: DropDown_u98smj8s
      - variableIdentifier:
          name: veiculo_id
          key: veic01
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: Integer
          nodeKeyRef:
            key: DropDown_1519s7xt
      - variableIdentifier:
          name: coletor_id
          key: cltid1
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: Integer
          nodeKeyRef:
            key: DropDown_yup3oioi
      - variableIdentifier:
          name: motorista_nome
          key: mot001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: userName
                key: ast002
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: data_coleta
          key: dtc001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: dataColeta
                key: cwdcol
              stateVariableType: WIDGET_CLASS_STATE
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: hora
          key: hora01
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: horaColeta
                key: cwhora
              stateVariableType: WIDGET_CLASS_STATE
          nodeKeyRef:
            key: {WIZARD}
      - variableIdentifier:
          name: destinador_id
          key: dest01
        inputValue:
          serializedValue: "0"
      - variableIdentifier:
          name: tratamento
          key: trt001
        inputValue:
          serializedValue: ""
      - variableIdentifier:
          name: observacao_destinador
          key: obsdes
        inputValue:
          serializedValue: ""
      - variableIdentifier:
          name: situacao_recebimento
          key: sit001
        inputValue:
          serializedValue: pendente
      - variableIdentifier:
          name: data_recebimento
          key: dtr001
        inputValue:
          serializedValue: ""
outputVariableName: wizardTransporteResult
key: actcwtrn
"""

# ─── 3) NewCollectionClientSelection page ────────────────────────────────────
PAGE_META = f"""name: NewCollectionClientSelection
description: Escolher cliente para iniciar coleta (espelha /painel/coleta/nova)
node:
  key: {NCCS}
classModel:
  stateFields:
    - parameter:
        identifier:
          name: busca
          key: nccsbs
        defaultValue:
          serializedValue: ""
        dataType:
          scalarType: String
          nonNullable: true
      serializedDefaultValue:
        - ""
    - parameter:
        identifier:
          name: clienteItems
          key: nccsli
        dataType:
          listType:
            scalarType: DataStruct
          subType:
            dataStructIdentifier:
              name: ClienteColetaItem
              key: {STRUCT}
    - parameter:
        identifier:
          name: isLoading
          key: nccsld
        defaultValue:
          serializedValue: "false"
        dataType:
          scalarType: Boolean
          nonNullable: true
      serializedDefaultValue:
        - "false"
"""

PAGE_OUTLINE = f"""node:
  key: {NCCS}
  body:
    key: Column_nccsroot
    children:
      - key: Container_nccshd
        children:
          - key: Row_nccshdr
            children:
              - key: Icon_nccsback
              - key: Column_nccstit
                children:
                  - key: Text_nccstt1
                  - key: Text_nccstt2
      - key: Container_nccsfilt
        children:
          - key: Column_nccsfc
            children:
              - key: TextField_nccsbusca
              - key: Button_nccsbuscar
      - key: Container_nccslist
        children:
          - key: ListView_nccslv
            children:
              - key: Container_nccscard
                children:
                  - key: Column_nccscc
                    children:
                      - key: Text_nccsnome
                      - key: Text_nccsdet
"""

SCAFFOLD_NODE = f"""key: {NCCS}
type: Scaffold
props:
  scaffold:
    safeArea: true
    navBarItem:
      show: false
    hideKeyboardOnTap: true
    backgroundColorValue:
      inputValue:
        themeColor: PRIMARY_BACKGROUND
      mostRecentInputValue:
        themeColor: PRIMARY_BACKGROUND
name: NewCollectionClientSelection
"""

COLUMN_ROOT = """key: Column_nccsroot
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_stretch
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 0
    minSizeValue:
      inputValue: false
  padding: {}
name: Root
"""

HEADER_WRAP = """key: Container_nccshd
type: Container
props:
  container:
    dimensions: {}
    boxDecoration:
      boxShadow: {}
      image: {}
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
    maxDimensions: {}
    minDimensions: {}
  padding:
    type: FF_PADDING_ONLY
    leftValue:
      inputValue: 16
    topValue:
      inputValue: 12
    rightValue:
      inputValue: 16
    bottomValue:
      inputValue: 12
name: Header
"""

HEADER_ROW = """key: Row_nccshdr
type: Row
props:
  row:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_center
    listSpacing:
      spacingValue:
        inputValue: 12
  padding: {}
name: HeaderRow
"""

ICON_BACK = """key: Icon_nccsback
type: Icon
props:
  icon:
    iconDataValue:
      inputValue:
        codePoint: 57490
        family: MaterialIcons
        matchTextDirection: true
        name: arrow_back
    sizeValue:
      inputValue: 24
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
  padding: {}
name: Back
"""

COL_TITLE = """key: Column_nccstit
type: Column
props:
  column:
    crossAxisAlignment: cross_axis_start
    listSpacing:
      spacingValue:
        inputValue: 2
    minSizeValue:
      inputValue: true
  padding: {}
name: Titles
"""

TEXT_T1 = """key: Text_nccstt1
type: Text
props:
  text:
    themeStyle: TITLE_MEDIUM
    selectable: false
    textValue:
      inputValue: Escolher cliente
      mostRecentInputValue: Escolher cliente
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
name: Title
"""

TEXT_T2 = """key: Text_nccstt2
type: Text
props:
  text:
    themeStyle: LABEL_SMALL
    selectable: false
    textValue:
      inputValue: Selecione o gerador para iniciar a coleta
      mostRecentInputValue: Selecione o gerador para iniciar a coleta
    colorValue:
      inputValue:
        themeColor: SECONDARY_TEXT
name: Subtitle
"""

FILT_WRAP = """key: Container_nccsfilt
type: Container
props:
  container:
    dimensions: {}
    boxDecoration:
      boxShadow: {}
      image: {}
    maxDimensions: {}
    minDimensions: {}
  padding:
    type: FF_PADDING_ONLY
    leftValue:
      inputValue: 16
    topValue:
      inputValue: 12
    rightValue:
      inputValue: 16
    bottomValue:
      inputValue: 8
name: Filtros
"""

FILT_COL = """key: Column_nccsfc
type: Column
props:
  column:
    crossAxisAlignment: cross_axis_stretch
    listSpacing:
      spacingValue:
        inputValue: 8
    minSizeValue:
      inputValue: true
  padding: {}
name: FiltCol
"""

BUSCA_TF = f"""key: TextField_nccsbusca
type: TextField
props:
  textField:
    textStyle:
      themeStyle: BODY_MEDIUM
    inputDecoration:
      inputBorderType: outline
      borderRadius:
        type: FF_BORDER_RADIUS_ALL
        allValue:
          inputValue: 8
      hintText:
        textValue:
          inputValue: Buscar cliente, cidade, CNPJ...
          mostRecentInputValue: Buscar cliente, cidade, CNPJ...
      filledValue:
        inputValue: true
      fillColorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
    maxLinesValue:
      inputValue: 1
  padding: {{}}
name: Busca
"""

BTN_BUSCAR = """key: Button_nccsbuscar
type: Button
props:
  button:
    text:
      themeStyle: TITLE_SMALL
      textValue:
        inputValue: Buscar
        mostRecentInputValue: Buscar
      colorValue:
        inputValue:
          themeColor: INFO
    borderRadius:
      type: FF_BORDER_RADIUS_ALL
      allValue:
        inputValue: 8
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
      height:
        pixelsValue:
          inputValue: 44
    elevationValue:
      inputValue: 0
    fillColorValue:
      inputValue:
        themeColor: PRIMARY
  padding: {}
name: Buscar
"""

LIST_WRAP = """key: Container_nccslist
type: Container
props:
  container:
    dimensions: {}
    boxDecoration:
      boxShadow: {}
      image: {}
    maxDimensions: {}
    minDimensions: {}
  expanded:
    expandedType: EXPANDED
  padding:
    type: FF_PADDING_ONLY
    leftValue:
      inputValue: 16
    rightValue:
      inputValue: 16
    bottomValue:
      inputValue: 16
name: ListWrap
"""

LISTVIEW = f"""key: ListView_nccslv
type: ListView
props:
  listView:
    axis: FF_AXIS_VERTICAL
    primary: true
    listSpacing:
      spacingValue:
        inputValue: 10
    shrinkWrapValue:
      inputValue: false
  padding: {{}}
parameterValues: {{}}
generatorVariable:
  identifier:
    name: clienteItem
  variable:
    source: LOCAL_STATE
    baseVariable:
      localState:
        fieldIdentifier:
          name: clienteItems
          key: nccsli
        stateVariableType: WIDGET_CLASS_STATE
    nodeKeyRef:
      key: {NCCS}
valueKey: {{}}
"""

CARD = """key: Container_nccscard
type: Container
props:
  container:
    dimensions:
      width:
        pixelsValue:
          inputValue: Infinity
    boxDecoration:
      borderRadius:
        type: FF_BORDER_RADIUS_ALL
        allValue:
          inputValue: 12
      boxShadow: {}
      image: {}
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
      borderColorValue:
        inputValue:
          themeColor: ALTERNATE
      borderWidthValue:
        inputValue: 1
    maxDimensions: {}
    minDimensions: {}
  padding:
    type: FF_PADDING_ONLY
    leftValue:
      inputValue: 14
    topValue:
      inputValue: 12
    rightValue:
      inputValue: 14
    bottomValue:
      inputValue: 12
name: ClientCard
"""

CARD_COL = """key: Column_nccscc
type: Column
props:
  column:
    crossAxisAlignment: cross_axis_start
    listSpacing:
      spacingValue:
        inputValue: 4
    minSizeValue:
      inputValue: true
  padding: {}
name: CardCol
"""

TEXT_NOME = f"""key: Text_nccsnome
type: Text
props:
  text:
    themeStyle: TITLE_SMALL
    selectable: false
    textValue:
      variable:
        source: GENERATOR_VARIABLE
        defaultValue:
          serializedValue: Cliente
        baseVariable:
          generatorVariable: {{}}
        operations:
          - accessDataStructField:
              fieldIdentifier:
                name: nome_fantasia
                key: clcf02
        nodeKeyRef:
          key: ListView_nccslv
      mostRecentInputValue: Cliente
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
name: Nome
"""

TEXT_DET = f"""key: Text_nccsdet
type: Text
props:
  text:
    themeStyle: LABEL_SMALL
    selectable: false
    textValue:
      variable:
        source: GENERATOR_VARIABLE
        defaultValue:
          serializedValue: ""
        baseVariable:
          generatorVariable: {{}}
        operations:
          - accessDataStructField:
              fieldIdentifier:
                name: detalhe
                key: clcf03
        nodeKeyRef:
          key: ListView_nccslv
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: SECONDARY_TEXT
name: Detalhe
"""

# Actions: load clients
def load_clientes_action(action_key: str, out_name: str, busca_from_field: bool = True) -> str:
    busca_block = (
        f"""
      - variableIdentifier:
          name: busca
          key: busq01
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: String
          nodeKeyRef:
            key: TextField_nccsbusca
"""
        if busca_from_field
        else """
      - variableIdentifier:
          name: busca
          key: busq01
        inputValue:
          serializedValue: ""
"""
    )
    return f"""database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Clientes Coleta
      key: wcli01
    variables:
      - variableIdentifier:
          name: baseUrl
          key: bsurl1
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: apiBaseUrl
                key: ast004
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {NCCS}
      - variableIdentifier:
          name: authToken
          key: tok001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: authToken
                key: ast001
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: {NCCS}
      - variableIdentifier:
          name: page
          key: pg0001
        inputValue:
          serializedValue: "1"
      - variableIdentifier:
          name: per_page
          key: pp0001
        inputValue:
          serializedValue: "30"
{busca_block}
      - variableIdentifier:
          name: escopo
          key: esc001
        inputValue:
          serializedValue: pendentes
outputVariableName: {out_name}
key: {action_key}
"""


UPDATE_CLIENTES = f"""localStateUpdate:
  updates:
    - fieldIdentifier:
        name: clienteItems
        key: nccsli
      setValue:
        variable:
          source: FUNCTION_CALL
          functionCall:
            values:
              - variable:
                  source: ACTION_OUTPUTS
                  baseVariable:
                    actionOutput:
                      outputVariableIdentifier:
                        name: nccsClientesResult
                      actionKeyRef:
                        key: actnccs01
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: {NCCS}
                  actionComponentKeyRef: {{}}
            customFunction:
              name: parseClientesColetaFromApi
              key: {FN_PARSE}
          nodeKeyRef:
            key: {NCCS}
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actnccs02
"""

UPDATE_CLIENTES_BUSCA = UPDATE_CLIENTES.replace("nccsClientesResult", "nccsBuscaResult").replace(
    "actnccs01", "actnccs03"
).replace("key: actnccs02", "key: actnccs04")

CREATE_COLETA = f"""database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Criar
      key: wcrt01
    variables:
      - variableIdentifier:
          name: baseUrl
          key: bsurl1
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: apiBaseUrl
                key: ast004
              stateVariableType: APP_STATE
      - variableIdentifier:
          name: authToken
          key: tok001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: authToken
                key: ast001
              stateVariableType: APP_STATE
      - variableIdentifier:
          name: cliente_id
          key: cli001
        variable:
          source: GENERATOR_VARIABLE
          baseVariable:
            generatorVariable: {{}}
          operations:
            - accessDataStructField:
                fieldIdentifier:
                  name: id
                  key: clcf01
          nodeKeyRef:
            key: ListView_nccslv
      - variableIdentifier:
          name: data_coleta
          key: dtc001
        inputValue:
          serializedValue: ""
      - variableIdentifier:
          name: client_uuid
          key: cuu001
        inputValue:
          serializedValue: ""
outputVariableName: nccsCriarResult
key: actnccs05
"""

NAV_WIZARD = f"""navigate:
  allowBack: true
  passedParameters:
    parameterPasses:
      cwcolid:
        paramIdentifier:
          name: coletaId
          key: cwcolid
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: "0"
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: nccsCriarResult
              actionKeyRef:
                key: actnccs05
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.id
          nodeKeyRef:
            key: Container_nccscard
          actionComponentKeyRef: {{}}
    widgetClassNodeKeyRef:
      key: {WIZARD}
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: {WIZARD}
key: actnccs06
"""

BACK_NAV = """navigate:
  isNavigateBack: true
  navigateToRootPageOnFailure: false
key: actnccsback
"""

FAB_NAV = f"""navigate:
  allowBack: true
  passedParameters:
    parameterPasses: {{}}
    widgetClassNodeKeyRef:
      key: {NCCS}
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: {NCCS}
key: 3rl8vfj9
"""


def main() -> None:
    # A) struct + parse function
    print("=== A struct+fn ===")
    push(
        {
            f"data-structs/id-{STRUCT}": STRUCT_YAML,
            f"custom-functions/id-{FN_PARSE}": FN_META,
            f"custom-functions/id-{FN_PARSE}/function-code": FN_CODE,
        }
    )

    # B) wizard catalogs + bind
    print("=== B wizard catalogs ===")
    files_b: dict[str, str] = {
        f"page/id-{WIZARD}": WIZARD_PAGE_META,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE": INIT_TRIGGER,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw009": api_catalog(
            "actcw009", "WellAdmin Catalogo Transportadoras", "wtrp01", "wizardTrpResult"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw010": update_lists(
            "actcw010", "actcw009", "wizardTrpResult", "transportadoraNomes", "cwtrnom", "transportadoraIds", "cwtrids"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw011": api_catalog(
            "actcw011", "WellAdmin Catalogo Veiculos", "wvei01", "wizardVeiResult"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw012": update_lists(
            "actcw012", "actcw011", "wizardVeiResult", "veiculoNomes", "cwvenom", "veiculoIds", "cwveids"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw013": api_catalog(
            "actcw013", "WellAdmin Catalogo Coletores", "wcltr1", "wizardColResult"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw014": update_lists(
            "actcw014", "actcw013", "wizardColResult", "coletorNomes", "cwclnom", "coletorIds", "cwclids"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-DropDown_u98smj8s": dropdown_bound(
            "DropDown_u98smj8s", "TransportadoraDropdown", "Selecione", "transportadoraNomes", "cwtrnom", "transportadoraIds", "cwtrids"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-DropDown_1519s7xt": dropdown_bound(
            "DropDown_1519s7xt", "VeiculoDropdown", "Selecione o veiculo", "veiculoNomes", "cwvenom", "veiculoIds", "cwveids"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-DropDown_yup3oioi": dropdown_bound(
            "DropDown_yup3oioi", "MotoristaDropdown", "Selecione o coletor", "coletorNomes", "cwclnom", "coletorIds", "cwclids"
        ),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Text_3m9jdkvv": text_state("Text_3m9jdkvv", "dataColeta", "cwdcol"),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Text_c5z5jlck": text_state("Text_c5z5jlck", "horaColeta", "cwhora"),
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwtrn": ACT_TRN,
    }
    push(files_b)

    # C) client selection page + FAB
    print("=== C client selection page ===")
    base = f"page/id-{NCCS}"
    files_c: dict[str, str] = {
        base: PAGE_META,
        f"{base}/page-widget-tree-outline": PAGE_OUTLINE,
        f"{base}/page-widget-tree-outline/node/id-{NCCS}": SCAFFOLD_NODE,
        f"{base}/page-widget-tree-outline/node/id-Column_nccsroot": COLUMN_ROOT,
        f"{base}/page-widget-tree-outline/node/id-Container_nccshd": HEADER_WRAP,
        f"{base}/page-widget-tree-outline/node/id-Row_nccshdr": HEADER_ROW,
        f"{base}/page-widget-tree-outline/node/id-Icon_nccsback": ICON_BACK,
        f"{base}/page-widget-tree-outline/node/id-Column_nccstit": COL_TITLE,
        f"{base}/page-widget-tree-outline/node/id-Text_nccstt1": TEXT_T1,
        f"{base}/page-widget-tree-outline/node/id-Text_nccstt2": TEXT_T2,
        f"{base}/page-widget-tree-outline/node/id-Container_nccsfilt": FILT_WRAP,
        f"{base}/page-widget-tree-outline/node/id-Column_nccsfc": FILT_COL,
        f"{base}/page-widget-tree-outline/node/id-TextField_nccsbusca": BUSCA_TF,
        f"{base}/page-widget-tree-outline/node/id-Button_nccsbuscar": BTN_BUSCAR,
        f"{base}/page-widget-tree-outline/node/id-Container_nccslist": LIST_WRAP,
        f"{base}/page-widget-tree-outline/node/id-ListView_nccslv": LISTVIEW,
        f"{base}/page-widget-tree-outline/node/id-Container_nccscard": CARD,
        f"{base}/page-widget-tree-outline/node/id-Column_nccscc": CARD_COL,
        f"{base}/page-widget-tree-outline/node/id-Text_nccsnome": TEXT_NOME,
        f"{base}/page-widget-tree-outline/node/id-Text_nccsdet": TEXT_DET,
        # ON_INIT load
        f"{base}/page-widget-tree-outline/node/id-{NCCS}/trigger_actions/id-ON_INIT_STATE": """rootAction:
  key: nccsinit1
  action:
    key: actnccs01
  followUpAction:
    key: nccsinit2
    action:
      key: actnccs02
trigger:
  triggerType: ON_INIT_STATE
""",
        f"{base}/page-widget-tree-outline/node/id-{NCCS}/trigger_actions/id-ON_INIT_STATE/action/id-actnccs01": load_clientes_action(
            "actnccs01", "nccsClientesResult", busca_from_field=False
        ),
        f"{base}/page-widget-tree-outline/node/id-{NCCS}/trigger_actions/id-ON_INIT_STATE/action/id-actnccs02": UPDATE_CLIENTES,
        # Buscar button
        f"{base}/page-widget-tree-outline/node/id-Button_nccsbuscar/trigger_actions/id-ON_TAP": """rootAction:
  key: nccsbsr1
  action:
    key: actnccs03
  followUpAction:
    key: nccsbsr2
    action:
      key: actnccs04
trigger:
  triggerType: ON_TAP
""",
        f"{base}/page-widget-tree-outline/node/id-Button_nccsbuscar/trigger_actions/id-ON_TAP/action/id-actnccs03": load_clientes_action(
            "actnccs03", "nccsBuscaResult", busca_from_field=True
        ),
        f"{base}/page-widget-tree-outline/node/id-Button_nccsbuscar/trigger_actions/id-ON_TAP/action/id-actnccs04": UPDATE_CLIENTES_BUSCA,
        # Card tap: create + navigate
        f"{base}/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP": """rootAction:
  key: nccstap1
  action:
    key: actnccs05
  followUpAction:
    key: nccstap2
    action:
      key: actnccs06
trigger:
  triggerType: ON_TAP
""",
        f"{base}/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP/action/id-actnccs05": CREATE_COLETA,
        f"{base}/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP/action/id-actnccs06": NAV_WIZARD,
        # Back
        f"{base}/page-widget-tree-outline/node/id-Icon_nccsback/trigger_actions/id-ON_TAP": """rootAction:
  key: nccsback1
  action:
    key: actnccsback
trigger:
  triggerType: ON_TAP
""",
        f"{base}/page-widget-tree-outline/node/id-Icon_nccsback/trigger_actions/id-ON_TAP/action/id-actnccsback": BACK_NAV,
        # FAB → selection page
        f"page/id-{LIST}/page-widget-tree-outline/node/id-FloatingActionButton_3dm55pze/trigger_actions/id-ON_TAP/action/id-3rl8vfj9": FAB_NAV,
    }
    push(files_c)
    print("DONE")


if __name__ == "__main__":
    main()

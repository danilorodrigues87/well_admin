#!/usr/bin/env python3
"""Push Etapa D: residuos ListView + add/remove + catalog tipos + step-aware footer."""
from __future__ import annotations

import json
import urllib.request
from pathlib import Path

MCP = Path(r"C:\Users\Meu PC\.cursor\mcp.json")
PROJECT = "well-coletas-by2777"


def token() -> str:
    mcp = json.loads(MCP.read_text(encoding="utf-8"))
    return mcp["mcpServers"]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]


def push(file_key_to_content: dict[str, str]) -> None:
    body = json.dumps(
        {"projectId": PROJECT, "fileKeyToContent": file_key_to_content},
        ensure_ascii=False,
    ).encode("utf-8")
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/updateProjectByYaml",
        data=body,
        headers={
            "Authorization": f"Bearer {token()}",
            "Content-Type": "application/json",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            print("status", resp.status, resp.read().decode("utf-8")[:500])
    except Exception as e:
        if hasattr(e, "read"):
            print("HTTP", getattr(e, "code", None), e.read().decode("utf-8", errors="replace")[:4000])
        else:
            raise


DATA_STRUCT = r'''identifier:
  name: ColetaResiduoItem
  key: cdditm1
fields:
  - identifier:
      name: id
      key: cdf04
    dataType:
      scalarType: Integer
    description: ""
  - identifier:
      name: nome
      key: cdf01
    dataType:
      scalarType: String
    description: ""
  - identifier:
      name: detalhe
      key: cdf02
    dataType:
      scalarType: String
    description: ""
  - identifier:
      name: quantidade
      key: cdf03
    dataType:
      scalarType: String
    description: ""
description: Item de residuo no detalhe/wizard da coleta
'''

CDDP_META = r'''identifier:
  name: parseColetaDetailItemsFromApi
  key: cddp01
arguments:
  - identifier:
      name: apiJson
      key: cddp02
    dataType:
      scalarType: JSON
      nonNullable: false
returnParameter:
  dataType:
    listType:
      scalarType: DataStruct
    subType:
      dataStructIdentifier:
        name: ColetaResiduoItem
        key: cdditm1
tests:
  - parameterValues: {}
'''

CDDP_CODE = r'''
  if (apiJson == null) {
    return [];
  }
  if (apiJson is! Map) {
    return [];
  }
  final root = Map<String, dynamic>.from(apiJson);
  final data = root['data'];
  if (data is! Map) {
    return [];
  }
  final items = data['itens'];
  if (items is! List) {
    return [];
  }
  final out = <ColetaResiduoItemStruct>[];
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final id = (m['id'] as num?)?.toInt() ?? 0;
    final nome = m['nome_label']?.toString() ?? m['nome']?.toString() ?? '';
    final detalhe = m['classe_nome']?.toString() ?? '';
    final quantidade = m['quantidade_label']?.toString() ?? '';
    out.add(
      ColetaResiduoItemStruct(
        id: id,
        nome: nome,
        detalhe: detalhe,
        quantidade: quantidade,
      ),
    );
  }
  return out;
'''

WTIPN_META = r'''identifier:
  name: parseTipoResiduoNomesFromApi
  key: wtipn01
arguments:
  - identifier:
      name: apiJson
      key: wtipn02
    dataType:
      scalarType: JSON
      nonNullable: false
returnParameter:
  dataType:
    listType:
      scalarType: String
      nonNullable: true
tests:
  - parameterValues: {}
'''

WTIPN_CODE = r'''
  final out = <String>[];
  if (apiJson == null) {
    return out;
  }
  if (apiJson is! Map) {
    return out;
  }
  final root = Map<String, dynamic>.from(apiJson);
  final data = root['data'];
  if (data is! Map) {
    return out;
  }
  final items = data['items'];
  if (items is! List) {
    return out;
  }
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final nome = m['nome']?.toString() ?? '';
    if (nome.isEmpty) {
      continue;
    }
    final classe = m['classe_nome']?.toString() ?? '';
    out.add(classe.isNotEmpty ? '$nome ($classe)' : nome);
  }
  return out;
'''

WTIPI_META = r'''identifier:
  name: parseTipoResiduoIdsFromApi
  key: wtipi01
arguments:
  - identifier:
      name: apiJson
      key: wtipi02
    dataType:
      scalarType: JSON
      nonNullable: false
returnParameter:
  dataType:
    listType:
      scalarType: Integer
      nonNullable: true
tests:
  - parameterValues: {}
'''

WTIPI_CODE = r'''
  final out = <int>[];
  if (apiJson == null) {
    return out;
  }
  if (apiJson is! Map) {
    return out;
  }
  final root = Map<String, dynamic>.from(apiJson);
  final data = root['data'];
  if (data is! Map) {
    return out;
  }
  final items = data['items'];
  if (items is! List) {
    return out;
  }
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final id = (m['id'] as num?)?.toInt();
    if (id != null && id > 0) {
      out.add(id);
    }
  }
  return out;
'''

PAGE_META = r'''name: CollectionWizard
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
  key: Scaffold_4rp06q2q
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
'''

ON_INIT = r'''rootAction:
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
trigger:
  triggerType: ON_INIT_STATE
'''

ACT_CW002 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: clienteNome
        key: cwcnom
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.cliente_nome
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: clienteEndereco
        key: cwcend
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.snapshot.gerador_endereco
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: transportadoraId
        key: cwtrid
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.transportadora_id
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: veiculoId
        key: cwveid
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.veiculo_id
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: coletorId
        key: cwclid
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.coletor_id
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: motoristaNome
        key: cwmotn
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.snapshot.motorista_nome
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: dataColeta
        key: cwdcol
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.data_coleta
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: horaColeta
        key: cwhora
      setValue:
        variable:
          source: ACTION_OUTPUTS
          baseVariable:
            actionOutput:
              outputVariableIdentifier:
                name: wizardDetalheResult
              actionKeyRef:
                key: actcw001
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.hora
          nodeKeyRef:
            key: Scaffold_4rp06q2q
          actionComponentKeyRef: {}
    - fieldIdentifier:
        name: residuos
        key: cwresi
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
                        name: wizardDetalheResult
                      actionKeyRef:
                        key: actcw001
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseColetaDetailItemsFromApi
              key: cddp01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
    - fieldIdentifier:
        name: currentStep
        key: cwstep
      setValue:
        inputValue:
          serializedValue: "1"
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcw002
'''

ACT_CW003 = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Catalogo Tipos Residuos
      key: wtip01
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
outputVariableName: wizardTiposResult
key: actcw003
'''

ACT_CW004 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: tipoResiduoNomes
        key: cwtinom
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
                        name: wizardTiposResult
                      actionKeyRef:
                        key: actcw003
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseTipoResiduoNomesFromApi
              key: wtipn01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
    - fieldIdentifier:
        name: tipoResiduoIds
        key: cwtiids
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
                        name: wizardTiposResult
                      actionKeyRef:
                        key: actcw003
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseTipoResiduoIdsFromApi
              key: wtipi01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcw004
'''

DROP_TIP = r'''key: DropDown_cwst2tip
type: DropDown
props:
  padding: {}
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
        inputValue: Buscar residuo
        mostRecentInputValue: Buscar residuo
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
              name: tipoResiduoNomes
              key: cwtinom
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
    optionsValues:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: tipoResiduoIds
              key: cwtiids
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
name: TipoResiduo
'''

ADD_TRIGGER = r'''rootAction:
  key: cwadd1
  action:
    key: actcwadd
  followUpAction:
    key: cwadd2
    action:
      key: actcwaddup
trigger:
  triggerType: ON_TAP
'''

ACT_CWADD = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Add Item
      key: wadd01
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
      - variableIdentifier:
          name: tipo_residuo_id
          key: tip001
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: Integer
          nodeKeyRef:
            key: DropDown_cwst2tip
      - variableIdentifier:
          name: quantidade
          key: qtd001
        variable:
          source: FUNCTION_CALL
          functionCall:
            values:
              - variable:
                  source: WIDGET_STATE
                  baseVariable:
                    widgetState:
                      returnParameter:
                        dataType:
                          scalarType: String
                  nodeKeyRef:
                    key: TextField_cwst2qtd
            codeExpression:
              arguments:
                - identifier:
                    name: raw
                  dataType:
                    scalarType: String
                    nonNullable: true
              returnParameter:
                dataType:
                  scalarType: Double
                  nonNullable: true
              expression: "double.tryParse((raw).replaceAll(',', '.')) ?? 0.0"
              valid: true
      - variableIdentifier:
          name: unidade
          key: uni001
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: String
          nodeKeyRef:
            key: DropDown_cwst2uni
outputVariableName: wizardAddItemResult
key: actcwadd
'''

ACT_CWADDUP = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: residuos
        key: cwresi
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
                        name: wizardAddItemResult
                      actionKeyRef:
                        key: actcwadd
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseColetaDetailItemsFromApi
              key: cddp01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwaddup
'''

DEL_TRIGGER = r'''rootAction:
  key: cwdel1
  action:
    key: actcwdel
  followUpAction:
    key: cwdel2
    action:
      key: actcwdelup
trigger:
  triggerType: ON_TAP
'''

ACT_CWDEL = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Remove Item
      key: wrmv01
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
      - variableIdentifier:
          name: itemId
          key: itm001
        variable:
          source: GENERATOR_VARIABLE
          baseVariable:
            generatorVariable: {}
          operations:
            - accessDataStructField:
                fieldIdentifier:
                  name: id
                  key: cdf04
          nodeKeyRef:
            key: ListView_cwst2lv
outputVariableName: wizardDelItemResult
key: actcwdel
'''

ACT_CWDELUP = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: residuos
        key: cwresi
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
                        name: wizardDelItemResult
                      actionKeyRef:
                        key: actcwdel
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseColetaDetailItemsFromApi
              key: cddp01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwdelup
'''

LISTVIEW = r'''key: ListView_cwst2lv
type: ListView
props:
  listView:
    axis: FF_AXIS_VERTICAL
    primary: false
    listSpacing:
      spacingValue:
        inputValue: 8
    shrinkWrapValue:
      inputValue: true
  padding: {}
name: ListaResiduosWizard
parameterValues: {}
generatorVariable:
  identifier:
    name: residuoItem
  variable:
    source: LOCAL_STATE
    baseVariable:
      localState:
        fieldIdentifier:
          name: residuos
          key: cwresi
        stateVariableType: WIDGET_CLASS_STATE
    nodeKeyRef:
      key: Scaffold_4rp06q2q
valueKey: {}
'''

CARD = r'''key: Container_cwst2card
type: Container
props:
  container:
    dimensions:
      width:
        percentOfContainingWidgetValue:
          inputValue: 100
    boxDecoration:
      borderRadius:
        type: FF_BORDER_RADIUS_ALL
        allValue:
          inputValue: 12
      border:
        top:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
        right:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
        bottom:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
        left:
          sizeValue:
            inputValue: 1
          colorValue:
            inputValue:
              themeColor: ALTERNATE
      boxShape: FF_BOX_SHAPE_RECTANGLE
      colorValue:
        inputValue:
          themeColor: SECONDARY_BACKGROUND
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 12
name: CardResiduoWizard
valueKey: {}
'''

CARD_ROW = r'''key: Row_cwst2cr
type: Row
props:
  row:
    mainAxisAlignment: main_axis_space_between
    crossAxisAlignment: cross_axis_center
    listSpacing:
      spacingValue:
        inputValue: 8
name: Row
'''

CARD_COL = r'''key: Column_cwst2cc
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_start
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 4
    minSizeValue:
      inputValue: true
  expanded:
    expandedType: EXPANDED
    flexValue:
      inputValue: 1
name: Column
'''

TXT_NOME = r'''key: Text_cwst2cn
type: Text
props:
  text:
    themeStyle: BODY_LARGE
    selectable: false
    textValue:
      variable:
        source: GENERATOR_VARIABLE
        defaultValue:
          serializedValue: ""
        baseVariable:
          generatorVariable: {}
        operations:
          - accessDataStructField:
              fieldIdentifier:
                name: nome
                key: cdf01
        nodeKeyRef:
          key: ListView_cwst2lv
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    fontWeightValue:
      inputValue: w600
name: NomeResiduo
valueKey: {}
'''

TXT_DET = r'''key: Text_cwst2cd
type: Text
props:
  text:
    themeStyle: BODY_SMALL
    selectable: false
    textValue:
      variable:
        source: FUNCTION_CALL
        functionCall:
          stringInterpolation: {}
          values:
            - variable:
                source: GENERATOR_VARIABLE
                baseVariable:
                  generatorVariable: {}
                operations:
                  - accessDataStructField:
                      fieldIdentifier:
                        name: detalhe
                        key: cdf02
                nodeKeyRef:
                  key: ListView_cwst2lv
            - inputValue:
                serializedValue: " - "
            - variable:
                source: GENERATOR_VARIABLE
                baseVariable:
                  generatorVariable: {}
                operations:
                  - accessDataStructField:
                      fieldIdentifier:
                        name: quantidade
                        key: cdf03
                nodeKeyRef:
                  key: ListView_cwst2lv
    colorValue:
      inputValue:
        themeColor: SECONDARY_TEXT
name: DetalheResiduo
valueKey: {}
'''

ICON_DEL = r'''key: Icon_cwst2cx
type: Icon
props:
  icon:
    sizeValue:
      inputValue: 24
    colorValue:
      inputValue:
        themeColor: ERROR
    iconDataValue:
      inputValue:
        codePoint: 58340
        family: MaterialIcons
        matchTextDirection: false
        name: delete_outline_rounded
name: Icon
'''

# Rebuild Column_cwst2root children in outline — only the list section change via outline patch
# We push the full page outline (required when changing children). Will be loaded at runtime.


ACT_CWSTEP2 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: currentStep
        key: cwstep
      setValue:
        inputValue:
          serializedValue: "2"
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwstep
'''

ACT_CWSTEP3 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: currentStep
        key: cwstep
      setValue:
        inputValue:
          serializedValue: "3"
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwstp3
'''

ACT_CWSTEP4 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: currentStep
        key: cwstep
      setValue:
        inputValue:
          serializedValue: "4"
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwstp4
'''

ACT_CWPREV = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: currentStep
        key: cwstep
      setValue:
        variable:
          source: FUNCTION_CALL
          functionCall:
            values:
              - variable:
                  source: LOCAL_STATE
                  baseVariable:
                    localState:
                      fieldIdentifier:
                        name: currentStep
                        key: cwstep
                      stateVariableType: WIDGET_CLASS_STATE
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
            codeExpression:
              arguments:
                - identifier:
                    name: step
                  dataType:
                    scalarType: Integer
                    nonNullable: true
              returnParameter:
                dataType:
                  scalarType: Integer
                  nonNullable: true
              expression: "step > 1 ? step - 1 : 1"
              valid: true
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwprev
'''

CONT_TRIGGER = r'''rootAction:
  key: cwcontroot
  conditionActions:
    trueActions:
      - condition:
          variable:
            source: FUNCTION_CALL
            functionCall:
              values:
                - variable:
                    source: LOCAL_STATE
                    baseVariable:
                      localState:
                        fieldIdentifier:
                          name: currentStep
                          key: cwstep
                        stateVariableType: WIDGET_CLASS_STATE
                    nodeKeyRef:
                      key: Scaffold_4rp06q2q
                - inputValue:
                    serializedValue: "1"
              condition:
                relation: EQUAL_TO
        trueAction:
          key: cwcont1
          action:
            key: actcwtrn
          followUpAction:
            key: cwcont2
            action:
              key: actcwstep
      - condition:
          variable:
            source: FUNCTION_CALL
            functionCall:
              values:
                - variable:
                    source: LOCAL_STATE
                    baseVariable:
                      localState:
                        fieldIdentifier:
                          name: currentStep
                          key: cwstep
                        stateVariableType: WIDGET_CLASS_STATE
                    nodeKeyRef:
                      key: Scaffold_4rp06q2q
                - inputValue:
                    serializedValue: "2"
              condition:
                relation: EQUAL_TO
        trueAction:
          key: cwcont3
          action:
            key: actcwstp3
      - condition:
          variable:
            source: FUNCTION_CALL
            functionCall:
              values:
                - variable:
                    source: LOCAL_STATE
                    baseVariable:
                      localState:
                        fieldIdentifier:
                          name: currentStep
                          key: cwstep
                        stateVariableType: WIDGET_CLASS_STATE
                    nodeKeyRef:
                      key: Scaffold_4rp06q2q
                - inputValue:
                    serializedValue: "3"
              condition:
                relation: EQUAL_TO
        trueAction:
          key: cwcont4
          action:
            key: actcwstp4
    hasMultiConditions: true
    key: cwcontcond
trigger:
  triggerType: ON_TAP
'''

VOLTAR_TRIGGER = r'''rootAction:
  key: cwbackroot
  conditionActions:
    trueActions:
      - condition:
          variable:
            source: FUNCTION_CALL
            functionCall:
              values:
                - variable:
                    source: LOCAL_STATE
                    baseVariable:
                      localState:
                        fieldIdentifier:
                          name: currentStep
                          key: cwstep
                        stateVariableType: WIDGET_CLASS_STATE
                    nodeKeyRef:
                      key: Scaffold_4rp06q2q
                - inputValue:
                    serializedValue: "1"
              condition:
                relation: EQUAL_TO
        trueAction:
          key: cwback1
          action:
            key: actcwback
    falseAction:
      key: cwback2
      action:
        key: actcwprev
    hasMultiConditions: false
    key: cwbackcond
trigger:
  triggerType: ON_TAP
'''

ACT_CWBACK = r'''navigate:
  allowBack: true
  isNavigateBack: true
  replaceRoute: false
key: actcwback
'''


def load_outline() -> str:
    """Fetch current outline and swap static residue cards for ListView."""
    import base64
    import io
    import zipfile as zf

    url = (
        "https://api.flutterflow.io/v2/projectYamls"
        f"?projectId={PROJECT}&fileNames=page/id-Scaffold_4rp06q2q/page-widget-tree-outline"
    )
    req = urllib.request.Request(
        url, headers={"Authorization": f"Bearer {token()}"}, method="GET"
    )
    with urllib.request.urlopen(req, timeout=120) as resp:
        data = json.loads(resp.read().decode())
    raw = base64.b64decode(data["value"]["project_yaml_bytes"])
    z = zf.ZipFile(io.BytesIO(raw))
    old = zf.ZipExtFile._update_crc
    zf.ZipExtFile._update_crc = lambda self, data, eof=None: None  # type: ignore
    try:
        text = z.open("page/id-Scaffold_4rp06q2q/page-widget-tree-outline.yaml").read().decode(
            "utf-8", "replace"
        )
    finally:
        zf.ZipExtFile._update_crc = old  # type: ignore

    old_block = """                          - key: Text_cwst2lst
                          - key: Container_cwst2r1
                            children:
                              - key: Row_cwst2r1r
                                children:
                                  - key: Column_cwst2r1c
                                    children:
                                      - key: Text_cwst2r1n
                                      - key: Text_cwst2r1d
                                  - key: Icon_cwst2r1x
                          - key: Container_cwst2r2
                            children:
                              - key: Row_cwst2r2r
                                children:
                                  - key: Column_cwst2r2c
                                    children:
                                      - key: Text_cwst2r2n
                                      - key: Text_cwst2r2d
                                  - key: Icon_cwst2r2x
"""
    new_block = """                          - key: Text_cwst2lst
                          - key: ListView_cwst2lv
                            children:
                              - key: Container_cwst2card
                                children:
                                  - key: Row_cwst2cr
                                    children:
                                      - key: Column_cwst2cc
                                        children:
                                          - key: Text_cwst2cn
                                          - key: Text_cwst2cd
                                      - key: Icon_cwst2cx
"""
    if old_block not in text:
        raise SystemExit("outline residue block not found — aborting outline patch")
    return text.replace(old_block, new_block)


def main() -> None:
    print("=== data struct + parsers (official API) ===")
    push(
        {
            "data-structs/id-cdditm1": DATA_STRUCT,
            "custom-functions/id-cddp01": CDDP_META,
            "custom-functions/id-cddp01/function-code": CDDP_CODE,
            "custom-functions/id-wtipn01": WTIPN_META,
            "custom-functions/id-wtipn01/function-code": WTIPN_CODE,
            "custom-functions/id-wtipi01": WTIPI_META,
            "custom-functions/id-wtipi01/function-code": WTIPI_CODE,
        }
    )

    outline = load_outline()
    print("=== page meta + init + list + actions ===")
    push(
        {
            "page/id-Scaffold_4rp06q2q": PAGE_META,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline": outline,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE": ON_INIT,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw002": ACT_CW002,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw003": ACT_CW003,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw004": ACT_CW004,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-DropDown_cwst2tip": DROP_TIP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst2add/trigger_actions/id-ON_TAP": ADD_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst2add/trigger_actions/id-ON_TAP/action/id-actcwadd": ACT_CWADD,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst2add/trigger_actions/id-ON_TAP/action/id-actcwaddup": ACT_CWADDUP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-ListView_cwst2lv": LISTVIEW,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cwst2card": CARD,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Row_cwst2cr": CARD_ROW,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Column_cwst2cc": CARD_COL,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_cwst2cn": TXT_NOME,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_cwst2cd": TXT_DET,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx": ICON_DEL,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx/trigger_actions/id-ON_TAP": DEL_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx/trigger_actions/id-ON_TAP/action/id-actcwdel": ACT_CWDEL,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx/trigger_actions/id-ON_TAP/action/id-actcwdelup": ACT_CWDELUP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP": CONT_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwstep": ACT_CWSTEP2,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwstp3": ACT_CWSTEP3,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwstp4": ACT_CWSTEP4,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP": VOLTAR_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP/action/id-actcwback": ACT_CWBACK,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP/action/id-actcwprev": ACT_CWPREV,
        }
    )


if __name__ == "__main__":
    main()

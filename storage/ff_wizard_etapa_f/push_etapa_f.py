#!/usr/bin/env python3
"""Push Etapa F: destinador catalogs + PATCH + finalizar + cancelar."""
from __future__ import annotations

import json
import urllib.request
from pathlib import Path

MCP = Path(r"C:\Users\Meu PC\.cursor\mcp.json")
PROJECT = "well-coletas-by2777"


def token() -> str:
    mcp = json.loads(MCP.read_text(encoding="utf-8"))
    return mcp["mcpServers"]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]


def push(m: dict[str, str]) -> None:
    body = json.dumps({"projectId": PROJECT, "fileKeyToContent": m}, ensure_ascii=False).encode()
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/updateProjectByYaml",
        data=body,
        headers={"Authorization": f"Bearer {token()}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            print("status", resp.status, resp.read().decode()[:400])
    except Exception as e:
        if hasattr(e, "read"):
            print("HTTP", getattr(e, "code", None), e.read().decode("utf-8", "replace")[:4000])
        else:
            raise


WDSSN = r'''identifier:
  name: parseDestinadorNomesFromApi
  key: wdssn01
arguments:
  - identifier:
      name: apiJson
      key: wdssn02
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

WDSSN_CODE = r'''
  final out = <String>[];
  if (apiJson == null || apiJson is! Map) {
    return out;
  }
  final data = Map<String, dynamic>.from(apiJson)['data'];
  if (data is! Map) {
    return out;
  }
  final items = data['items'];
  if (items is! List) {
    return out;
  }
  for (final raw in items) {
    if (raw is! Map) continue;
    final m = Map<String, dynamic>.from(raw);
    final label = m['label']?.toString() ?? m['nome']?.toString() ?? '';
    if (label.isNotEmpty) out.add(label);
  }
  return out;
'''

WDSSI = r'''identifier:
  name: parseDestinadorIdsFromApi
  key: wdssi01
arguments:
  - identifier:
      name: apiJson
      key: wdssi02
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

WDSSI_CODE = r'''
  final out = <int>[];
  if (apiJson == null || apiJson is! Map) {
    return out;
  }
  final data = Map<String, dynamic>.from(apiJson)['data'];
  if (data is! Map) {
    return out;
  }
  final items = data['items'];
  if (items is! List) {
    return out;
  }
  for (final raw in items) {
    if (raw is! Map) continue;
    final id = (Map<String, dynamic>.from(raw)['id'] as num?)?.toInt();
    if (id != null && id > 0) out.add(id);
  }
  return out;
'''

WTRAN = r'''identifier:
  name: parseTratamentosFromApi
  key: wtran01
arguments:
  - identifier:
      name: apiJson
      key: wtran02
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

WTRAN_CODE = r'''
  final out = <String>[];
  if (apiJson == null || apiJson is! Map) {
    return out;
  }
  final data = Map<String, dynamic>.from(apiJson)['data'];
  if (data is! Map) {
    return out;
  }
  final items = data['items'];
  if (items is! List) {
    return out;
  }
  for (final raw in items) {
    final s = raw?.toString() ?? '';
    if (s.isNotEmpty) out.add(s);
  }
  return out;
'''

# PAGE_META with destinador state fields appended — keep all prior fields
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
trigger:
  triggerType: ON_INIT_STATE
'''

ACT_CW005 = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Catalogo Destinadores
      key: wdss01
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
outputVariableName: wizardDestResult
key: actcw005
'''

ACT_CW006 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: destinadorNomes
        key: cwdnom
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
                        name: wizardDestResult
                      actionKeyRef:
                        key: actcw005
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseDestinadorNomesFromApi
              key: wdssn01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
    - fieldIdentifier:
        name: destinadorIds
        key: cwdids
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
                        name: wizardDestResult
                      actionKeyRef:
                        key: actcw005
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseDestinadorIdsFromApi
              key: wdssi01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
    - fieldIdentifier:
        name: dataRecebimento
        key: cwdrec
      setValue:
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: rotaData
                key: ast011
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: Scaffold_4rp06q2q
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcw006
'''

ACT_CW007 = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Catalogo Tratamentos
      key: wtra01
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
outputVariableName: wizardTratResult
key: actcw007
'''

ACT_CW008 = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: tratamentoOpcoes
        key: cwtopc
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
                        name: wizardTratResult
                      actionKeyRef:
                        key: actcw007
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
                  actionComponentKeyRef: {}
            customFunction:
              name: parseTratamentosFromApi
              key: wtran01
          nodeKeyRef:
            key: Scaffold_4rp06q2q
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcw008
'''

DROP_DES = r'''key: DropDown_cwst4des
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
        inputValue: Selecione
        mostRecentInputValue: Selecione
    optionsHasKeyValues: true
    valueType:
      dataType:
        scalarType: Integer
    fillColorValue:
      inputValue:
        themeColor: SECONDARY_BACKGROUND
    elevationValue:
      inputValue: 2
    borderRadiusValue:
      inputValue: 8
    borderColorValue:
      inputValue:
        themeColor: ALTERNATE
    borderWidthValue:
      inputValue: 1
    hidesUnderlineValue:
      inputValue: true
    optionsLabels:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: destinadorNomes
              key: cwdnom
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
    optionsValues:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: destinadorIds
              key: cwdids
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
name: Destinador
'''

DROP_TRA = r'''key: DropDown_cwst4tra
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
        inputValue: Selecione
        mostRecentInputValue: Selecione
    valueType:
      dataType:
        scalarType: String
    fillColorValue:
      inputValue:
        themeColor: SECONDARY_BACKGROUND
    elevationValue:
      inputValue: 2
    borderRadiusValue:
      inputValue: 8
    borderColorValue:
      inputValue:
        themeColor: ALTERNATE
    borderWidthValue:
      inputValue: 1
    hidesUnderlineValue:
      inputValue: true
    optionsLabels:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: tratamentoOpcoes
              key: cwtopc
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
    optionsValues:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: tratamentoOpcoes
              key: cwtopc
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
name: Tratamento
'''

TXT_DATA = r'''key: Text_cwst4dval
type: Text
props:
  text:
    themeStyle: BODY_MEDIUM
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: dataRecebimento
              key: cwdrec
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
name: Text
'''

DEST_TRIGGER = r'''rootAction:
  key: cwdest1
  action:
    key: actcwdest
trigger:
  triggerType: ON_TAP
'''

ACT_CWDEST = r'''database:
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
          name: transportadora_id
          key: tpid01
        inputValue:
          serializedValue: "0"
      - variableIdentifier:
          name: veiculo_id
          key: veic01
        inputValue:
          serializedValue: "0"
      - variableIdentifier:
          name: coletor_id
          key: cltid1
        inputValue:
          serializedValue: "0"
      - variableIdentifier:
          name: motorista_nome
          key: mot001
        inputValue:
          serializedValue: ""
      - variableIdentifier:
          name: data_coleta
          key: dtc001
        inputValue:
          serializedValue: ""
      - variableIdentifier:
          name: hora
          key: hora01
        inputValue:
          serializedValue: ""
      - variableIdentifier:
          name: destinador_id
          key: dest01
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: Integer
          nodeKeyRef:
            key: DropDown_cwst4des
      - variableIdentifier:
          name: tratamento
          key: trt001
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: String
          nodeKeyRef:
            key: DropDown_cwst4tra
      - variableIdentifier:
          name: observacao_destinador
          key: obsdes
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: String
          nodeKeyRef:
            key: TextField_cwst4obs
      - variableIdentifier:
          name: situacao_recebimento
          key: sit001
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
                          scalarType: Boolean
                  nodeKeyRef:
                    key: Switch_cwst4sw
            codeExpression:
              arguments:
                - identifier:
                    name: on
                  dataType:
                    scalarType: Boolean
                    nonNullable: true
              returnParameter:
                dataType:
                  scalarType: String
                  nonNullable: true
              expression: "on ? 'recebido' : 'pendente'"
              valid: true
      - variableIdentifier:
          name: data_recebimento
          key: dtr001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: dataRecebimento
                key: cwdrec
              stateVariableType: WIDGET_CLASS_STATE
          nodeKeyRef:
            key: Scaffold_4rp06q2q
outputVariableName: wizardDestinadorResult
key: actcwdest
'''

FIN_TRIGGER = r'''rootAction:
  key: cwfin1
  action:
    key: actcwdest
  followUpAction:
    key: cwfin2
    action:
      key: actcwfin
    followUpAction:
      key: cwfin3
      action:
        key: actcwnav
trigger:
  triggerType: ON_TAP
'''

ACT_CWFIN = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Finalizar
      key: wfini01
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
outputVariableName: wizardFinalizarResult
key: actcwfin
'''

ACT_CWNAV = r'''navigate:
  allowBack: true
  passedParameters:
    parameterPasses:
      cldtid1:
        paramIdentifier:
          name: coletaId
          key: cldtid1
        variable:
          source: WIDGET_CLASS_PARAMETER
          baseVariable:
            widgetClass:
              paramIdentifier:
                name: coletaId
                key: cwcolid
          nodeKeyRef:
            key: Scaffold_4rp06q2q
    widgetClassNodeKeyRef:
      key: Scaffold_bfclgcqo
  isNavigateBack: false
  replaceRoute: true
  pageNodeKeyRef:
    key: Scaffold_bfclgcqo
key: actcwnav
'''

CAN_TRIGGER = r'''rootAction:
  key: cwcan1
  action:
    key: actcwcan
  followUpAction:
    key: cwcan2
    action:
      key: actcwcanb
trigger:
  triggerType: ON_TAP
'''

ACT_CWCAN = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Cancelar
      key: wcncl1
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
outputVariableName: wizardCancelResult
key: actcwcan
'''

ACT_CWCANB = r'''navigate:
  allowBack: true
  isNavigateBack: true
  replaceRoute: false
key: actcwcanb
'''


def main() -> None:
    print("=== parsers ===")
    push(
        {
            "custom-functions/id-wdssn01": WDSSN,
            "custom-functions/id-wdssn01/function-code": WDSSN_CODE,
            "custom-functions/id-wdssi01": WDSSI,
            "custom-functions/id-wdssi01/function-code": WDSSI_CODE,
            "custom-functions/id-wtran01": WTRAN,
            "custom-functions/id-wtran01/function-code": WTRAN_CODE,
        }
    )
    print("=== destinador + finalizar + cancelar ===")
    push(
        {
            "page/id-Scaffold_4rp06q2q": PAGE_META,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE": ON_INIT,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw005": ACT_CW005,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw006": ACT_CW006,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw007": ACT_CW007,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw008": ACT_CW008,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-DropDown_cwst4des": DROP_DES,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-DropDown_cwst4tra": DROP_TRA,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_cwst4dval": TXT_DATA,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4sav/trigger_actions/id-ON_TAP": DEST_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4sav/trigger_actions/id-ON_TAP/action/id-actcwdest": ACT_CWDEST,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4fin/trigger_actions/id-ON_TAP": FIN_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4fin/trigger_actions/id-ON_TAP/action/id-actcwdest": ACT_CWDEST,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4fin/trigger_actions/id-ON_TAP/action/id-actcwfin": ACT_CWFIN,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4fin/trigger_actions/id-ON_TAP/action/id-actcwnav": ACT_CWNAV,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4can/trigger_actions/id-ON_TAP": CAN_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4can/trigger_actions/id-ON_TAP/action/id-actcwcan": ACT_CWCAN,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4can/trigger_actions/id-ON_TAP/action/id-actcwcanb": ACT_CWCANB,
        }
    )


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""Push Etapa C FF YAML via FlutterFlow official API."""
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


WCRT = r'''identifier:
  name: WellAdmin Coleta Criar
  key: wcrt01
url: "[baseUrl]/coletas"
callType: POST
variables:
  - identifier:
      name: baseUrl
      key: bsurl1
    type: String
    testValue: https://admin.well.eco.br/api/v1
  - identifier:
      name: authToken
      key: tok001
    type: String
    testValue: ""
  - identifier:
      name: cliente_id
      key: cli001
    type: Integer
    testValue: "1"
  - identifier:
      name: data_coleta
      key: dtc001
    type: String
    testValue: ""
  - identifier:
      name: client_uuid
      key: cuu001
    type: String
    testValue: ""
bodyType: JSON
body: "{\n  \"cliente_id\": <cliente_id>,\n  \"data_coleta\": \"<data_coleta>\",\n  \"client_uuid\": \"<client_uuid>\"\n}"
jsonPathDefinitions:
  - identifier:
      name: coletaId
      key: col001
    jsonPath:
      jsonPath: $.data.coleta.id
      returnParameter:
        dataType:
          scalarType: Integer
  - identifier:
      name: clientUuid
      key: cuures
    jsonPath:
      jsonPath: $.data.coleta.client_uuid
      returnParameter:
        dataType:
          scalarType: String
headers:
  - "Authorization: Bearer [authToken]"
  - "Content-Type: application/json"
endpointSettings:
  escapeVariablesInRequestBody: true
parameters:
  - identifier:
      name: baseUrl
      key: bsup01
    variableIdentifier:
      name: baseUrl
      key: bsurl1
  - identifier:
      name: authToken
      key: autp01
    variableIdentifier:
      name: authToken
      key: tok001
  - identifier:
      name: cliente_id
      key: clip01
    variableIdentifier:
      name: cliente_id
      key: cli001
  - identifier:
      name: data_coleta
      key: dtcp01
    variableIdentifier:
      name: data_coleta
      key: dtc001
  - identifier:
      name: client_uuid
      key: cuup01
    variableIdentifier:
      name: client_uuid
      key: cuu001
'''

WTRN = r'''identifier:
  name: WellAdmin Coleta Transporte
  key: wtrn01
url: "[baseUrl]/coletas/[coletaId]/transporte"
callType: PATCH
variables:
  - identifier:
      name: baseUrl
      key: bsurl1
    type: String
    testValue: https://admin.well.eco.br/api/v1
  - identifier:
      name: authToken
      key: tok001
    type: String
    testValue: ""
  - identifier:
      name: coletaId
      key: colid1
    type: Integer
    testValue: "1"
  - identifier:
      name: transportadora_id
      key: tpid01
    type: Integer
    testValue: "0"
  - identifier:
      name: veiculo_id
      key: veic01
    type: Integer
    testValue: "0"
  - identifier:
      name: coletor_id
      key: cltid1
    type: Integer
    testValue: "0"
  - identifier:
      name: motorista_nome
      key: mot001
    type: String
    testValue: ""
  - identifier:
      name: data_coleta
      key: dtc001
    type: String
    testValue: ""
  - identifier:
      name: hora
      key: hora01
    type: String
    testValue: ""
  - identifier:
      name: destinador_id
      key: dest01
    type: Integer
    testValue: "0"
  - identifier:
      name: tratamento
      key: trt001
    type: String
    testValue: ""
  - identifier:
      name: observacao_destinador
      key: obsdes
    type: String
    testValue: ""
  - identifier:
      name: situacao_recebimento
      key: sit001
    type: String
    testValue: pendente
  - identifier:
      name: data_recebimento
      key: dtr001
    type: String
    testValue: ""
bodyType: JSON
body: "{\n  \"transportadora_id\": <transportadora_id>,\n  \"veiculo_id\": <veiculo_id>,\n  \"coletor_id\": <coletor_id>,\n  \"motorista_nome\": \"<motorista_nome>\",\n  \"data_coleta\": \"<data_coleta>\",\n  \"hora\": \"<hora>\",\n  \"destinador_id\": <destinador_id>,\n  \"tratamento\": \"<tratamento>\",\n  \"observacao_destinador\": \"<observacao_destinador>\",\n  \"situacao_recebimento\": \"<situacao_recebimento>\",\n  \"data_recebimento\": \"<data_recebimento>\"\n}"
headers:
  - "Authorization: Bearer [authToken]"
  - "Content-Type: application/json"
endpointSettings:
  escapeVariablesInRequestBody: true
parameters:
  - identifier:
      name: baseUrl
      key: bsup01
    variableIdentifier:
      name: baseUrl
      key: bsurl1
  - identifier:
      name: authToken
      key: autp01
    variableIdentifier:
      name: authToken
      key: tok001
  - identifier:
      name: coletaId
      key: colpar
    variableIdentifier:
      name: coletaId
      key: colid1
  - identifier:
      name: transportadora_id
      key: tpip01
    variableIdentifier:
      name: transportadora_id
      key: tpid01
  - identifier:
      name: veiculo_id
      key: veip01
    variableIdentifier:
      name: veiculo_id
      key: veic01
  - identifier:
      name: coletor_id
      key: cltp01
    variableIdentifier:
      name: coletor_id
      key: cltid1
  - identifier:
      name: motorista_nome
      key: motp01
    variableIdentifier:
      name: motorista_nome
      key: mot001
  - identifier:
      name: data_coleta
      key: dtcp01
    variableIdentifier:
      name: data_coleta
      key: dtc001
  - identifier:
      name: hora
      key: horp01
    variableIdentifier:
      name: hora
      key: hora01
  - identifier:
      name: destinador_id
      key: desp01
    variableIdentifier:
      name: destinador_id
      key: dest01
  - identifier:
      name: tratamento
      key: trtp01
    variableIdentifier:
      name: tratamento
      key: trt001
  - identifier:
      name: observacao_destinador
      key: obdp01
    variableIdentifier:
      name: observacao_destinador
      key: obsdes
  - identifier:
      name: situacao_recebimento
      key: sitp01
    variableIdentifier:
      name: situacao_recebimento
      key: sit001
  - identifier:
      name: data_recebimento
      key: dtrp01
    variableIdentifier:
      name: data_recebimento
      key: dtr001
'''

WTRP = r'''identifier:
  name: WellAdmin Catalogo Transportadoras
  key: wtrp01
url: "[baseUrl]/catalogos/transportadoras"
callType: GET
variables:
  - identifier:
      name: baseUrl
      key: bsurl1
    type: String
    testValue: https://admin.well.eco.br/api/v1
  - identifier:
      name: authToken
      key: tok001
    type: String
    testValue: ""
headers:
  - "Authorization: Bearer [authToken]"
endpointSettings: {}
parameters:
  - identifier:
      name: baseUrl
      key: bsup01
    variableIdentifier:
      name: baseUrl
      key: bsurl1
  - identifier:
      name: authToken
      key: autp01
    variableIdentifier:
      name: authToken
      key: tok001
'''

WDSS = r'''identifier:
  name: WellAdmin Catalogo Destinadores
  key: wdss01
url: "[baseUrl]/catalogos/destinadores"
callType: GET
variables:
  - identifier:
      name: baseUrl
      key: bsurl1
    type: String
    testValue: https://admin.well.eco.br/api/v1
  - identifier:
      name: authToken
      key: tok001
    type: String
    testValue: ""
headers:
  - "Authorization: Bearer [authToken]"
endpointSettings: {}
parameters:
  - identifier:
      name: baseUrl
      key: bsup01
    variableIdentifier:
      name: baseUrl
      key: bsurl1
  - identifier:
      name: authToken
      key: autp01
    variableIdentifier:
      name: authToken
      key: tok001
'''

WREL = r'''identifier:
  name: WellAdmin Coleta Relatorio
  key: wrel01
url: "[baseUrl]/coletas/[coletaId]/relatorio"
callType: POST
variables:
  - identifier:
      name: baseUrl
      key: bsurl1
    type: String
    testValue: https://admin.well.eco.br/api/v1
  - identifier:
      name: authToken
      key: tok001
    type: String
    testValue: ""
  - identifier:
      name: coletaId
      key: colid1
    type: Integer
    testValue: "1"
  - identifier:
      name: relatorio
      key: rel001
    type: String
    testValue: ""
bodyType: JSON
body: "{\n  \"relatorio\": \"<relatorio>\"\n}"
headers:
  - "Authorization: Bearer [authToken]"
  - "Content-Type: application/json"
endpointSettings:
  escapeVariablesInRequestBody: true
parameters:
  - identifier:
      name: baseUrl
      key: bsup01
    variableIdentifier:
      name: baseUrl
      key: bsurl1
  - identifier:
      name: authToken
      key: autp01
    variableIdentifier:
      name: authToken
      key: tok001
  - identifier:
      name: coletaId
      key: colpar
    variableIdentifier:
      name: coletaId
      key: colid1
  - identifier:
      name: relatorio
      key: relp01
    variableIdentifier:
      name: relatorio
      key: rel001
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
'''

# Navigate Coletar → CollectionWizard
ACT_NAV = r'''navigate:
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
                name: coletaCriarResult
              actionKeyRef:
                key: actcolcrt
          operations:
            - apiResponseField:
                responseField: JSON_BODY
            - jsonPathOperation:
                jsonPath: $.data.coleta.id
          nodeKeyRef:
            key: IconButton_rotcol1
          actionComponentKeyRef: {}
    widgetClassNodeKeyRef:
      key: Scaffold_4rp06q2q
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: Scaffold_4rp06q2q
key: actcolnav
'''

ACT_CRT = r'''database:
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
            generatorVariable: {}
          operations:
            - accessDataStructField:
                fieldIdentifier:
                  name: cliente_id
                  key: rpf01
          nodeKeyRef:
            key: ListView_zq2w1nhb
      - variableIdentifier:
          name: data_coleta
          key: dtc001
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: rotaData
                key: ast011
              stateVariableType: APP_STATE
      - variableIdentifier:
          name: client_uuid
          key: cuu001
        inputValue:
          serializedValue: ""
outputVariableName: coletaCriarResult
key: actcolcrt
'''

SCAFFOLD_NODE = r'''key: Scaffold_4rp06q2q
type: Scaffold
props:
  scaffold:
    safeAreaValue:
      inputValue: true
name: CollectionWizard
'''

ON_INIT = r'''rootAction:
  key: cwinit1
  action:
    key: actcw001
  followUpAction:
    key: cwinit2
    action:
      key: actcw002
trigger:
  triggerType: ON_INIT_STATE
'''

ACT_CW001 = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Detalhe
      key: wcgt01
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
outputVariableName: wizardDetalheResult
key: actcw001
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
        name: currentStep
        key: cwstep
      setValue:
        inputValue:
          serializedValue: "1"
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcw002
'''

CONT_TRIGGER = r'''rootAction:
  key: cwcont1
  action:
    key: actcwtrn
  followUpAction:
    key: cwcont2
    action:
      key: actcwstep
trigger:
  triggerType: ON_TAP
'''

ACT_CWTRN = r'''database:
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
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: transportadoraId
                key: cwtrid
              stateVariableType: WIDGET_CLASS_STATE
          nodeKeyRef:
            key: Scaffold_4rp06q2q
      - variableIdentifier:
          name: veiculo_id
          key: veic01
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: veiculoId
                key: cwveid
              stateVariableType: WIDGET_CLASS_STATE
          nodeKeyRef:
            key: Scaffold_4rp06q2q
      - variableIdentifier:
          name: coletor_id
          key: cltid1
        variable:
          source: LOCAL_STATE
          baseVariable:
            localState:
              fieldIdentifier:
                name: userId
                key: ast003
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
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
            key: Scaffold_4rp06q2q
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
'''

ACT_CWSTEP = r'''localStateUpdate:
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

VOLTAR_TRIGGER = r'''rootAction:
  key: cwback1
  action:
    key: actcwback
trigger:
  triggerType: ON_TAP
'''

ACT_CWBACK = r'''navigate:
  allowBack: true
  isNavigateBack: true
  replaceRoute: false
key: actcwback
'''

BTN_VOLTAR = r'''key: Container_9951ewpo
type: Container
props:
  container: {}
  expanded:
    expandedType: EXPANDED
    flexValue:
      inputValue: 1
name: Button
parameterValues:
  parameterPasses:
    design_system_std_button_std_buttonparam_icon_present:
      paramIdentifier:
        name: iconPresent
        key: design_system_std_button_std_buttonparam_icon_present
      inputValue:
        serializedValue: "false"
    design_system_std_button_std_buttonparam_icon_end_present:
      paramIdentifier:
        name: iconEndPresent
        key: design_system_std_button_std_buttonparam_icon_end_present
      inputValue:
        serializedValue: "false"
    design_system_std_button_std_buttonparam_content:
      paramIdentifier:
        name: content
        key: design_system_std_button_std_buttonparam_content
      inputValue:
        serializedValue: Voltar
    design_system_std_button_std_buttonparam_variant:
      paramIdentifier:
        name: variant
        key: design_system_std_button_std_buttonparam_variant
      inputValue:
        serializedValue: outline
    design_system_std_button_std_buttonparam_size:
      paramIdentifier:
        name: size
        key: design_system_std_button_std_buttonparam_size
      inputValue:
        serializedValue: medium
    design_system_std_button_std_buttonparam_full_width:
      paramIdentifier:
        name: fullWidth
        key: design_system_std_button_std_buttonparam_full_width
      inputValue:
        serializedValue: "true"
    design_system_std_button_std_buttonparam_loading:
      paramIdentifier:
        name: loading
        key: design_system_std_button_std_buttonparam_loading
      inputValue:
        serializedValue: "false"
    design_system_std_button_std_buttonparam_disabled:
      paramIdentifier:
        name: disabled
        key: design_system_std_button_std_buttonparam_disabled
      inputValue:
        serializedValue: "false"
  widgetClassNodeKeyRef:
    key: Container_9ts13gxw
componentClassKeyRef:
  key: Container_9ts13gxw
'''


def step_visibility(step: str) -> str:
    return f'''visibility:
    visibleValue:
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
                serializedValue: "{step}"
          condition:
            relation: EQUAL_TO'''


COL_STEP1 = f'''key: Column_3jqm1at4
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_center
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 16
    minSizeValue:
      inputValue: true
  {step_visibility("1")}
name: Column
'''

TTL_STEP1 = f'''key: Text_yxzdjlag
type: Text
props:
  text:
    themeStyle: TITLE_MEDIUM
    selectable: false
    textValue:
      inputValue: Dados do Transporte
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    heightValue:
      inputValue: 1.35
  {step_visibility("1")}
name: Text
'''

COL_STEP2 = f'''key: Column_cwst2root
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_stretch
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 16
    minSizeValue:
      inputValue: true
  {step_visibility("2")}
name: Column
'''

COL_STEP3 = f'''key: Column_cwst3root
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_stretch
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 16
    minSizeValue:
      inputValue: true
  {step_visibility("3")}
name: Column
'''

COL_STEP4 = f'''key: Column_cwst4root
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_stretch
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 16
    minSizeValue:
      inputValue: true
  {step_visibility("4")}
name: Column
'''


def main() -> None:
    batch1 = {
        "api-endpoint/id-wcrt01": WCRT,
        "api-endpoint/id-wtrn01": WTRN,
        "api-endpoint/id-wtrp01": WTRP,
        "api-endpoint/id-wdss01": WDSS,
        "api-endpoint/id-wrel01": WREL,
    }
    print("=== endpoints ===")
    push(batch1)

    batch2 = {
        "page/id-Scaffold_4rp06q2q": PAGE_META,
        "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP/action/id-actcolcrt": ACT_CRT,
        "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP/action/id-actcolnav": ACT_NAV,
    }
    print("=== rota + page meta ===")
    push(batch2)

    batch3 = {
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE": ON_INIT,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw001": ACT_CW001,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Scaffold_4rp06q2q/trigger_actions/id-ON_INIT_STATE/action/id-actcw002": ACT_CW002,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP": CONT_TRIGGER,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwtrn": ACT_CWTRN,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwstep": ACT_CWSTEP,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo": BTN_VOLTAR,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP": VOLTAR_TRIGGER,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP/action/id-actcwback": ACT_CWBACK,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_yxzdjlag": TTL_STEP1,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Column_3jqm1at4": COL_STEP1,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Column_cwst2root": COL_STEP2,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Column_cwst3root": COL_STEP3,
        "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Column_cwst4root": COL_STEP4,
    }
    print("=== wizard init + continuar + voltar + visibility ===")
    push(batch3)


if __name__ == "__main__":
    main()

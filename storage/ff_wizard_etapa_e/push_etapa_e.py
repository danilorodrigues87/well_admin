#!/usr/bin/env python3
"""Push Etapa E: salvar relatorio (JSON) + resumo OK + Continuar step3 salva e avanca."""
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


WASG = r'''identifier:
  name: WellAdmin Coleta Assinatura
  key: wasg01
url: "[baseUrl]/coletas/[coletaId]/assinatura"
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
      name: assinatura
      key: asg001
    type: String
    testValue: ""
bodyType: JSON
body: "{\n  \"assinatura\": \"<assinatura>\"\n}"
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
      name: assinatura
      key: asgp01
    variableIdentifier:
      name: assinatura
      key: asg001
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
'''

REL_TRIGGER = r'''rootAction:
  key: cwrel1
  action:
    key: actcwrel
  followUpAction:
    key: cwrel2
    action:
      key: actcwrelok
trigger:
  triggerType: ON_TAP
'''

ACT_CWREL = r'''database:
  apiCall:
    endpointIdentifier:
      name: WellAdmin Coleta Relatorio
      key: wrel01
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
          name: relatorio
          key: rel001
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: String
          nodeKeyRef:
            key: TextField_cwst3rel
outputVariableName: wizardRelatorioResult
key: actcwrel
'''

ACT_CWRELOK = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: relatorioSalvo
        key: cwrelok
      setValue:
        inputValue:
          serializedValue: "true"
    - fieldIdentifier:
        name: relatorioResumo
        key: cwrelrs
      setValue:
        variable:
          source: FUNCTION_CALL
          functionCall:
            stringInterpolation: {}
            values:
              - inputValue:
                  serializedValue: "Relatorio salvo. Residuos: "
              - variable:
                  source: LOCAL_STATE
                  baseVariable:
                    localState:
                      fieldIdentifier:
                        name: residuos
                        key: cwresi
                      stateVariableType: WIDGET_CLASS_STATE
                  operations:
                    - numberFormat:
                        formatType: CUSTOM
                        customFormat: "0"
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
              - inputValue:
                  serializedValue: " - Motorista: "
              - variable:
                  source: LOCAL_STATE
                  baseVariable:
                    localState:
                      fieldIdentifier:
                        name: userName
                        key: ast002
                      stateVariableType: APP_STATE
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwrelok
'''

# Fix resumo: list length via codeExpression instead of numberFormat on list
ACT_CWRELOK = r'''localStateUpdate:
  updates:
    - fieldIdentifier:
        name: relatorioSalvo
        key: cwrelok
      setValue:
        inputValue:
          serializedValue: "true"
    - fieldIdentifier:
        name: relatorioResumo
        key: cwrelrs
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
                        name: residuos
                        key: cwresi
                      stateVariableType: WIDGET_CLASS_STATE
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
              - variable:
                  source: LOCAL_STATE
                  baseVariable:
                    localState:
                      fieldIdentifier:
                        name: userName
                        key: ast002
                      stateVariableType: APP_STATE
                  nodeKeyRef:
                    key: Scaffold_4rp06q2q
            codeExpression:
              arguments:
                - identifier:
                    name: items
                  dataType:
                    listType:
                      scalarType: DataStruct
                    subType:
                      dataStructIdentifier:
                        name: ColetaResiduoItem
                        key: cdditm1
                - identifier:
                    name: motorista
                  dataType:
                    scalarType: String
                    nonNullable: true
              returnParameter:
                dataType:
                  scalarType: String
                  nonNullable: true
              expression: "'Residuos: ${items?.length ?? 0} - Motorista: $motorista'"
              valid: true
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwrelok
'''

OK_BOX = r'''key: Container_cwst3ok
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
      boxShape: FF_BOX_SHAPE_RECTANGLE
      colorValue:
        inputValue:
          themeColor: SUCCESS
  padding:
    type: FF_PADDING_ALL
    allValue:
      inputValue: 12
  visibility:
    visibleValue:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: relatorioSalvo
              key: cwrelok
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
name: ResumoOk
'''

OK_TXT2 = r'''key: Text_cwst3ok2
type: Text
props:
  text:
    themeStyle: BODY_SMALL
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        baseVariable:
          localState:
            fieldIdentifier:
              name: relatorioResumo
              key: cwrelrs
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: Scaffold_4rp06q2q
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_BACKGROUND
name: Text
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
            key: actcwrel
          followUpAction:
            key: cwcont4b
            action:
              key: actcwrelok
            followUpAction:
              key: cwcont4c
              action:
                key: actcwstp4
    hasMultiConditions: true
    key: cwcontcond
trigger:
  triggerType: ON_TAP
'''


def main() -> None:
    print("=== assinatura endpoint + page state + relatorio ===")
    push(
        {
            "api-endpoint/id-wasg01": WASG,
            "page/id-Scaffold_4rp06q2q": PAGE_META,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst3sav/trigger_actions/id-ON_TAP": REL_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst3sav/trigger_actions/id-ON_TAP/action/id-actcwrel": ACT_CWREL,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst3sav/trigger_actions/id-ON_TAP/action/id-actcwrelok": ACT_CWRELOK,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cwst3ok": OK_BOX,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_cwst3ok2": OK_TXT2,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP": CONT_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwrel": ACT_CWREL,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwrelok": ACT_CWRELOK,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwstp4": ACT_CWSTEP4,
        }
    )


if __name__ == "__main__":
    main()

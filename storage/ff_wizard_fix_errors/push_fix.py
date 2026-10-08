#!/usr/bin/env python3
"""Fix CollectionWizard FF issues: duplicate outputs, residuos nodeKeyRef, Continuar step4."""
from __future__ import annotations

import json
import urllib.request
from pathlib import Path

MCP = Path(r"C:\Users\Meu PC\.cursor\mcp.json")
PROJECT = "well-coletas-by2777"


def token() -> str:
    return json.loads(MCP.read_text(encoding="utf-8"))["mcpServers"]["flutterflow"]["env"][
        "FLUTTERFLOW_API_TOKEN"
    ]


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
            print("status", resp.status, resp.read().decode()[:500])
    except Exception as e:
        if hasattr(e, "read"):
            print("HTTP", getattr(e, "code", None), e.read().decode("utf-8", "replace")[:4000])
        else:
            raise


# --- residuos refresh: GET detalhe after mutation (proven ON_INIT path) ---

ACT_ADD_GT = r'''database:
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
outputVariableName: wizardAddDetalheResult
key: actcwaddgt
'''

ACT_ADD_UP = r'''localStateUpdate:
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
                        name: wizardAddDetalheResult
                      actionKeyRef:
                        key: actcwaddgt
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Button_cwst2add
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

ADD_TRIGGER = r'''rootAction:
  key: cwadd1
  action:
    key: actcwadd
  followUpAction:
    key: cwadd2
    action:
      key: actcwaddgt
    followUpAction:
      key: cwadd3
      action:
        key: actcwaddup
trigger:
  triggerType: ON_TAP
'''

ACT_DEL_GT = r'''database:
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
outputVariableName: wizardDelDetalheResult
key: actcwdelgt
'''

ACT_DEL_UP = r'''localStateUpdate:
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
                        name: wizardDelDetalheResult
                      actionKeyRef:
                        key: actcwdelgt
                  operations:
                    - apiResponseField:
                        responseField: JSON_BODY
                  nodeKeyRef:
                    key: Icon_cwst2cx
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

DEL_TRIGGER = r'''rootAction:
  key: cwdel1
  action:
    key: actcwdel
  followUpAction:
    key: cwdel2
    action:
      key: actcwdelgt
    followUpAction:
      key: cwdel3
      action:
        key: actcwdelup
trigger:
  triggerType: ON_TAP
'''

# --- unique output names ---

ACT_REL_CONT = r'''database:
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
outputVariableName: wizardRelContResult
key: actcwrelc
'''

ACT_DEST_FIN = r'''database:
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
outputVariableName: wizardDestFinResult
key: actcwdestf
'''

ACT_NOOP = r'''waitAction:
  durationMillisValue:
    inputValue: 1
key: actcwnoop
'''

CONT_TRIGGER = r'''rootAction:
  key: cwcontroot
  action:
    key: actcwnoop
  followUpAction:
    key: cwcontcond
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
              key: actcwrelc
            followUpAction:
              key: cwcont4b
              action:
                key: actcwrelok
              followUpAction:
                key: cwcont4c
                action:
                  key: actcwstp4
      falseAction:
        key: cwcont5
        action:
          key: actcwnoop
      hasMultiConditions: true
      key: cwcontck
trigger:
  triggerType: ON_TAP
'''

VOLTAR_TRIGGER = r'''rootAction:
  key: cwbackroot
  action:
    key: actcwnoop
  followUpAction:
    key: cwbackcond
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
      key: cwbackck
trigger:
  triggerType: ON_TAP
'''

FIN_TRIGGER = r'''rootAction:
  key: cwfin1
  action:
    key: actcwdestf
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


def main() -> None:
    print("=== fix residuos refresh + unique outputs + Continuar/Voltar root action ===")
    push(
        {
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst2add/trigger_actions/id-ON_TAP": ADD_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst2add/trigger_actions/id-ON_TAP/action/id-actcwaddgt": ACT_ADD_GT,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst2add/trigger_actions/id-ON_TAP/action/id-actcwaddup": ACT_ADD_UP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx/trigger_actions/id-ON_TAP": DEL_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx/trigger_actions/id-ON_TAP/action/id-actcwdelgt": ACT_DEL_GT,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Icon_cwst2cx/trigger_actions/id-ON_TAP/action/id-actcwdelup": ACT_DEL_UP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP": CONT_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwnoop": ACT_NOOP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cmdqepai/trigger_actions/id-ON_TAP/action/id-actcwrelc": ACT_REL_CONT,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP": VOLTAR_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_9951ewpo/trigger_actions/id-ON_TAP/action/id-actcwnoop": ACT_NOOP,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4fin/trigger_actions/id-ON_TAP": FIN_TRIGGER,
            "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Button_cwst4fin/trigger_actions/id-ON_TAP/action/id-actcwdestf": ACT_DEST_FIN,
        }
    )


if __name__ == "__main__":
    main()

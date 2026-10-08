import json
import urllib.request
from pathlib import Path

TOKEN = json.loads(Path(r"C:\Users\Meu PC\.cursor\mcp.json").read_text(encoding="utf-8"))[
    "mcpServers"
]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]
PROJECT = "well-coletas-by2777"

SET_ACTIVE = """localStateUpdate:
  updates:
    - fieldIdentifier:
        name: activeColetaId
        key: ast005
      setValue:
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
          actionComponentKeyRef: {}
  updateType: WIDGET
  stateVariableType: APP_STATE
key: actnccs05b
"""

NAV = """navigate:
  allowBack: true
  passedParameters:
    parameterPasses:
      cwcolid:
        paramIdentifier:
          name: coletaId
          key: cwcolid
        variable:
          source: LOCAL_STATE
          defaultValue:
            serializedValue: "0"
          baseVariable:
            localState:
              fieldIdentifier:
                name: activeColetaId
                key: ast005
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: Scaffold_nccs01ab
    widgetClassNodeKeyRef:
      key: Scaffold_4rp06q2q
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: Scaffold_4rp06q2q
key: actnccs06
"""

CARD = """rootAction:
  key: nccstap1
  action:
    key: actnccs05
  followUpAction:
    key: nccstap1b
    action:
      key: actnccs05b
    followUpAction:
      key: nccstap2
      action:
        key: actnccs06
trigger:
  triggerType: ON_TAP
"""

ROUTE_SET = """localStateUpdate:
  updates:
    - fieldIdentifier:
        name: activeColetaId
        key: ast005
      setValue:
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
  updateType: WIDGET
  stateVariableType: APP_STATE
key: actcolact
"""

ROUTE_NAV = """navigate:
  allowBack: true
  passedParameters:
    parameterPasses:
      cwcolid:
        paramIdentifier:
          name: coletaId
          key: cwcolid
        variable:
          source: LOCAL_STATE
          defaultValue:
            serializedValue: "0"
          baseVariable:
            localState:
              fieldIdentifier:
                name: activeColetaId
                key: ast005
              stateVariableType: APP_STATE
          nodeKeyRef:
            key: Scaffold_74bj9gpe
    widgetClassNodeKeyRef:
      key: Scaffold_4rp06q2q
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: Scaffold_4rp06q2q
key: actcolnav
"""

ROUTE_TAP = """rootAction:
  key: coltroot1
  action:
    key: actcolcrt
  followUpAction:
    key: coltroot1b
    action:
      key: actcolact
    followUpAction:
      key: coltroot2
      action:
        key: actcolnav
trigger:
  triggerType: ON_TAP
"""

files = {
    "page/id-Scaffold_nccs01ab/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP": CARD,
    "page/id-Scaffold_nccs01ab/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP/action/id-actnccs05b": SET_ACTIVE,
    "page/id-Scaffold_nccs01ab/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP/action/id-actnccs06": NAV,
    "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP": ROUTE_TAP,
    "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP/action/id-actcolact": ROUTE_SET,
    "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP/action/id-actcolnav": ROUTE_NAV,
}

body = json.dumps({"projectId": PROJECT, "fileKeyToContent": files}, ensure_ascii=False).encode()
req = urllib.request.Request(
    "https://api.flutterflow.io/v2/updateProjectByYaml",
    data=body,
    headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(req, timeout=120) as resp:
    print(resp.read().decode()[:300])

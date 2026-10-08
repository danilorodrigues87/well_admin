"""
Fix: wizard null + dropdowns vazios + overflow Continuar.
1) Após criar coleta: grava activeColetaId e navega com esse id
2) ON_INIT: catálogos primeiro, depois detalhe via activeColetaId
3) Header/data com default "" (evita texto 'null')
4) Botão Continuar sem overflow
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
NCCS = "Scaffold_nccs01ab"


def push(files: dict[str, str]) -> None:
    body = json.dumps({"projectId": PROJECT, "fileKeyToContent": files}, ensure_ascii=False).encode()
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/updateProjectByYaml",
        data=body,
        headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            raw = resp.read().decode()
            print("push", resp.status, raw[:300], "n=", len(files))
            if not json.loads(raw).get("success"):
                raise SystemExit(raw)
    except urllib.error.HTTPError as e:
        print("HTTP", e.code, e.read().decode()[:800])
        raise


# --- Card tap: create → set activeColetaId → navigate ---
SET_ACTIVE = f"""localStateUpdate:
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
          actionComponentKeyRef: {{}}
  updateType: APP_STATE
  stateVariableType: APP_STATE
key: actnccs05b
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
            key: {NCCS}
    widgetClassNodeKeyRef:
      key: {WIZARD}
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: {WIZARD}
key: actnccs06
"""

CARD_TAP = """rootAction:
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

# Also update RouteOfTheDay-style: wizard detalhe uses activeColetaId (fallback after param set)
DETALHE = f"""database:
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
            key: {WIZARD}
outputVariableName: wizardDetalheResult
key: actcw001
"""

UPDATE_DETALHE = f"""localStateUpdate:
  updates:
    - fieldIdentifier:
        name: clienteNome
        key: cwcnom
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: ""
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
                jsonPath: $.data.resumo.cliente
          nodeKeyRef:
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: clienteEndereco
        key: cwcend
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: ""
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: transportadoraId
        key: cwtrid
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: "0"
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: veiculoId
        key: cwveid
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: "0"
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: coletorId
        key: cwclid
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: "0"
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: motoristaNome
        key: cwmotn
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: ""
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: dataColeta
        key: cwdcol
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: ""
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
    - fieldIdentifier:
        name: horaColeta
        key: cwhora
      setValue:
        variable:
          source: ACTION_OUTPUTS
          defaultValue:
            serializedValue: ""
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
            key: {WIZARD}
          actionComponentKeyRef: {{}}
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
                    key: {WIZARD}
                  actionComponentKeyRef: {{}}
            customFunction:
              name: parseColetaDetailItemsFromApi
              key: cddp01
          nodeKeyRef:
            key: {WIZARD}
    - fieldIdentifier:
        name: currentStep
        key: cwstep
      setValue:
        inputValue:
          serializedValue: "1"
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcw002
"""

# Catalogs FIRST (dropdowns), then detalhe via activeColetaId
INIT = """rootAction:
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
                        followUpAction:
                          key: cwinit1
                          action:
                            key: actcw001
                          followUpAction:
                            key: cwinit2
                            action:
                              key: actcw002
trigger:
  triggerType: ON_INIT_STATE
"""

HEADER_NOME = f"""key: Text_t4amqzk8
type: Text
props:
  text:
    themeStyle: TITLE_MEDIUM
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        defaultValue:
          serializedValue: ""
        baseVariable:
          localState:
            fieldIdentifier:
              name: clienteNome
              key: cwcnom
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: {WIZARD}
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
  responsiveVisibility: {{}}
parameterValues: {{}}
valueKey: {{}}
"""

HEADER_END = f"""key: Text_6hcel9pv
type: Text
props:
  text:
    themeStyle: LABEL_SMALL
    selectable: false
    textValue:
      variable:
        source: LOCAL_STATE
        defaultValue:
          serializedValue: ""
        baseVariable:
          localState:
            fieldIdentifier:
              name: clienteEndereco
              key: cwcend
            stateVariableType: WIDGET_CLASS_STATE
        nodeKeyRef:
          key: {WIZARD}
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    heightValue:
      inputValue: 1.27
name: Text
"""

BTN_CONTINUAR = """key: Container_cmdqepai
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
        serializedValue: Continuar
    design_system_std_button_std_buttonparam_variant:
      paramIdentifier:
        name: variant
        key: design_system_std_button_std_buttonparam_variant
      inputValue:
        serializedValue: primary
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
"""

FOOTER_PAD = """key: Container_x643ywbl
type: Container
props:
  container: {}
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
name: Container Padding
"""

ROW_FOOTER = """key: Row_cxlggwbg
type: Row
props:
  row:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_center
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 12
    minSizeValue:
      inputValue: false
name: Row
"""

# Route Coletar also set activeColetaId for consistency
ROUTE_SET = f"""localStateUpdate:
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
          actionComponentKeyRef: {{}}
  updateType: APP_STATE
  stateVariableType: APP_STATE
key: actcolact
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

ROUTE_NAV = f"""navigate:
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
      key: {WIZARD}
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: {WIZARD}
key: actcolnav
"""


def main() -> None:
    files = {
        f"page/id-{NCCS}/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP": CARD_TAP,
        f"page/id-{NCCS}/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP/action/id-actnccs05b": SET_ACTIVE,
        f"page/id-{NCCS}/page-widget-tree-outline/node/id-Container_nccscard/trigger_actions/id-ON_TAP/action/id-actnccs06": NAV_WIZARD,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE": INIT,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw001": DETALHE,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-{WIZARD}/trigger_actions/id-ON_INIT_STATE/action/id-actcw002": UPDATE_DETALHE,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Text_t4amqzk8": HEADER_NOME,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Text_6hcel9pv": HEADER_END,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Container_cmdqepai": BTN_CONTINUAR,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Container_x643ywbl": FOOTER_PAD,
        f"page/id-{WIZARD}/page-widget-tree-outline/node/id-Row_cxlggwbg": ROW_FOOTER,
        "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP": ROUTE_TAP,
        "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP/action/id-actcolact": ROUTE_SET,
        "page/id-Scaffold_74bj9gpe/page-widget-tree-outline/node/id-IconButton_rotcol1/trigger_actions/id-ON_TAP/action/id-actcolnav": ROUTE_NAV,
    }
    push(files)
    print("DONE")


if __name__ == "__main__":
    main()

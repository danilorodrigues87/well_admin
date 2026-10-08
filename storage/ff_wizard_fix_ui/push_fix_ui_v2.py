"""
Corrige CollectionWizard (UI):
1) Date/Time picker real (schema Route) + grava dataColeta/horaColeta
2) Header: overflowReplacement ELLIPSIS no nome e endereco
3) Remove visibility do Column pai (etapas 2-4 ficavam ocultas)
4) Continuar: coletaId via activeColetaId + avanca step mesmo apos API
"""
from __future__ import annotations

import json
import urllib.request
from pathlib import Path

PROJECT = "well-coletas-by2777"
TOKEN = json.loads(Path(r"C:\Users\Meu PC\.cursor\mcp.json").read_text(encoding="utf-8"))[
    "mcpServers"
]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]

BASE = "page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node"
BTN = f"{BASE}/id-Container_cmdqepai/trigger_actions/id-ON_TAP"


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
        print("push", resp.status, raw[:400], "files=", len(files))
        data = json.loads(raw)
        if not data.get("success"):
            raise SystemExit(f"push failed: {raw}")


DATE_PICKER = """datePicker:
  pickerType: DATE_TIME_PICKER_TYPE_DATE
  allowPast: true
  defaultDateTime:
    variable:
      source: GLOBAL_PROPERTIES
      baseVariable:
        globalProperties:
          property: CURRENT_TIMESTAMP
  allowFuture: true
  minDateTime:
    inputValue: {}
  maxDateTime: {}
  useDefaultTheme: false
  useCupertinoStyle: false
  headerDateStyle:
    themeStyle: HEADLINE_LARGE
    fontSizeValue:
      inputValue: 32
    fontWeightValue:
      inputValue: w600
  headerBackgroundColor:
    themeColor: PRIMARY
  headerForegroundColor:
    themeColor: INFO
  pickerBackgroundColor:
    themeColor: SECONDARY_BACKGROUND
  pickerForegroundColor:
    themeColor: PRIMARY_TEXT
  selectedDateTimeForegroundColor:
    themeColor: INFO
  selectedDateTimeBackgroundColor:
    themeColor: PRIMARY
  actionButtonForegroundColor:
    themeColor: PRIMARY_TEXT
  cupertinoBackgroundColor:
    themeColor: SECONDARY_BACKGROUND
  cupertinoTextStyle:
    themeStyle: HEADLINE_MEDIUM
    variableColor:
      inputValue:
        color:
          themeColor: PRIMARY_TEXT
  iconSize: 24
  dismissible: true
key: actcwdtpk
"""

TIME_PICKER = """datePicker:
  pickerType: DATE_TIME_PICKER_TYPE_TIME
  allowPast: true
  defaultDateTime:
    variable:
      source: GLOBAL_PROPERTIES
      baseVariable:
        globalProperties:
          property: CURRENT_TIMESTAMP
  allowFuture: true
  minDateTime:
    inputValue: {}
  maxDateTime: {}
  useDefaultTheme: false
  useCupertinoStyle: false
  headerDateStyle:
    themeStyle: HEADLINE_LARGE
    fontSizeValue:
      inputValue: 32
    fontWeightValue:
      inputValue: w600
  headerBackgroundColor:
    themeColor: PRIMARY
  headerForegroundColor:
    themeColor: INFO
  pickerBackgroundColor:
    themeColor: SECONDARY_BACKGROUND
  pickerForegroundColor:
    themeColor: PRIMARY_TEXT
  selectedDateTimeForegroundColor:
    themeColor: INFO
  selectedDateTimeBackgroundColor:
    themeColor: PRIMARY
  actionButtonForegroundColor:
    themeColor: PRIMARY_TEXT
  cupertinoBackgroundColor:
    themeColor: SECONDARY_BACKGROUND
  cupertinoTextStyle:
    themeStyle: HEADLINE_MEDIUM
    variableColor:
      inputValue:
        color:
          themeColor: PRIMARY_TEXT
  iconSize: 24
  dismissible: true
key: actcwtmpk
"""

SET_DATA = """localStateUpdate:
  updates:
    - fieldIdentifier:
        name: dataColeta
        key: cwdcol
      setValue:
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              actionType: DATE_PICKER
          operations:
            - dateTimeFormat:
                format: yyyy-MM-dd
                isCustom: true
          nodeKeyRef:
            key: Container_u1sveh3f
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwdset
"""

SET_HORA = """localStateUpdate:
  updates:
    - fieldIdentifier:
        name: horaColeta
        key: cwhora
      setValue:
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              actionType: DATE_PICKER
          operations:
            - dateTimeFormat:
                format: HH:mm:ss
                isCustom: true
          nodeKeyRef:
            key: Container_7vbmh0y5
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwhset
"""

# Fix transporte: use activeColetaId (ast005), not page param (often 0)
ACT_TRN = """database:
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
            key: Scaffold_4rp06q2q
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
"""

files: dict[str, str] = {}

# --- 1) Header overflow ---
files[f"{BASE}/id-Text_t4amqzk8"] = """key: Text_t4amqzk8
type: Text
props:
  text:
    themeStyle: TITLE_SMALL
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
          key: Scaffold_4rp06q2q
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    maxLinesValue:
      inputValue: 1
    overflowReplacement: ELLIPSIS
name: Text
"""

files[f"{BASE}/id-Text_6hcel9pv"] = """key: Text_6hcel9pv
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
          key: Scaffold_4rp06q2q
      mostRecentInputValue: ""
    colorValue:
      inputValue:
        themeColor: PRIMARY_TEXT
    heightValue:
      inputValue: 1.27
    maxLinesValue:
      inputValue: 2
    overflowReplacement: ELLIPSIS
name: Text
"""

# Ensure header column stays Expanded
files[f"{BASE}/id-Column_dcrnfl8l"] = """key: Column_dcrnfl8l
type: Column
props:
  expanded:
    expandedType: EXPANDED
    flexValue:
      inputValue: 1
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_start
    scrollable: false
    minSizeValue:
      inputValue: true
name: Column
"""

# --- 2) Parent column: SEM visibility (filhos controlam por etapa) ---
files[f"{BASE}/id-Column_yu3ci4m0"] = """key: Column_yu3ci4m0
type: Column
props:
  column:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_center
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 24
    minSizeValue:
      inputValue: true
name: Column
"""

# --- 3) Date picker ---
files[f"{BASE}/id-Container_u1sveh3f/trigger_actions/id-ON_TAP"] = """rootAction:
  key: cwdproot
  action:
    key: actcwdtpk
  followUpAction:
    key: cwdpfollow
    action:
      key: actcwdset
trigger:
  triggerType: ON_TAP
"""
files[f"{BASE}/id-Container_u1sveh3f/trigger_actions/id-ON_TAP/action/id-actcwdtpk"] = DATE_PICKER
files[f"{BASE}/id-Container_u1sveh3f/trigger_actions/id-ON_TAP/action/id-actcwdset"] = SET_DATA

# --- 4) Time picker ---
files[f"{BASE}/id-Container_7vbmh0y5/trigger_actions/id-ON_TAP"] = """rootAction:
  key: cwtproot
  action:
    key: actcwtmpk
  followUpAction:
    key: cwtpfollow
    action:
      key: actcwhset
trigger:
  triggerType: ON_TAP
"""
files[f"{BASE}/id-Container_7vbmh0y5/trigger_actions/id-ON_TAP/action/id-actcwtmpk"] = TIME_PICKER
files[f"{BASE}/id-Container_7vbmh0y5/trigger_actions/id-ON_TAP/action/id-actcwhset"] = SET_HORA

# --- 5) Continuar: PATCH com activeColetaId (para actcwstep rodar) ---
files[f"{BTN}/action/id-actcwtrn"] = ACT_TRN

if __name__ == "__main__":
    push(files)
    print("done")

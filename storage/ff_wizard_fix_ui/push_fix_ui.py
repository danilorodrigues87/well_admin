"""
Corrige CollectionWizard:
1) Date/Time Picker em data e hora
2) Overflow do nome/endereco no header (Expanded + maxLines)
3) Stepper: circulo/label da etapa N ativos quando currentStep > N-1
4) Etapa 1 some ao avancar (visibility currentStep == 1)
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
        print("push", resp.status, raw[:300], "files=", len(files))
        data = json.loads(raw)
        if not data.get("success"):
            raise SystemExit(f"push failed: {raw}")


def cond_color(gt_value: str, active_kind: str, inactive_kind: str) -> str:
    """Build conditional Color binding. kinds: primary|inactive_circle|white|secondary_text|primary_text|alternate."""

    def color_block(kind: str, indent: int) -> str:
        pad = " " * indent
        if kind == "primary":
            body = f"{pad}themeColor: PRIMARY"
        elif kind == "inactive_circle":
            body = (
                f'{pad}value: "4294309365"\n'
                f"{pad}darkModeColor:\n"
                f'{pad}  value: "4281087278"'
            )
        elif kind == "white":
            body = f'{pad}value: "4294967295"'
        elif kind == "secondary_text":
            body = f"{pad}themeColor: SECONDARY_TEXT"
        elif kind == "primary_text":
            body = f"{pad}themeColor: PRIMARY_TEXT"
        elif kind == "alternate":
            body = f"{pad}themeColor: ALTERNATE"
        else:
            raise ValueError(kind)
        return body

    # active branch color: is at 22 spaces -> children at 24
    # else branch color: is at 18 spaces -> children at 20
    active = color_block(active_kind, 24)
    inactive = color_block(inactive_kind, 20)
    return f"""variable:
          source: FUNCTION_CALL
          functionCall:
            conditionalValue:
              ifConditionalValues:
                - condition:
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
                            serializedValue: "{gt_value}"
                      condition:
                        relation: GREATER_THAN
                  value:
                    inputValue:
                      color:
{active}
              elseValue:
                inputValue:
                  color:
{inactive}
              returnParameter:
                dataType:
                  scalarType: Color"""


def step_circle(key: str, gt: str) -> str:
    return f"""key: {key}
type: Container
props:
  container:
    dimensions:
      width:
        pixelsValue:
          inputValue: 28
      height:
        pixelsValue:
          inputValue: 28
    boxDecoration:
      borderRadius:
        type: FF_BORDER_RADIUS_ALL
        allValue:
          inputValue: 9999
      boxShape: FF_BOX_SHAPE_RECTANGLE
      colorValue:
        {cond_color(gt, "primary", "inactive_circle")}
    childAlignment:
      xValue:
        inputValue: 0
      yValue:
        inputValue: 0
name: Container
"""


def step_num(key: str, num: str, gt: str) -> str:
    return f"""key: {key}
type: Text
props:
  text:
    themeStyle: LABEL_SMALL
    selectable: false
    textValue:
      inputValue: "{num}"
    colorValue:
      {cond_color(gt, "white", "secondary_text")}
    heightValue:
      inputValue: 1.27
name: Text
"""


def step_label(key: str, label: str, gt: str, height: bool = True) -> str:
    h = """
    heightValue:
      inputValue: 1.27""" if height else ""
    return f"""key: {key}
type: Text
props:
  text:
    themeStyle: LABEL_SMALL
    selectable: false
    textValue:
      inputValue: {label}
    colorValue:
      {cond_color(gt, "primary_text", "secondary_text")}{h}
name: Text
"""


def step_divider(key: str, gt: str) -> str:
    return f"""key: {key}
type: Divider
props:
  expanded:
    expandedType: EXPANDED
    flexValue:
      inputValue: 1
  divider:
    heightValue:
      inputValue: 16
    thicknessValue:
      inputValue: 2
    colorValue:
      {cond_color(gt, "primary", "alternate")}
    indentValue:
      inputValue: 16
    endIndentValue:
      inputValue: 16
name: Divider
"""


files: dict[str, str] = {}

# --- Header overflow ---
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
  responsiveVisibility: {}
parameterValues: {}
valueKey: {}
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
name: Text
"""

files[f"{BASE}/id-Row_5txqrfd7"] = """key: Row_5txqrfd7
type: Row
props:
  row:
    mainAxisAlignment: main_axis_start
    crossAxisAlignment: cross_axis_center
    scrollable: false
    listSpacing:
      spacingValue:
        inputValue: 16
    minSizeValue:
      inputValue: false
name: Row
"""

# --- Step 1 content visibility ---
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
  visibility:
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
                serializedValue: "1"
          condition:
            relation: EQUAL_TO
name: Column
"""

# --- Stepper visuals (step N active when currentStep > N-1) ---
files[f"{BASE}/id-Container_908zsd0c"] = step_circle("Container_908zsd0c", "1")
files[f"{BASE}/id-Container_k49c15e7"] = step_circle("Container_k49c15e7", "2")
files[f"{BASE}/id-Container_4aasniw7"] = step_circle("Container_4aasniw7", "3")

files[f"{BASE}/id-Text_7nfqyk3a"] = step_num("Text_7nfqyk3a", "2", "1")
files[f"{BASE}/id-Text_rbsv4uv3"] = step_num("Text_rbsv4uv3", "3", "2")
files[f"{BASE}/id-Text_lyqu0yp1"] = step_num("Text_lyqu0yp1", "4", "3")

files[f"{BASE}/id-Text_843zlq29"] = step_label("Text_843zlq29", "Coleta", "1")
files[f"{BASE}/id-Text_7tzqk85f"] = step_label("Text_7tzqk85f", "Relatorio", "2", height=False)
files[f"{BASE}/id-Text_0m8rg49a"] = step_label("Text_0m8rg49a", "Destino", "3")

files[f"{BASE}/id-Divider_27zjzd63"] = step_divider("Divider_27zjzd63", "1")
files[f"{BASE}/id-Divider_68gs6xeq"] = step_divider("Divider_68gs6xeq", "2")
files[f"{BASE}/id-Divider_08a04cjr"] = step_divider("Divider_08a04cjr", "3")

# --- Date picker ---
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

files[f"{BASE}/id-Container_u1sveh3f/trigger_actions/id-ON_TAP/action/id-actcwdtpk"] = """dateTimePicker:
  dateTimeType: DATE
  allowPastDates: true
  allowFutureDates: true
  useDefaultTheme: true
key: actcwdtpk
"""

files[f"{BASE}/id-Container_u1sveh3f/trigger_actions/id-ON_TAP/action/id-actcwdset"] = """localStateUpdate:
  updates:
    - fieldIdentifier:
        name: dataColeta
        key: cwdcol
      setValue:
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: DateTime
          nodeKeyRef:
            key: Container_u1sveh3f
          operations:
            - dateTimeFormat:
                format: yyyy-MM-dd
                isCustom: true
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwdset
"""

# --- Time picker ---
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

files[f"{BASE}/id-Container_7vbmh0y5/trigger_actions/id-ON_TAP/action/id-actcwtmpk"] = """dateTimePicker:
  dateTimeType: TIME
  allowPastDates: true
  allowFutureDates: true
  useDefaultTheme: true
key: actcwtmpk
"""

files[f"{BASE}/id-Container_7vbmh0y5/trigger_actions/id-ON_TAP/action/id-actcwhset"] = """localStateUpdate:
  updates:
    - fieldIdentifier:
        name: horaColeta
        key: cwhora
      setValue:
        variable:
          source: WIDGET_STATE
          baseVariable:
            widgetState:
              returnParameter:
                dataType:
                  scalarType: DateTime
          nodeKeyRef:
            key: Container_7vbmh0y5
          operations:
            - dateTimeFormat:
                format: HH:mm:ss
                isCustom: true
  updateType: WIDGET
  stateVariableType: WIDGET_CLASS_STATE
key: actcwhset
"""

if __name__ == "__main__":
    push(files)
    print("done")

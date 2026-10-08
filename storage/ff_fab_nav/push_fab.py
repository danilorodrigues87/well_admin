import json
import urllib.request
from pathlib import Path

mcp = json.loads(Path(r"C:\Users\Meu PC\.cursor\mcp.json").read_text(encoding="utf-8"))
tok = mcp["mcpServers"]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]
PROJECT = "well-coletas-by2777"

ACT = """navigate:
  allowBack: true
  passedParameters:
    parameterPasses:
      cwcolid:
        paramIdentifier:
          name: coletaId
          key: cwcolid
        inputValue:
          serializedValue: "0"
    widgetClassNodeKeyRef:
      key: Scaffold_4rp06q2q
  isNavigateBack: false
  replaceRoute: false
  pageNodeKeyRef:
    key: Scaffold_4rp06q2q
key: 3rl8vfj9
"""

TRIGGER = """rootAction:
  key: cyct1ud7
  action:
    key: 3rl8vfj9
trigger:
  triggerType: ON_TAP
"""

body = json.dumps(
    {
        "projectId": PROJECT,
        "fileKeyToContent": {
            "page/id-Scaffold_dwij8cyv/page-widget-tree-outline/node/id-FloatingActionButton_3dm55pze/trigger_actions/id-ON_TAP": TRIGGER,
            "page/id-Scaffold_dwij8cyv/page-widget-tree-outline/node/id-FloatingActionButton_3dm55pze/trigger_actions/id-ON_TAP/action/id-3rl8vfj9": ACT,
        },
    },
    ensure_ascii=False,
).encode()

req = urllib.request.Request(
    "https://api.flutterflow.io/v2/updateProjectByYaml",
    data=body,
    headers={"Authorization": f"Bearer {tok}", "Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(req, timeout=120) as resp:
    print(resp.status, resp.read().decode()[:800])

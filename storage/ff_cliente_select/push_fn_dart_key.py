import base64
import io
import json
import urllib.request
import zipfile
from pathlib import Path

TOKEN = json.loads(Path(r"C:\Users\Meu PC\.cursor\mcp.json").read_text(encoding="utf-8"))[
    "mcpServers"
]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]
PROJECT = "well-coletas-by2777"

dart = Path(__file__).with_name("fix_fn_code.py").read_text(encoding="utf-8")
start = dart.find('CODE = r"""') + len('CODE = r"""')
end = dart.find('"""', start)
CODE = dart[start:end]

for key in [
    "custom-functions/id-pclt01/function-code",
    "custom-functions/id-pclt01/function-code.dart",
]:
    body = json.dumps(
        {"projectId": PROJECT, "fileKeyToContent": {key: CODE}},
        ensure_ascii=False,
    ).encode()
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/updateProjectByYaml",
        data=body,
        headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        print("push", key, resp.read().decode()[:120])

# list partitioned names containing pclt
req = urllib.request.Request(
    f"https://api.flutterflow.io/v2/listPartitionedFileNames?projectId={PROJECT}",
    headers={"Authorization": f"Bearer {TOKEN}"},
    method="GET",
)
with urllib.request.urlopen(req, timeout=120) as resp:
    data = json.loads(resp.read().decode())
names = data.get("value") or []
if isinstance(names, dict):
    names = names.get("file_names") or names.get("fileNames") or list(names)
hits = [n for n in names if isinstance(n, str) and "pclt01" in n]
print("partition hits", hits)

# export again
url = f"https://api.flutterflow.io/v2/projectYamls?projectId={PROJECT}&fileNames=custom-functions/id-pclt01/function-code.dart"
req = urllib.request.Request(url, headers={"Authorization": f"Bearer {TOKEN}"}, method="GET")
with urllib.request.urlopen(req, timeout=120) as resp:
    data = json.loads(resp.read().decode())
b64 = (data.get("value") or {}).get("project_yaml_bytes")
raw = base64.b64decode(b64)
with zipfile.ZipFile(io.BytesIO(raw)) as zf:
    for name in zf.namelist():
        if "pclt01" in name and "function-code" in name:
            try:
                text = zf.read(name).decode("utf-8", errors="replace")
                print("OK", name, "len", len(text))
                print(text[:250])
                # check not corrupted yaml wrap
                print("looks_dart", "ClienteColetaItemStruct" in text and "identifier:" not in text[:80])
            except Exception as e:
                print("READ FAIL", name, e)

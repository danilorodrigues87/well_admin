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

CODE = Path(__file__).with_name("fix_fn_code.py").read_text(encoding="utf-8")
start = CODE.find('CODE = r"""') + len('CODE = r"""')
end = CODE.find('"""', start)
dart = CODE[start:end]
print("dart_len", len(dart))

# validate
body = json.dumps(
    {
        "projectId": PROJECT,
        "fileKey": "custom-functions/id-pclt01/function-code",
        "fileContent": dart,
    }
).encode()
req = urllib.request.Request(
    "https://api.flutterflow.io/v2/validateProjectYaml",
    data=body,
    headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(req, timeout=60) as resp:
    print("validate", resp.read().decode()[:400])

# push again
body2 = json.dumps(
    {
        "projectId": PROJECT,
        "fileKeyToContent": {"custom-functions/id-pclt01/function-code": dart},
    },
    ensure_ascii=False,
).encode()
req2 = urllib.request.Request(
    "https://api.flutterflow.io/v2/updateProjectByYaml",
    data=body2,
    headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(req2, timeout=60) as resp:
    print("push", resp.read().decode()[:200])

# export via GET projectYamls
url = (
    "https://api.flutterflow.io/v2/projectYamls"
    f"?projectId={PROJECT}"
    "&fileNames=custom-functions/id-pclt01/function-code"
    "&fileNames=custom-functions/id-wtipn01/function-code"
)
req3 = urllib.request.Request(url, headers={"Authorization": f"Bearer {TOKEN}"}, method="GET")
with urllib.request.urlopen(req3, timeout=120) as resp:
    data = json.loads(resp.read().decode())
val = data.get("value") or {}
b64 = val.get("project_yaml_bytes") or val.get("projectYamlBytes")
if not b64:
    print("no bytes", str(data)[:400])
else:
    raw = base64.b64decode(b64)
    out = Path(__file__).with_name("yaml_export")
    out.mkdir(exist_ok=True)
    zpath = out / "p.zip"
    zpath.write_bytes(raw)
    with zipfile.ZipFile(io.BytesIO(raw)) as zf:
        print("zip members", zf.namelist()[:20])
        for name in zf.namelist():
            if "pclt01" in name or "wtipn01" in name:
                text = zf.read(name).decode("utf-8", errors="replace")
                print("---", name, "len", len(text))
                print(text[:300])

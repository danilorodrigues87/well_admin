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

# re-push only without .dart
CODE = Path(__file__).with_name("fix_fn_code.py").read_text(encoding="utf-8")
start = CODE.find('CODE = r"""') + len('CODE = r"""')
end = CODE.find('"""', start)
dart = CODE[start:end]

body = json.dumps(
    {
        "projectId": PROJECT,
        "fileKeyToContent": {"custom-functions/id-pclt01/function-code": dart},
    },
    ensure_ascii=False,
).encode()
req = urllib.request.Request(
    "https://api.flutterflow.io/v2/updateProjectByYaml",
    data=body,
    headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
    method="POST",
)
with urllib.request.urlopen(req, timeout=60) as resp:
    print("push", resp.read().decode()[:120])

for fname in [
    "custom-functions/id-pclt01/function-code",
    "custom-functions/id-wtipn01/function-code",
]:
    url = f"https://api.flutterflow.io/v2/projectYamls?projectId={PROJECT}&fileNames={fname}"
    req = urllib.request.Request(url, headers={"Authorization": f"Bearer {TOKEN}"}, method="GET")
    with urllib.request.urlopen(req, timeout=120) as resp:
        data = json.loads(resp.read().decode())
    b64 = (data.get("value") or {}).get("project_yaml_bytes")
    raw = base64.b64decode(b64)
    out = Path(__file__).with_name("yaml_export") / (fname.replace("/", "_") + ".zip")
    out.parent.mkdir(exist_ok=True)
    out.write_bytes(raw)
    with zipfile.ZipFile(io.BytesIO(raw)) as zf:
        print("===", fname, "members", [n for n in zf.namelist() if "function" in n or "pclt" in n or "wtip" in n])
        for name in zf.namelist():
            if "function-code" in name:
                info = zf.getinfo(name)
                print("info", name, "size", info.file_size, "compress", info.compress_size)
                try:
                    text = zf.read(name).decode("utf-8", errors="replace")
                    print(text[:200])
                    print("has_struct", "ClienteColetaItemStruct" in text or "String" in text)
                except Exception as e:
                    print("fail", e)
                    # try reading raw
                    with zf.open(name) as fp:
                        chunk = fp.read(200)
                        print("raw", chunk[:100])

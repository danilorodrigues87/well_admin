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

# ASCII-only body to avoid zip/export quirks with special chars
CODE = """
  if (apiJson == null) {
    return [];
  }
  Map<String, dynamic> root;
  if (apiJson is String) {
    root = jsonDecode(apiJson) as Map<String, dynamic>;
  } else if (apiJson is Map) {
    root = Map<String, dynamic>.from(apiJson);
  } else {
    return [];
  }
  final data = root['data'];
  if (data is! Map) {
    return [];
  }
  final items = data['items'];
  if (items is! List) {
    return [];
  }
  final out = <ClienteColetaItemStruct>[];
  for (final raw in items) {
    if (raw is! Map) {
      continue;
    }
    final m = Map<String, dynamic>.from(raw);
    final cidade = m['cidade']?.toString() ?? '';
    final uf = m['uf']?.toString() ?? '';
    final prox = m['proxima_coleta']?.toString() ?? '';
    final prio = m['prioridade']?.toString() ?? '';
    final locParts = <String>[];
    if (cidade.isNotEmpty) {
      locParts.add(cidade);
    }
    if (uf.isNotEmpty) {
      locParts.add(uf);
    }
    final loc = locParts.join('/');
    final bits = <String>[];
    if (loc.isNotEmpty) {
      bits.add(loc);
    }
    if (prox.isNotEmpty) {
      bits.add('Prox: ' + prox);
    }
    if (prio == 'urgente') {
      bits.add('Urgente');
    }
    final idRaw = m['id'];
    final id = idRaw is int ? idRaw : int.tryParse('$idRaw') ?? 0;
    out.add(
      ClienteColetaItemStruct(
        id: id,
        nomeFantasia: m['nome_fantasia']?.toString() ?? '',
        detalhe: bits.join(' | '),
      ),
    );
  }
  return out;
"""

body = json.dumps(
    {
        "projectId": PROJECT,
        "fileKeyToContent": {"custom-functions/id-pclt01/function-code": CODE},
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
    print("push", resp.read().decode()[:120], "code_len", len(CODE))

url = f"https://api.flutterflow.io/v2/projectYamls?projectId={PROJECT}&fileNames=custom-functions/id-pclt01/function-code"
req = urllib.request.Request(url, headers={"Authorization": f"Bearer {TOKEN}"}, method="GET")
with urllib.request.urlopen(req, timeout=120) as resp:
    data = json.loads(resp.read().decode())
raw = base64.b64decode((data.get("value") or {}).get("project_yaml_bytes"))
with zipfile.ZipFile(io.BytesIO(raw)) as zf:
    name = "custom-functions/id-pclt01/function-code.dart"
    info = zf.getinfo(name)
    print("size", info.file_size, "crc", hex(info.CRC))
    text = zf.read(name).decode("utf-8")
    print(text[:220])
    print("OK ClienteColetaItemStruct", "ClienteColetaItemStruct" in text)

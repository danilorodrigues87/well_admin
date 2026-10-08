import json
import urllib.request
from pathlib import Path

TOKEN = json.loads(Path(r"C:\Users\Meu PC\.cursor\mcp.json").read_text(encoding="utf-8"))[
    "mcpServers"
]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]
PROJECT = "well-coletas-by2777"

CODE = r"""
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
    final loc = [if (cidade.isNotEmpty) cidade, if (uf.isNotEmpty) uf].join('/');
    final bits = <String>[];
    if (loc.isNotEmpty) bits.add(loc);
    if (prox.isNotEmpty) bits.add('Prox: $prox');
    if (prio == 'urgente') bits.add('Urgente');
    out.add(
      ClienteColetaItemStruct(
        id: m['id'] is int ? m['id'] as int : int.tryParse('${m['id']}') ?? 0,
        nomeFantasia: m['nome_fantasia']?.toString() ?? '',
        detalhe: bits.join(' · '),
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
    print("push", resp.read().decode()[:200])

# compare with known-good function-code key of wtipn01
for key in [
    "custom-functions/id-pclt01/function-code",
    "custom-functions/id-wtipn01/function-code",
    "custom-functions/id-pclt01",
]:
    b = json.dumps({"projectId": PROJECT, "fileName": key}).encode()
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/getProjectYaml",
        data=b,
        headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as resp:
            raw = resp.read().decode()
            print("GET", key, raw[:180].replace("\n", " | "))
    except Exception as e:
        print("GET", key, "ERR", e)

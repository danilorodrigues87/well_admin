#!/usr/bin/env python3
"""Rebuild fixed wizard payload and push via FlutterFlow API."""
from __future__ import annotations

import json
import re
import urllib.request
from pathlib import Path

BASE = Path(__file__).resolve().parent
MCP = Path(r"C:\Users\Meu PC\.cursor\mcp.json")

CONTAINER_KEYS = {
    "Container_cwst2r1": ("SECONDARY_BACKGROUND", 12, 12, None, None),
    "Container_cwst2r2": ("SECONDARY_BACKGROUND", 12, 12, None, None),
    "Container_cwst2pad": ("PRIMARY_BACKGROUND", 8, 16, 100, 140),
    "Container_cwst3p1": ("SECONDARY_BACKGROUND", 12, 8, 100, 100),
    "Container_cwst3p2": ("SECONDARY_BACKGROUND", 12, 8, 100, 100),
    "Container_cwst3p3": ("SECONDARY_BACKGROUND", 12, 8, 100, 100),
    "Container_cwst3ok": ("SECONDARY_BACKGROUND", 12, 12, None, None),
    "Container_cwst4dat": ("SECONDARY_BACKGROUND", 8, None, 100, 48),
}

PHOTO_EXPANDED = {"Container_cwst3p1", "Container_cwst3p2", "Container_cwst3p3"}
OK_BORDER = {"Container_cwst3ok"}  # SUCCESS border color
PAD_NAME = {
    "Container_cwst2r1": "Card",
    "Container_cwst2r2": "Card",
    "Container_cwst2pad": "AssinaturaPad",
    "Container_cwst3p1": "PhotoSlot",
    "Container_cwst3p2": "PhotoSlot",
    "Container_cwst3p3": "PhotoSlot",
    "Container_cwst3ok": "ResumoOk",
    "Container_cwst4dat": "DataEncerramento",
}


def quote_if_needed(val: str) -> str:
    if any(c in val for c in (":", "#", "{", "}", "[", "]", ",", "&", "*", "!", "|", ">", "%", "@", "`")):
        escaped = val.replace('"', '\\"')
        return f'"{escaped}"'
    if val == "" or val.lower() in {"true", "false", "null"}:
        return f'"{val}"'
    return val


def make_container(key: str) -> str:
    color, radius, pad_all, width_pct_or_px, height = CONTAINER_KEYS[key]
    border_color = "SUCCESS" if key in OK_BORDER else "ALTERNATE"
    name = PAD_NAME[key]

    dims = []
    if width_pct_or_px is not None:
        if key.startswith("Container_cwst3p") or key == "Container_cwst4dat" and height == 48:
            if key.startswith("Container_cwst3p"):
                dims.append(
                    "      width:\n"
                    "        pixelsValue:\n"
                    f"          inputValue: {width_pct_or_px}"
                )
            else:
                dims.append(
                    "      width:\n"
                    "        percentOfContainingWidgetValue:\n"
                    "          inputValue: 100"
                )
        else:
            dims.append(
                "      width:\n"
                "        percentOfContainingWidgetValue:\n"
                "          inputValue: 100"
            )
    else:
        dims.append(
            "      width:\n"
            "        percentOfContainingWidgetValue:\n"
            "          inputValue: 100"
        )
    if height is not None:
        dims.append(
            "      height:\n"
            "        pixelsValue:\n"
            f"          inputValue: {height}"
        )

    # recreate dimensions cleanly
    if key.startswith("Container_cwst3p"):
        dimensions = (
            "    dimensions:\n"
            "      width:\n"
            "        pixelsValue:\n"
            "          inputValue: 100\n"
            "      height:\n"
            "        pixelsValue:\n"
            "          inputValue: 100\n"
        )
    elif key == "Container_cwst2pad":
        dimensions = (
            "    dimensions:\n"
            "      width:\n"
            "        percentOfContainingWidgetValue:\n"
            "          inputValue: 100\n"
            "      height:\n"
            "        pixelsValue:\n"
            "          inputValue: 140\n"
        )
    elif key == "Container_cwst4dat":
        dimensions = (
            "    dimensions:\n"
            "      width:\n"
            "        percentOfContainingWidgetValue:\n"
            "          inputValue: 100\n"
            "      height:\n"
            "        pixelsValue:\n"
            "          inputValue: 48\n"
        )
    else:
        dimensions = (
            "    dimensions:\n"
            "      width:\n"
            "        percentOfContainingWidgetValue:\n"
            "          inputValue: 100\n"
        )

    if key == "Container_cwst4dat":
        padding = (
            "  padding:\n"
            "    type: FF_PADDING_ONLY\n"
            "    leftValue:\n"
            "      inputValue: 16\n"
            "    rightValue:\n"
            "      inputValue: 16\n"
        )
    else:
        padding = (
            "  padding:\n"
            "    type: FF_PADDING_ALL\n"
            "    allValue:\n"
            f"      inputValue: {pad_all}\n"
        )

    expanded = ""
    if key in PHOTO_EXPANDED:
        expanded = (
            "  expanded:\n"
            "    expandedType: EXPANDED\n"
            "    flexValue:\n"
            "      inputValue: 1\n"
        )

    return (
        f"key: {key}\n"
        "type: Container\n"
        "props:\n"
        "  container:\n"
        f"{dimensions}"
        "    boxDecoration:\n"
        "      colorValue:\n"
        "        inputValue:\n"
        f"          themeColor: {color}\n"
        "      borderRadius:\n"
        "        type: FF_BORDER_RADIUS_ALL\n"
        "        allValue:\n"
        f"          inputValue: {radius}\n"
        "      border:\n"
        "        top:\n"
        "          sizeValue:\n"
        "            inputValue: 1\n"
        "          colorValue:\n"
        "            inputValue:\n"
        f"              themeColor: {border_color}\n"
        "        right:\n"
        "          sizeValue:\n"
        "            inputValue: 1\n"
        "          colorValue:\n"
        "            inputValue:\n"
        f"              themeColor: {border_color}\n"
        "        bottom:\n"
        "          sizeValue:\n"
        "            inputValue: 1\n"
        "          colorValue:\n"
        "            inputValue:\n"
        f"              themeColor: {border_color}\n"
        "        left:\n"
        "          sizeValue:\n"
        "            inputValue: 1\n"
        "          colorValue:\n"
        "            inputValue:\n"
        f"              themeColor: {border_color}\n"
        "      boxShape: FF_BOX_SHAPE_RECTANGLE\n"
        f"{padding}"
        f"{expanded}"
        f"name: {name}\n"
    )


def sanitize_text_yaml(y: str) -> str:
    def repl(m: re.Match[str]) -> str:
        val = m.group(1)
        # unquoted scalar — quote if needed
        if val.startswith('"') or val.startswith("'"):
            return m.group(0)
        return f"inputValue: {quote_if_needed(val)}"

    # only top-level-ish textValue inputValue lines without nested structure
    return re.sub(r"inputValue:\s+([^\\\n][^\n]*)", repl, y)


def load_original_nodes() -> dict[str, str]:
    """Merge original batches (pre-broken) + updated tree."""
    nodes: dict[str, str] = {}
    # Prefer expanded folders / mcp_args which were built before broken fix
    for i in range(7):
        f = BASE / f"mcp_args_{i}.json"
        d = json.loads(f.read_text(encoding="utf-8"))
        for k, v in d["fileKeyToContent"].items():
            if k.endswith("page-widget-tree-outline") or k == "page/id-Scaffold_4rp06q2q":
                continue
            nodes[k] = v
    return nodes


def main() -> None:
    nodes = load_original_nodes()
    tree = (BASE / "tree_outline.yaml").read_text(encoding="utf-8")
    assert "Container_dgb8eww2" in tree
    assert "BottomNav" not in tree

    # Replace container YAMLs with schema-correct versions
    for short in CONTAINER_KEYS:
        full = f"page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-{short}"
        nodes[full] = make_container(short)

    # Quote risky text inputValues (colons etc.)
    for k, v in list(nodes.items()):
        if "/node/id-Text_" in k:
            # manually fix known bad lines
            v2 = v
            for bad, good in [
                (
                    "inputValue: Residuos: 2 · Motorista: Joao · Fotos: 1",
                    'inputValue: "Residuos: 2 - Motorista: Joao - Fotos: 1"',
                ),
                (
                    "inputValue: Residuos: 2 - Motorista: Joao - Fotos: 1",
                    'inputValue: "Residuos: 2 - Motorista: Joao - Fotos: 1"',
                ),
                (
                    "inputValue: Relatorio salvo — confira antes de concluir",
                    'inputValue: "Relatorio salvo - confira antes de concluir"',
                ),
                (
                    "inputValue: Classe A · 12,5 kg",
                    'inputValue: "Classe A - 12,5 kg"',
                ),
                (
                    "inputValue: Classe E · 2 un",
                    'inputValue: "Classe E - 2 un"',
                ),
                (
                    "inputValue: 2. Coleta — Residuos",
                    'inputValue: "2. Coleta - Residuos"',
                ),
                (
                    "inputValue: 3. Relatorio / Evidencias",
                    'inputValue: "3. Relatorio / Evidencias"',
                ),
                (
                    "inputValue: 4. Destinador",
                    'inputValue: "4. Destinador"',
                ),
            ]:
                v2 = v2.replace(bad, good)
            # also replace middle dots leftover
            v2 = v2.replace("·", "-").replace("—", "-")
            nodes[k] = v2

    # Ensure DropDowns keep borderRadiusValue (not borderRadius)
    for k, v in list(nodes.items()):
        if "DropDown_" in k and "borderRadius:" in v and "borderRadiusValue:" not in v:
            # shouldn't happen from originals
            pass

    fk = dict(nodes)
    fk["page/id-Scaffold_4rp06q2q/page-widget-tree-outline"] = tree

    out = {
        "projectId": "well-coletas-by2777",
        "fileKeyToContent": fk,
    }
    out_path = BASE / "push_all_fixed.json"
    out_path.write_text(json.dumps(out, ensure_ascii=False), encoding="utf-8")
    print("keys", len(fk), "bytes", out_path.stat().st_size)
    print("container_sample")
    print(nodes["page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cwst2r1"])
    print("text_ok2")
    print(nodes["page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_cwst3ok2"])

    mcp = json.loads(MCP.read_text(encoding="utf-8"))
    token = mcp["mcpServers"]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]
    body = json.dumps(out).encode("utf-8")
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/updateProjectByYaml",
        data=body,
        headers={
            "Authorization": f"Bearer {token}",
            "Content-Type": "application/json",
        },
        method="POST",
    )
    try:
        with urllib.request.urlopen(req, timeout=180) as resp:
            raw = resp.read().decode("utf-8")
            print("status", resp.status)
            print(raw[:2000])
    except Exception as e:
        if hasattr(e, "read"):
            err = e.read().decode("utf-8", errors="replace")
            print("HTTP_ERROR", getattr(e, "code", None))
            print(err[:5000])
        else:
            raise


if __name__ == "__main__":
    main()

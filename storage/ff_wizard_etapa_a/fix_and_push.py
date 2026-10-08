#!/usr/bin/env python3
"""Fix Container/Text YAML schema issues and push via FlutterFlow API."""
from __future__ import annotations

import json
import re
import urllib.request
from pathlib import Path

BASE = Path(__file__).resolve().parent
MCP = Path(r"C:\Users\Meu PC\.cursor\mcp.json")


def fix_yaml(y: str) -> str:
    y = (
        y.replace("\u00b7", "-")
        .replace("·", "-")
        .replace("—", "-")
        .replace("–", "-")
        .replace("â€”", "-")
        .replace("Â·", "-")
    )

    def repl_br(m: re.Match[str]) -> str:
        indent, val = m.group(1), m.group(2)
        return (
            f"borderRadius:\n{indent}type: FF_BORDER_RADIUS_ALL\n"
            f"{indent}allValue:\n{indent}  inputValue: {val}"
        )

    y = re.sub(
        r"borderRadiusValue:\n(\s+)inputValue:\s*(\d+)",
        repl_br,
        y,
    )

    def repl_ba(m: re.Match[str]) -> str:
        base = m.group(1)
        size = m.group(4)
        color = m.group(8)
        side = (
            f"sizeValue:\n{base}  inputValue: {size}\n"
            f"{base}colorValue:\n{base}  inputValue:\n"
            f"{base}    themeColor: {color}"
        )
        return (
            f"border:\n{base}top:\n{base}  {side}\n"
            f"{base}right:\n{base}  {side}\n"
            f"{base}bottom:\n{base}  {side}\n"
            f"{base}left:\n{base}  {side}"
        )

    y = re.sub(
        r"border:\n(\s+)all:\n(\s+)sizeValue:\n(\s+)inputValue:\s*(\d+)\n"
        r"(\s+)colorValue:\n(\s+)inputValue:\n(\s+)themeColor:\s*(\w+)",
        repl_ba,
        y,
    )
    return y


def main() -> None:
    payload_path = BASE / "push_all_with_tree.json"
    d = json.loads(payload_path.read_text(encoding="utf-8"))
    fk = d["fileKeyToContent"]

    fixed = 0
    for k, v in list(fk.items()):
        nv = fix_yaml(v)
        if nv != v:
            fk[k] = nv
            fixed += 1

    tree = fk["page/id-Scaffold_4rp06q2q/page-widget-tree-outline"]
    assert "Container_dgb8eww2" in tree
    assert "Column_cwst4root" in tree
    assert "BottomNav" not in tree

    payload_path.write_text(json.dumps(d, ensure_ascii=False), encoding="utf-8")
    print("fixed_files", fixed)
    print("sample_container")
    print(fk["page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Container_cwst2r1"])
    print("sample_text")
    print(fk["page/id-Scaffold_4rp06q2q/page-widget-tree-outline/node/id-Text_cwst3ok2"])

    mcp = json.loads(MCP.read_text(encoding="utf-8"))
    token = mcp["mcpServers"]["flutterflow"]["env"]["FLUTTERFLOW_API_TOKEN"]
    body = json.dumps(d).encode("utf-8")
    print("keys", len(fk), "bytes", len(body))

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
            print(err[:4000])
        else:
            raise


if __name__ == "__main__":
    main()

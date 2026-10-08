"""Validate key YAMLs then push all."""
from __future__ import annotations

import importlib
import json
import urllib.request

import push_fix_ui as m

importlib.reload(m)

TOKEN = m.TOKEN
PROJECT = m.PROJECT


def validate(file_key: str, content: str) -> None:
    body = json.dumps(
        {"projectId": PROJECT, "fileKey": file_key, "fileContent": content},
        ensure_ascii=False,
    ).encode()
    req = urllib.request.Request(
        "https://api.flutterflow.io/v2/validateProjectYaml",
        data=body,
        headers={"Authorization": f"Bearer {TOKEN}", "Content-Type": "application/json"},
        method="POST",
    )
    with urllib.request.urlopen(req, timeout=90) as resp:
        data = json.loads(resp.read().decode())
    err = data.get("value")
    short = file_key.rsplit("/", 1)[-1]
    if err:
        raise SystemExit(f"VALIDATE FAIL {short}: {err}")
    print("OK", short)


def main() -> None:
    samples = [
        x
        for x in m.files
        if any(
            s in x
            for s in (
                "908zsd0c",
                "Text_7nfqyk3a",
                "Divider_27zjzd63",
                "Text_843zlq29",
                "Column_yu3ci4m0",
                "Text_t4amqzk8",
                "Column_dcrnfl8l",
                "actcwdtpk",
                "actcwdset",
                "actcwtmpk",
                "actcwhset",
            )
        )
    ]
    # also ON_TAP triggers (not nested under /action/)
    samples += [
        x
        for x in m.files
        if x.endswith("/trigger_actions/id-ON_TAP")
    ]
    for k in samples:
        validate(k, m.files[k])
    print("all samples valid, pushing", len(m.files), "files")
    m.push(m.files)
    print("done")


if __name__ == "__main__":
    main()

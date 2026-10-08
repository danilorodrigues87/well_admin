import push_fix_ui as m

k = [x for x in m.files if "908zsd0c" in x][0]
for i, line in enumerate(m.files[k].splitlines()):
    if any(s in line for s in ("color", "themeColor", 'value: "429', "elseValue", "PRIMARY", "darkMode")):
        spaces = len(line) - len(line.lstrip(" "))
        print(f"{i:3} sp={spaces:2} [{line}]")

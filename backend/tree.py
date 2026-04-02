#!/usr/bin/env python3

import sys
import subprocess
import tempfile
import os

if len(sys.argv) < 2:
    print("Error: No input file")
    sys.exit(1)

input_file = sys.argv[1]

# 输出文件
output_file = tempfile.NamedTemporaryFile(delete=False, suffix=".nwk").name

try:
    # ✅ 使用 FastTree 生成 Newick
    cmd = [
        "fasttree",
        input_file
    ]

    with open(output_file, "w") as out:
        subprocess.run(cmd, stdout=out, stderr=subprocess.DEVNULL, check=True)

    with open(output_file, "r") as f:
        tree = f.read().strip()

    print(tree)

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)

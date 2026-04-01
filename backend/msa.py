#!/usr/bin/env python3

import sys
import subprocess
import os
import tempfile

if len(sys.argv) < 2:
    print("Error: No input file")
    sys.exit(1)

input_fasta = sys.argv[1]

# 创建临时输出文件
output_file = tempfile.NamedTemporaryFile(delete=False, suffix=".aln").name

try:
    # 调用 Clustal Omega
    cmd = [
        "clustalo",
        "-i", input_fasta,
        "-o", output_file,
        "--force",
        "--outfmt=clu"
    ]

    subprocess.run(cmd, check=True)

    # 读取结果
    with open(output_file, "r") as f:
        alignment = f.read()

    print(alignment)

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)


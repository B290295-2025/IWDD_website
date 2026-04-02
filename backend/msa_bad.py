#!/usr/bin/env python3

import sys
import subprocess
import os
import tempfile
from Bio import AlignIO
import json
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

# -----------------------------
    # 2. 输出 alignment（保持原功能）
    # -----------------------------
    with open(output_file, "r") as f:
        alignment_text = f.read()

    print(alignment_text)


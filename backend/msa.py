#!/usr/bin/env python3

import sys
import subprocess
import os
import tempfile
import json
from Bio import AlignIO

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

    # 保持现有功能：先输出 alignment 文本
    print(alignment)

    # 新增：计算 conservation score
    scores = []
    try:
        aln = AlignIO.read(output_file, "clustal")
        aln_len = aln.get_alignment_length()

        for i in range(aln_len):
            column = aln[:, i]
            counts = {}
            valid = 0

            for aa in column:
                if aa != '-':
                    counts[aa] = counts.get(aa, 0) + 1
                    valid += 1

            if valid == 0:
                scores.append(0.0)
            else:
                max_freq = max(counts.values())
                scores.append(round(max_freq / valid, 3))
    except Exception:
        scores = []

    # 用标记包起来，方便 PHP 提取，不会破坏你现有 alignment 渲染
    print("###SCORES_JSON_START###")
    print(json.dumps(scores))
    print("###SCORES_JSON_END###")

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)

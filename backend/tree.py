#!/usr/bin/env python3
import sys
import subprocess

input_file = sys.argv[1]

output_file = input_file + ".aln"

# 直接用 clustalo 生成树
cmd = [
    "clustalo",
    "-i", input_file,
    "--guidetree-out=" + output_file,
    "--force"
]

subprocess.run(cmd)

with open(output_file) as f:
    print(f.read())

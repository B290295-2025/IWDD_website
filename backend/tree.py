#!/usr/bin/env python3
import os
import sys
import json
import tempfile

# 关键：在 import matplotlib 前设置可写目录
os.environ["MPLCONFIGDIR"] = tempfile.mkdtemp(prefix="mplcfg_")

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt

from Bio import AlignIO, Phylo
from Bio.Phylo.TreeConstruction import DistanceCalculator, DistanceTreeConstructor

if len(sys.argv) < 2:
    print(json.dumps({"error": "No input file"}))
    sys.exit(1)

file = sys.argv[1]

try:
    try:
        aln = AlignIO.read(file, "fasta")
    except Exception:
        aln = AlignIO.read(file, "clustal")

    calculator = DistanceCalculator('identity')
    dm = calculator.get_distance(aln)

    constructor = DistanceTreeConstructor()
    tree = constructor.nj(dm)

    newick_file = tempfile.NamedTemporaryFile(delete=False, suffix=".nwk").name
    png_file = tempfile.NamedTemporaryFile(delete=False, suffix=".png").name

    Phylo.write(tree, newick_file, "newick")
    with open(newick_file, "r") as f:
        newick = f.read().strip()

    fig = plt.figure(figsize=(10, 6), dpi=180)
    ax = fig.add_subplot(1, 1, 1)
    Phylo.draw(tree, axes=ax, do_show=False)
    plt.tight_layout()
    fig.savefig(png_file, bbox_inches="tight")
    plt.close(fig)

    print(json.dumps({
        "newick": newick,
        "png_file": png_file
    }))

except Exception as e:
    print(json.dumps({"error": str(e)}))
    sys.exit(1)

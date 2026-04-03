#!/usr/bin/env python3

import sys
import subprocess
import tempfile
import json
import itertools
from Bio import AlignIO
from Bio.Align import substitution_matrices

if len(sys.argv) < 2:
    print("Error: No input file")
    sys.exit(1)

input_fasta = sys.argv[1]
output_file = tempfile.NamedTemporaryFile(delete=False, suffix=".aln").name


def compute_blosum62_scores(alignment_path):
    aln = AlignIO.read(alignment_path, "clustal")
    matrix = substitution_matrices.load("BLOSUM62")
    alphabet = set(str(matrix.alphabet))

    matrix_values = []
    for a in alphabet:
        for b in alphabet:
            try:
                matrix_values.append(float(matrix[a, b]))
            except Exception:
                continue

    if not matrix_values:
        return []

    matrix_min = min(matrix_values)
    matrix_max = max(matrix_values)
    denom = matrix_max - matrix_min if matrix_max != matrix_min else 1.0

    scores = []
    aln_len = aln.get_alignment_length()

    for i in range(aln_len):
        column = aln[:, i]
        residues = [aa for aa in column if aa != '-' and aa in alphabet]

        if len(residues) < 2:
            scores.append(0.0)
            continue

        pair_scores = []
        for a, b in itertools.combinations(residues, 2):
            try:
                raw = float(matrix[a, b])
                normalized = (raw - matrix_min) / denom
                pair_scores.append(normalized)
            except Exception:
                continue

        if not pair_scores:
            scores.append(0.0)
        else:
            scores.append(round(sum(pair_scores) / len(pair_scores), 3))

    return scores


try:
    cmd = [
        "clustalo",
        "-i", input_fasta,
        "-o", output_file,
        "--force",
        "--outfmt=clu"
    ]
    subprocess.run(cmd, check=True)

    with open(output_file, "r") as f:
        alignment = f.read()

    print(alignment)

    try:
        scores = compute_blosum62_scores(output_file)
    except Exception:
        scores = []

    print("###SCORES_JSON_START###")
    print(json.dumps(scores))
    print("###SCORES_JSON_END###")

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)

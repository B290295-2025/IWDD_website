from Bio import AlignIO
from Bio.Phylo.TreeConstruction import DistanceCalculator, DistanceTreeConstructor
from Bio import Phylo
import sys

file = sys.argv[1]

# 自动识别格式
try:
    aln = AlignIO.read(file, "fasta")
except:
    aln = AlignIO.read(file, "clustal")

calculator = DistanceCalculator('identity')
dm = calculator.get_distance(aln)

constructor = DistanceTreeConstructor()
tree = constructor.nj(dm)

Phylo.write(tree, sys.stdout, "newick")

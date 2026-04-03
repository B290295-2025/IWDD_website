#!/usr/bin/env python3

import sys
import time
from Bio import Entrez

Entrez.email = "s2845297@ed.ac.uk"
Entrez.api_key = "da5f2b2bc8dfd014bbd15a6760b20c082608"

#  define parameters
if len(sys.argv) < 3:
    print("Error: Missing parameters")
    sys.exit(1)

taxon = sys.argv[1]
protein = sys.argv[2]

# setup query
query = f'({protein}[All Fields]) AND ({taxon}[All Fields])'

# 
time.sleep(1)

try:
    # esearch
    handle = Entrez.esearch(
        db="protein",
        term=query,
        retmax=150
    )
    record = Entrez.read(handle)
    handle.close()

    ids = record["IdList"]

    if not ids:
        print("Error: No sequences found")
        sys.exit(1)

    # e fetch fasta file
    handle = Entrez.efetch(
        db="protein",
        id=",".join(ids),
        rettype="fasta",
        retmode="text"
    )

    fasta_data = handle.read()
    handle.close()

    print(fasta_data)

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)

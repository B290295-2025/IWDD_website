#!/usr/bin/env python3

import sys
import time
from Bio import Entrez
# 设置邮箱（必需）和可选 API Key
Entrez.email = "s2845297@ed.ac.uk"  
Entrez.api_key = "da5f2b2bc8dfd014bbd15a6760b20c082608"  # 
# 获取 taxon 参数
if len(sys.argv) < 2:
    print("Error: No taxon provided")
    sys.exit(1)

taxon = sys.argv[1].lower()

# 查询模板
queries = {
    "aves": "glucose-6-phosphatase[Protein Name] AND Aves[Organism]",
    "mammals": "ABC transporter[Protein Name] AND Mammalia[Organism]",
    "rodents": "kinase[Protein Name] AND Rodentia[Organism]",
    "vertebrates": "adenylyl cyclase[Protein Name] AND Vertebrata[Organism]"
}

if taxon not in queries:
    print("Error: Invalid taxon")
    sys.exit(1)

query = queries[taxon]

# 延时避免被NCBI限制
time.sleep(2)

try:
    # Step 1: 搜索蛋白
    handle = Entrez.esearch(db="protein", term=query, retmax=10)  # 返回前10条
    record = Entrez.read(handle)
    handle.close()
    ids = record["IdList"]

    if not ids:
        print("Error: No sequences found")
        sys.exit(1)

    # Step 2: 获取 FASTA
    handle = Entrez.efetch(db="protein", id=",".join(ids), rettype="fasta", retmode="text")
    fasta_data = handle.read()
    handle.close()

    print(fasta_data)

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)

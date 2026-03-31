#!/usr/bin/env python3

import sys
import subprocess
import time
API_KEY = "da5f2b2bc8dfd014bbd15a6760b20c082608"

# 获取用户输入的 taxon
if len(sys.argv) < 2:
    print("Error: No taxon provided")
    sys.exit(1)

taxon = sys.argv[1].lower()

# 不同 taxon 对应的查询
queries = {
    "aves": "glucose-6-phosphatase[Protein Name] AND Aves[Organism]",
    "mammals": "ABC transporter[Protein Name] AND Mammalia[Organism]",
    "rodents":"kinase[Protein Name] AND Rodentia[Organism]",
    "vertebrates": "adenylyl cyclase[Protein Name] AND Vertebrata[Organism]"
}

# 检查 taxon 是否有效
if taxon not in queries:
    print("Error: Invalid taxon")
    sys.exit(1)

query = queries[taxon]

time.sleep(1)

# 构建命令（限制返回前10条序列）
cmd = f'''
/home/s2845297/edirect/esearch -db protein -query "{query}" -api_key {API_KEY} | /home/s2845297/edirect/efetch -format fasta -api_key {API_KEY}
'''
result = subprocess.getoutput(cmd)

try:
    # 执行命令
    result = subprocess.getoutput(cmd)

    # 如果没有结果
    if not result.strip():
        print("Error: No sequences found")
        sys.exit(1)

    # 输出 FASTA
    print(result)

except Exception as e:
    print(f"Error: {str(e)}")
    sys.exit(1)

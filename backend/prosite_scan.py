#!/usr/bin/env python3
import sys
import json
import re
import os

def convert_prosite_to_regex(pattern):
    """
    最严谨的 PROSITE 转 Python 正则逻辑
    """
    p = pattern.upper().replace('-', '')
    # 处理 {ABC} -> [^ABC]
    p = re.sub(r'\{([A-Z]+)\}', r'[^\1]', p)
    # 处理 (2,3) -> {2,3}
    p = p.replace('(', '{').replace(')', '}')
    # 处理 x -> .
    p = p.replace('X', '.')
    # 处理 < 和 >
    p = p.replace('<', '^').replace('>', '$')
    return p

def scan_prosite(sequence):
    results = []
    # 针对 Glucose-6-phosphatase (你的蛋白) 增加其特征基序
    # 课程项目自定义 G6P motif；避免误用官方 PS accession
    motifs = [
        {"id": "CUSTOM_G6PASE", "name": "G6P_PHOSPHATASE", "pattern": "K-W-x-R-P-G-R-T", "desc": "Custom project motif for glucose-6-phosphatase"},
        {"id": "PS00001", "name": "ASN_GLYCOSYLATION", "pattern": "N-{P}-[ST]-{P}", "desc": "N-glycosylation site"},
        {"id": "PS00005", "name": "PKC_PHOSPHO", "pattern": "[ST]-x-[RK]", "desc": "PKC phosphorylation site"},
        {"id": "PS00006", "name": "CK2_PHOSPHO", "pattern": "[ST]-x(2)-[DE]", "desc": "CK2 phosphorylation site"},
        {"id": "TEST01", "name": "ALL_ALANINE", "pattern": "A-A-A", "desc": "Triple Alanine (Test)"}
    ]

    for motif in motifs:
        try:
            reg_str = convert_prosite_to_regex(motif['pattern'])
            # 使用 (?=(...)) 捕获重叠匹配
            for m in re.finditer(f"(?=({reg_str}))", sequence):
                match_str = m.group(1)
                start = m.start() + 1
                results.append({
                    "accession": motif['id'],
                    "name": motif['name'],
                    "description": motif['desc'],
                    "start": start,
                    "end": start + len(match_str) - 1,
                    "match": match_str
                })
        except:
            continue
    return results

if __name__ == "__main__":
    try:
        if len(sys.argv) < 3:
            print(json.dumps([]))
            sys.exit(0)

        acc = sys.argv[1]
        file_path = sys.argv[2]

        # 从临时文件中读取序列
        if os.path.exists(file_path):
            with open(file_path, 'r') as f:
                sequence = f.read().upper().strip()
            
            # 过滤掉非氨基酸字符（以防万一）
            sequence = re.sub(r'[^A-Z]', '', sequence)
            
            findings = scan_prosite(sequence)
            print(json.dumps(findings))
        else:
            print(json.dumps([{"error": "Temp file not found"}]))
            
    except Exception as e:
        print(json.dumps([{"error": str(e)}]))

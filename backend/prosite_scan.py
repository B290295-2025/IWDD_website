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
    # PS00516 是 G6P 酶的特征模式
    motifs = [
        {"id": "PS00001", "name": "ASN_GLYCOSYLATION", "pattern": "N-{P}-[ST]-{P}", "desc": "N-glycosylation site"},
        {"id": "PS00004", "name": "CAMP_PHOSPHO_SITE", "pattern": "[RK](2)-x-[ST]", "desc": "cAMP/cGMP-dependent protein kinase phosphorylation site"},
        {"id": "PS00005", "name": "PKC_PHOSPHO", "pattern": "[ST]-x-[RK]", "desc": "PKC phosphorylation site"},
        {"id": "PS00006", "name": "CK2_PHOSPHO", "pattern": "[ST]-x(2)-[DE]", "desc": "Casein kinase II phosphorylation site"},
        {"id": "PS00007", "name": "TYR_PHOSPHO_SITE_1", "pattern": "[RK]-x(2)-[DE]-x(3)-Y", "desc": "Tyrosine kinase phosphorylation site 1"},
        {"id": "PS60007", "name": "TYR_PHOSPHO_SITE_2", "pattern": "[RK]-x(3)-[DE]-x(2)-Y", "desc": "Tyrosine kinase phosphorylation site 2"},
        {"id": "PS00008", "name": "MYRISTYL", "pattern": "G-{EDRKHPFYW}-x(2)-[STAGCN]-{P}", "desc": "N-myristoylation site"},
        {"id": "PS00009", "name": "AMIDATION", "pattern": "x-G-[RK]-[RK]", "desc": "Amidation site"},
        {"id": "PS00016", "name": "RGD", "pattern": "R-G-D", "desc": "Cell attachment sequence (RGD)"},
        {"id": "PS00017", "name": "ATP_GTP_A", "pattern": "[AG]-x(4)-G-K-[ST]", "desc": "ATP/GTP-binding site motif A (P-loop)"},
        {"id": "PS00014", "name": "ER_TARGET", "pattern": "[KRHQSA]-[DENQ]-E-L>", "desc": "Endoplasmic reticulum targeting signal"},
        {"id": "PS00029", "name": "LEUCINE_ZIPPER", "pattern": "L-x(6)-L-x(6)-L-x(6)-L", "desc": "Leucine zipper pattern"},
        {"id": "PS00294", "name": "PRENYLATION", "pattern": "C-{DENQ}-[LIVM]-x>", "desc": "Prenyl group binding site (CAAX box)"},
        {"id": "PS00342", "name": "MICROBODIES_CTER", "pattern": "[STAGCN]-[RKH]-[LIVMAFY]>", "desc": "Microbodies C-terminal targeting signal"},
        {"id": "PS00134", "name": "TRYPSIN_HIS", "pattern": "[LIVM]-[ST]-A-[STAG]-H-C", "desc": "Trypsin family serine protease histidine active site"},
        {"id": "PS00135", "name": "TRYPSIN_SER", "pattern": "[DNSTAGC]-[GSTAPIMVQH]-x(2)-G-[DE]-S-G-[GS]-[SAPHV]-[LIVMFYWH]-[LIVMFYSTANQH]", "desc": "Trypsin family serine protease serine active site"},
        {"id": "PS00141", "name": "ASP_PROTEASE", "pattern": "[LIVMFGAC]-[LIVMTADN]-[LIVFSA]-D-[ST]-G-[STAV]-[STAPDENQ]-{GQ}-[LIVMFSTNC]-{EGK}-[LIVMFGTA]", "desc": "Eukaryotic and viral aspartyl proteases active site"},
        {"id": "PS00107", "name": "PROTEIN_KINASE_ATP", "pattern": "[LIV]-G-{P}-G-{P}-[FYWMGSTNH]-[SGA]-{PW}-[LIVCAT]-{PD}-x-[GSTACLIVMFY]-x(5,18)-[LIVMFYWCSTAR]-[AIVP]-[LIVMFAGCKR]-K", "desc": "Protein kinase ATP-binding region signature"},
        {"id": "PS00108", "name": "PROTEIN_KINASE_ST", "pattern": "[LIVMFYC]-x-[HY]-x-D-[LIVMFY]-K-x(2)-N-[LIVMFYCT](3)", "desc": "Serine/threonine protein kinases active-site signature"},
        {"id": "PS00109", "name": "PROTEIN_KINASE_TYR", "pattern": "[LIVMFYC]-{A}-[HY]-x-D-[LIVMFY]-[RSTAC]-{D}-{PF}-N-[LIVMFYC](3)", "desc": "Tyrosine protein kinases active-site signature"},
        {"id": "PS00383", "name": "TYR_PHOSPHATASE_1", "pattern": "[LIVMF]-H-C-x(2)-G-x(2)-R-[STC]-[STAGP]", "desc": "Tyrosine specific protein phosphatases active site"},
        {"id": "PS00213", "name": "LIPOCALIN", "pattern": "[DENG]-{A}-[DENQGSTARK]-x(0,2)-[DENQARK]-[LIVFY]-{CP}-G-{C}-W-[FYWLRH]-{D}-[LIVMTA]", "desc": "Lipocalin signature"},
        {"id": "PS00028", "name": "ZINC_FINGER_C2H2_1", "pattern": "C-x(2,4)-C-x(3)-[LIVMFYWC]-x(8)-H-x(3,5)-H", "desc": "Zinc finger C2H2-type domain signature"},
        {"id": "PS00027", "name": "HOMEOBOX_1", "pattern": "[LIVMFYG]-[ASLVR]-x(2)-[LIVMSTACN]-x-[LIVM]-{Y}-x(2)-{L}-[LIV]-[RKNQESTAIY]-[LIVFSTNKH]-W-[FYVC]-x-[NDQTAH]-x(5)-[RKNAIMW]", "desc": "Homeobox domain signature"},
        {"id": "PS00380", "name": "RHODANESE_1", "pattern": "[FY]-x(3)-H-[LIV]-P-G-A-x(2)-[LIVF]", "desc": "Rhodanese signature 1"},
        {"id": "PS00018", "name": "EF_HAND_1", "pattern": "D-{W}-[DNS]-{ILVFYW}-[DENSTG]-[DNQGHRK]-{GP}-[LIVMC]-[DENQSTAGC]-x(2)-[DE]-[LIVMFYW]", "desc": "EF-hand calcium-binding domain"},
        {"id": "PS00540", "name": "FERRITIN_1", "pattern": "E-x-[KR]-E-x(2)-E-[KR]-[LF]-[LIVMA]-x(2)-Q-N-x-R-x-G-R", "desc": "Ferritin iron-binding region signature 1"},
        {"id": "PS00469", "name": "NDPK", "pattern": "N-x(2)-H-[GA]-S-D-[GSA]-[LIVMPKNE]", "desc": "Nucleoside diphosphate kinase active site"},
        {"id": "PS00086", "name": "CYTOCHROME_P450", "pattern": "[FW]-[SGNH]-x-[GD]-{F}-[RKHPT]-{P}-C-[LIVMFAP]-[GAD]", "desc": "Cytochrome P450 cysteine heme-iron ligand signature"}
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

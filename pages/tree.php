<?php
$selected_ids = $_POST['selected'] ?? [];

$conn = new PDO("mysql:host=127.0.0.1;dbname=s2845297_website", "s2845297", "YuQ1LiN030709!");

$placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

$stmt = $conn->prepare(
    "SELECT accession_id, sequence FROM protein_data
     WHERE accession_id IN ($placeholders)"
);

$stmt->execute($selected_ids);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

$fasta = '';
foreach ($data as $r) {
    $fasta .= ">" . $r['accession_id'] . "\n";
    $fasta .= $r['sequence'] . "\n";
}

// 1️⃣ 写 FASTA
$fasta_file = "/tmp/tree_" . uniqid() . ".fasta";
file_put_contents($fasta_file, $fasta);

// 2️⃣ 先做 MSA（关键！）
$msa_cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/msa.py " . $fasta_file;
$msa_output = shell_exec($msa_cmd);

// 🔥 提取 alignment 文件（重新跑一次 clustal 输出 fasta）
$aln_file = "/tmp/aln_" . uniqid() . ".fasta";

exec("clustalo -i $fasta_file -o $aln_file --force --outfmt=fasta");

// 3️⃣ 再构建 tree
$cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/tree.py $aln_file 2>&1";
$newick = trim(shell_exec($cmd));

// 清理
$newick = str_replace(["\n", "\r"], '', $newick);
$history = $conn->prepare(
    "INSERT INTO analysis_history (selected_ids, action)
     VALUES (?, 'TREE')"
);

?>
<?php ob_start(); ?>

<!DOCTYPE html>
<html>
<head>
<title>Tree Visualization</title>
<link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>

<body>

<div class="page-container">

<h2>Phylogenetic Tree</h2>
<div id="tree"></div>

<script src="https://unpkg.com/phylotree@1.0.0/dist/phylotree.js"></script>

<script>
let newick = <?= json_encode($newick) ?>;

document.addEventListener("DOMContentLoaded", function () {

    let tree = new phylotree.phylotree(newick);

    tree.render("#tree");

});
</script>
<pre><?= htmlspecialchars($newick) ?></pre>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>


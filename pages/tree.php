<?php
$selected_ids = $_POST['selected'] ?? [];

if (count($selected_ids) < 2) {
    die("Need at least 2 sequences");
}

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

$file = "/tmp/tree_" . uniqid() . ".fasta";
file_put_contents($file, $fasta);

$cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/tree.py $file 2>&1";
$output = shell_exec($cmd);
?>

<?php ob_start(); ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>Phylogenetic Tree</title>
<link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>
<div id="tree"></div>

<script src="https://cdn.jsdelivr.net/npm/phylotree@1.0.0/phylotree.min.js"></script>

<script>
let newick = `<?= trim($output) ?>`;

let tree = new phylotree.phylotree(newick);

tree.render("#tree");
</script>
</body>
</html>

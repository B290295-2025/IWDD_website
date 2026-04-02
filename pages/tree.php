<?php
$selected_ids = $_POST['selected'] ?? [];

$conn = new PDO(
    "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4",
    "s2845297",
    "YuQ1LiN030709!",
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$newick = '';
$tree_png_base64 = '';
$error = '';
$download_png_file = '';

if (isset($_GET['download_png'])) {
    $file = "/tmp/" . basename($_GET['download_png']);

    if (file_exists($file)) {
        header('Content-Type: image/png');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    } else {
        echo "File not found";
        exit;
    }
}

if (count($selected_ids) >= 2) {
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

    $fasta_file = "/tmp/tree_" . uniqid() . ".fasta";
    file_put_contents($fasta_file, $fasta);

    $aln_file = "/tmp/aln_" . uniqid() . ".fasta";
    $clustalo_cmd = "clustalo -i " . escapeshellarg($fasta_file) . " -o " . escapeshellarg($aln_file) . " --force --outfmt=fasta 2>&1";
    $clustalo_output = shell_exec($clustalo_cmd);

    if (!file_exists($aln_file) || filesize($aln_file) == 0) {
        $error = "Alignment failed: " . $clustalo_output;
    } else {
        $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/tree.py " . escapeshellarg($aln_file) . " 2>&1";
        $json_output = shell_exec($cmd);
        $tree_data = json_decode($json_output, true);

        if (!is_array($tree_data) || isset($tree_data['error'])) {
            $error = is_array($tree_data) ? $tree_data['error'] : $json_output;
        } else {
            $newick = $tree_data['newick'] ?? '';
            $png_file = $tree_data['png_file'] ?? '';

            if ($png_file && file_exists($png_file)) {
                $tree_png_base64 = base64_encode(file_get_contents($png_file));

                $download_png_name = "tree_" . time() . ".png";
                copy($png_file, "/tmp/" . $download_png_name);
                $download_png_file = $download_png_name;
            }
        }
    }

    $history = $conn->prepare(
        "INSERT INTO analysis_history (selected_ids, action)
         VALUES (?, 'TREE')"
    );
    $history->execute([implode(',', $selected_ids)]);
} else {
    $error = "Please select at least 2 sequences.";
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Tree Visualization</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>Phylogenetic Tree</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php else: ?>
        <?php if ($tree_png_base64): ?>
            <?php if (!empty($download_png_file)): ?>
                <div style="margin-top:20px;">
                    <a href="tree.php?download_png=<?= urlencode($download_png_file) ?>" class="download-btn">
                        Save Tree Image
                    </a>
                </div>
            <?php endif; ?>

            <div style="margin-top:20px; background:#ffffff; padding:12px; border-radius:6px;">
                <img src="data:image/png;base64,<?= $tree_png_base64 ?>" alt="Phylogenetic Tree" style="max-width:100%; height:auto;">
            </div>
        <?php endif; ?>

        <?php if ($newick): ?>
            <h3>Newick Format</h3>
            <pre style="white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere;"><?= htmlspecialchars($newick) ?></pre>
        <?php endif; ?>
    <?php endif; ?>
</div>

</body>
</html>

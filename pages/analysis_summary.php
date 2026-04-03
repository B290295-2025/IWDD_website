<?php
$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";
$dataset_name = 'aves_g6pase_example';

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$datasetStmt = $conn->prepare(
    "SELECT accession_id, seq_length
     FROM example_dataset
     WHERE dataset_name = ?
     ORDER BY display_order ASC"
);
$datasetStmt->execute([$dataset_name]);
$rows = $datasetStmt->fetchAll();

$resultStmt = $conn->prepare(
    "SELECT summary_report, tree_png_base64
     FROM example_results
     WHERE dataset_name = ?
     ORDER BY id DESC
     LIMIT 1"
);
$resultStmt->execute([$dataset_name]);
$result = $resultStmt->fetch();

$summary = json_decode($result['summary_report'] ?? '[]', true);
if (!is_array($summary)) {
    $summary = [];
}

$tree_png_base64 = $result['tree_png_base64'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Example Summary Report</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>Example Summary Report</h2>

    <a href="example.php" class="back-button">← Back to Example</a>

    <div class="card">
        <h3><?= htmlspecialchars($summary['dataset'] ?? 'Example dataset') ?></h3>
        <p><strong>Taxon:</strong> <?= htmlspecialchars($summary['query_taxon'] ?? 'Aves') ?></p>
        <p><strong>Protein:</strong> <?= htmlspecialchars($summary['query_protein'] ?? 'glucose-6-phosphatase') ?></p>
        <p>This report summarises the pre-processed example workflow for the coursework website.</p>
    </div>

    <h3>Dataset Overview</h3>
    <table class="result-table">
        <tbody>
            <tr>
                <th>Number of sequences</th>
                <td><?= htmlspecialchars((string)($summary['sequence_count'] ?? 0)) ?></td>
            </tr>
            <tr>
                <th>Minimum sequence length</th>
                <td><?= htmlspecialchars((string)($summary['length_min'] ?? 0)) ?></td>
            </tr>
            <tr>
                <th>Maximum sequence length</th>
                <td><?= htmlspecialchars((string)($summary['length_max'] ?? 0)) ?></td>
            </tr>
            <tr>
                <th>Average sequence length</th>
                <td><?= htmlspecialchars((string)($summary['length_avg'] ?? 0)) ?></td>
            </tr>
        </tbody>
    </table>

    <h3>MSA and Conservation Summary</h3>
    <table class="result-table">
        <tbody>
            <tr>
                <th>Alignment length</th>
                <td><?= htmlspecialchars((string)($summary['alignment_length'] ?? 0)) ?></td>
            </tr>
            <tr>
                <th>Average conservation score</th>
                <td><?= htmlspecialchars((string)($summary['average_conservation'] ?? 0)) ?></td>
            </tr>
            <tr>
                <th>Highly conserved positions</th>
                <td><?= htmlspecialchars((string)($summary['high_sites'] ?? 0)) ?></td>
            </tr>
            <tr>
                <th>Interpretation</th>
                <td><?= htmlspecialchars($summary['message'] ?? '') ?></td>
            </tr>
        </tbody>
    </table>

    <h3>Motif Summary</h3>
    <table class="result-table">
        <tbody>
            <tr>
                <th>Total detected motifs</th>
                <td><?= htmlspecialchars((string)($summary['total_motifs'] ?? 0)) ?></td>
            </tr>
        </tbody>
    </table>

    <?php if ($tree_png_base64): ?>
        <h3>Tree Preview</h3>
        <div style="background:#ffffff; padding:12px; border-radius:6px;">
            <img src="data:image/png;base64,<?= $tree_png_base64 ?>" alt="Phylogenetic Tree" style="max-width:100%; height:auto;">
        </div>
    <?php endif; ?>

    <h3>Biological Interpretation</h3>
    <div class="card">
        <p>
            This example dataset contains glucose-6-phosphatase proteins from Aves and demonstrates how sequence conservation,
            motif scanning and phylogenetic reconstruction can be combined to summarise evolutionary and functional properties
            within a user-defined taxonomic group.
        </p>
        <p>
            Conserved regions identified in the MSA may indicate functionally constrained residues, while PROSITE motif matches
            highlight candidate functional signatures. The phylogenetic tree provides an overview of sequence similarity patterns
            across the selected bird proteins.
        </p>
    </div>

    <div style="margin-top:20px;">
        <a href="example_msa.php" class="enter-button" style="display:inline-block; text-decoration:none;">Example MSA</a>
        <a href="example_prosite.php" class="enter-button" style="display:inline-block; text-decoration:none;">Example Motif Analysis</a>
        <a href="example_tree.php" class="enter-button" style="display:inline-block; text-decoration:none;">Example Tree</a>
    </div>
</div>

</body>
</html>

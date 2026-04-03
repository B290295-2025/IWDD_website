<?php
$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";
$dataset_name = 'aves_g6pase_example';

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$stmt = $conn->prepare(
    "SELECT accession_id, description, seq_length, taxon_group, protein_name
     FROM example_dataset
     WHERE dataset_name = ?
     ORDER BY display_order ASC"
);
$stmt->execute([$dataset_name]);
$data = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Example Dataset</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>Example Dataset</h2>

    <a href="/~s2845297/B290295_website/index.php" class="back-button">
        ← Back to Home
    </a>

    <br><br>

    <div class="card">
        <h3>Aves glucose-6-phosphatase example dataset</h3>
        <p>This example uses pre-processed data to illustrate all functionalities of the website.</p>
        <p><strong>Taxon:</strong> Aves</p>
        <p><strong>Protein:</strong> glucose-6-phosphatase</p>
        <p><strong>Displayed sequences:</strong> first 5 proteins</p>
    </div>

    <?php if (!empty($data)): ?>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Accession</th>
                    <th>Description</th>
                    <th>Length</th>
                    <th>Taxon</th>
                    <th>Protein</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($row['accession_id']) ?></strong></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars((string)$row['seq_length']) ?></td>
                        <td><?= htmlspecialchars($row['taxon_group']) ?></td>
                        <td><?= htmlspecialchars($row['protein_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top:20px;">
            <a href="example_msa.php" class="enter-button" style="display:inline-block; text-decoration:none;">View Example MSA</a>
            <a href="example_prosite.php" class="enter-button" style="display:inline-block; text-decoration:none;">View Example Motif Analysis</a>
            <a href="example_tree.php" class="enter-button" style="display:inline-block; text-decoration:none;">View Example Tree</a>
            <a href="analysis_summary.php" class="enter-button" style="display:inline-block; text-decoration:none;">View Summary Report</a>
        </div>

    <?php else: ?>
        <p>No example dataset found. Please run <strong>example_generate.php</strong> first.</p>
    <?php endif; ?>
</div>

</body>
</html>

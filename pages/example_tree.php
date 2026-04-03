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
    "SELECT tree_newick, tree_png_base64
     FROM example_results
     WHERE dataset_name = ?
     ORDER BY id DESC
     LIMIT 1"
);
$stmt->execute([$dataset_name]);
$result = $stmt->fetch();

$newick = $result['tree_newick'] ?? '';
$tree_png_base64 = $result['tree_png_base64'] ?? '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Example Tree</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>Example Phylogenetic Tree</h2>

    <a href="example.php" class="back-button">← Back to Example</a>
    <a href="analysis_summary.php" class="back-button"> -> View Summary Report</a>

    <?php if ($tree_png_base64): ?>
        <div style="margin-top:20px; background:#ffffff; padding:12px; border-radius:6px;">
            <img src="data:image/png;base64,<?= $tree_png_base64 ?>" alt="Phylogenetic Tree" style="max-width:100%; height:auto;">
        </div>
    <?php else: ?>
        <p>No example tree image found.</p>
    <?php endif; ?>

    <?php if ($newick): ?>
        <h3>Newick Format</h3>
        <pre style="white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere;"><?= htmlspecialchars($newick) ?></pre>
    <?php endif; ?>
</div>

</body>
</html>

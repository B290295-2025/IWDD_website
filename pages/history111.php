<?php
$conn = new PDO("mysql:host=127.0.0.1;dbname=s2845297_website", "s2845297", "YuQ1LiN030709!");

$stmt = $conn->query(
    "SELECT * FROM analysis_history
     ORDER BY created_at DESC"
);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<?php ob_start(); ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

<!DOCTYPE html>
<html>
<head>
<title>History</title>
<link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<div class="page-container">

<h2>Search History</h2>

<table class="result-table">
<tr>
    <th>Taxon</th>
    <th>Protein</th>
    <th>Time</th>
    <th>Action</th>
</tr>

<?php foreach ($data as $row): ?>
<tr>
    <td><?= htmlspecialchars($row['taxon']) ?></td>
    <td><?= htmlspecialchars($row['protein']) ?></td>
    <td><?= $row['created_at'] ?></td>

    <td>
        <a href="protein_query.php?taxon=<?= urlencode($row['taxon']) ?>&protein=<?= urlencode($row['protein']) ?>">
            Re-run
        </a>
    </td>
</tr>
<?php endforeach; ?>

</table>

</div>

</body>
</html>


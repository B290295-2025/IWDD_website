<?php
$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['aln_file'])) {

    $file = $_FILES['aln_file']['tmp_name'];

    $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/tree.py " . escapeshellarg($file) . " 2>&1";
    $output = shell_exec($cmd);
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Upload Alignment</title>
<link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<div class="page-container">

<h2>Build Tree from Alignment</h2>

<form method="post" enctype="multipart/form-data">
    <input type="file" name="aln_file" required>
    <button class="enter-button">Upload & Build Tree</button>
</form>

<?php if ($output): ?>
    <h3>Tree Result</h3>
    <pre><?= htmlspecialchars($output) ?></pre>
<?php endif; ?>

</div>

</body>
</html>

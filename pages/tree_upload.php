<?php
$output = '';
$newick = '';
$tree_png_base64 = '';
$error = '';
$download_png_file = '';
#allow user to download the result phylogenetic tree image
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['aln_file'])) {

    $file = $_FILES['aln_file']['tmp_name'];

    $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/tree.py " . escapeshellarg($file) . " 2>&1";
    $output = shell_exec($cmd);

    $tree_data = json_decode($output, true);

    if (!is_array($tree_data) || isset($tree_data['error'])) {
        $error = is_array($tree_data) ? $tree_data['error'] : $output;
    } else {
        $newick = $tree_data['newick'] ?? '';
        $png_file = $tree_data['png_file'] ?? '';

        if ($png_file && file_exists($png_file)) {
            $tree_png_base64 = base64_encode(file_get_contents($png_file));

            $download_png_name = "tree_upload_" . time() . ".png";
            copy($png_file, "/tmp/" . $download_png_name);
            $download_png_file = $download_png_name;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Upload Alignment</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">

    <h2>Build Tree from Alignment</h2>

    <form method="post" enctype="multipart/form-data">
        <input type="file" name="aln_file" required>
        <button class="enter-button">Upload & Build Tree</button>
    </form>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($tree_png_base64): ?>
        <h3>Tree Result</h3>

        <?php if (!empty($download_png_file)): ?>
            <div style="margin-bottom:15px;">
                <a href="tree_upload.php?download_png=<?= urlencode($download_png_file) ?>" class="download-btn">
                    Save Tree Image
                </a>
            </div>
        <?php endif; ?>

        <div style="background:#ffffff; padding:12px; border-radius:6px;">
            <img src="data:image/png;base64,<?= $tree_png_base64 ?>" alt="Phylogenetic Tree" style="max-width:100%; height:auto;">
        </div>
    <?php endif; ?>

    <?php if ($newick): ?>
        <h3>Newick Format</h3>
        <pre style="white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere;"><?= htmlspecialchars($newick) ?></pre>
    <?php endif; ?>

</div>

</body>
</html>

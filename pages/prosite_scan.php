<?php
// 1. 数据库连接
$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";
$selected_ids = $_GET['selected'] ?? [];
$taxon = $_GET['taxon'] ?? '';
$protein = $_GET['protein'] ?? '';
try {
    $conn = new PDO($dsn, $user, $pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

$results = [];
$protein_info = null;

// 2. 获取参数
$accession = $_GET['accession'] ?? '';

if ($accession) {
    // 从数据库获取序列
    $stmt = $conn->prepare("SELECT * FROM protein_data WHERE accession_id = ?");
    $stmt->execute([$accession]);
    $protein_info = $stmt->fetch(PDO::FETCH_ASSOC);
    	if ($protein_info) {
		$seq = trim($protein_info['sequence']);
		$accession = $protein_info['accession_id'];

	    // 将序列写入临时文件，避免命令行参数过长的问题
	    	$tmp_file = tempnam(sys_get_temp_dir(), 'seq_');
	    	file_put_contents($tmp_file, $seq);

	    // 修改命令：只传递 Accession 和 临时文件路径
	    	$cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/prosite_scan.py " . escapeshellarg($accession) . " " . escapeshellarg($tmp_file) . " 2>&1";
	    	$output = shell_exec($cmd);
    
	    // 执行完记得删掉临时文件
	    	unlink($tmp_file);

	    	$results = json_decode($output, true);
        // 🔥 关键修复：确保 $results 始终是一个数组
        if ($results === null || !is_array($results)) {
            $results = [];
        }

        // 检查 Python 是否传回了内部错误
        if (isset($results[0]['error'])) {
            $error_from_python = $results[0]['error'];
            $results = []; // 清空结果以防 foreach 崩溃
	}
    }
}
?>


<!DOCTYPE html>
<html>
<head>
    <title>PROSITE Scan - <?= htmlspecialchars($accession) ?></title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<?php ob_start(); ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

<body>

<div class="page-container">
    <h2>PROSITE Motif Analysis</h2>
    <a href="protein_query.php" class="back-button">← Back to Query</a>
    <a href="protein_query.php?taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>
    <?php foreach ($selected_ids as $id): ?>&selected[]=<?= urlencode($id) ?><?php endforeach; ?>"
    class="back-button">
    ← Back to Selected Proteins
    </a>
    <?php if ($protein_info): ?>
        <div class="card">
            <h3>Protein: <?= htmlspecialchars($protein_info['accession_id']) ?></h3>
            <p><?= htmlspecialchars($protein_info['description']) ?></p>
            <p><strong>Length:</strong> <?= $protein_info['seq_length'] ?> aa</p>
        </div>

        <h3>Functional Architecture</h3>
	<div class="motif-bar-container">
		<?php
		$seq_len = $protein_info['seq_length'];
		foreach ($results as $m):
		    $left = ($m['start'] / $seq_len) * 100;
		    $width = (($m['end'] - $m['start'] + 1) / $seq_len) * 100;
		    if ($width < 1.2) $width = 1.2; // 确保极短位点也可见

		    // 💡 生物学见解颜色区分：
		    // 磷酸化位点 (Phospho) 用橙色，糖基化 (Glyco) 用紫色，其他用蓝色
		    $color = '#3498db'; // 默认蓝色
		    if (strpos($m['name'], 'PHOSPHO') !== false) $color = '#e67e22'; // 橙色
		    if (strpos($m['name'], 'GLYCO') !== false) $color = '#9b59b6';   // 紫色

		    $tooltip = $m['accession'] . ": " . $m['description'] . " (" . $m['start'] . "-" . $m['end'] . ")";
		?>
		    <div class="motif-track"
		         style="left: <?= $left ?>%; width: <?= $width ?>%; background-color: <?= $color ?>;"
		         data-tooltip="<?= htmlspecialchars($tooltip) ?>">
		    </div>
		<?php endforeach; ?>
	</div>

        <h3>Detected Motifs</h3>
        <table class="result-table">
            <thead>
                <tr>
                    <th>Accession</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Start</th>
                    <th>End</th>
                    <th>Match Sequence</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($results)): ?>
                    <tr><td colspan="6">No known PROSITE motifs found.</td></tr>
                <?php else: ?>
                    <?php foreach ($results as $m): ?>
                        <tr>
                            <td><a href="https://prosite.expasy.org/<?= $m['accession'] ?>" target="_blank"><?= $m['accession'] ?></a></td>
                            <td><?= htmlspecialchars($m['name']) ?></td>
                            <td><?= htmlspecialchars($m['description']) ?></td>
                            <td><?= $m['start'] ?></td>
                            <td><?= $m['end'] ?></td>
                            <td style="font-family: monospace; font-weight: bold; color: #d62728;"><?= $m['match'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="error">Sequence not found.</p>
    <?php endif; ?>
</div>

</body>
</html>

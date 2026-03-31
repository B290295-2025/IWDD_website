<?php
// ---------------------------
// PDO 数据库连接
// ---------------------------
$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";

try {
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ---------------------------
// POST 参数 & 排序
// ---------------------------
$taxon = $_POST['taxon'] ?? $_GET['taxon'] ?? '';
$error_message = '';
$data = [];
// 排序字段
$sort = $_GET['sort'] ?? 'accession_id';
$sort = in_array($sort, ['seq_length', 'accession_id']) ? $sort : 'accession_id';

// 排序方向
$order = $_GET['order'] ?? 'asc';
$order = $order === 'desc' ? 'desc' : 'asc';

if (!empty($taxon)) {

    // 1️⃣ 检查缓存
    $stmt = $conn->prepare("SELECT COUNT(*) FROM protein_data WHERE taxon_group = ?");
    $stmt->execute([$taxon]);
    $count = $stmt->fetchColumn();

    // 2️⃣ 如果没有 → 调 Python 获取 FASTA
    if ($count == 0) {

        $safe_taxon = escapeshellarg($taxon);

        // ✅ 使用绝对路径 + 捕获错误输出
        $cmd = "/usr/bin/python3 /localdisk/home/s2845297/public_html/B290295_website/backend/fetch_protein.py $safe_taxon 2>&1";

        $fasta = shell_exec($cmd);


        if (!$fasta || stripos($fasta, 'Error') !== false) {
            $error_message = $fasta ?: "Unknown error fetching data from NCBI";
        } else {

            $entries = explode(">", $fasta);

            $insertStmt = $conn->prepare(
                "INSERT IGNORE INTO protein_data
                (taxon_group, accession_id, description, sequence, seq_length)
                VALUES (:taxon_group, :accession_id, :description, :sequence, :seq_length)"
            );

            foreach ($entries as $entry) {
                if (trim($entry) == "") continue;

                $lines = explode("\n", $entry);
                $header = array_shift($lines);
                $sequence = implode("", $lines);

                preg_match('/^(\S+)/', $header, $match);
                $accession_id = $match[1] ?? '';

                $description = $header;
                $seq_length = strlen($sequence);

                $insertStmt->execute([
                    ':taxon_group' => $taxon,
                    ':accession_id' => $accession_id,
                    ':description' => $description,
                    ':sequence' => $sequence,
                    ':seq_length' => $seq_length
                ]);
            }
        }
    }

    // 3️⃣ 从数据库读取
    if (empty($error_message)) {
        $stmt = $conn->prepare(
            "SELECT accession_id, description, seq_length, taxon_group
             FROM protein_data
             WHERE taxon_group = ?
             ORDER BY $sort $order"
        );
        $stmt->execute([$taxon]);
        $data = $stmt->fetchAll();
    }
}
?>

<?php include __DIR__ . '/../components/header.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Protein Query</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>

<body>

<div class="page-container">

    <h2>Protein Query</h2>

    <a href="/~s2845297/B290295_website/index.php" class="back-button">← Back to Home</a>

    <br><br>

    <form method="post">
        <select name="taxon" required>
            <option value="" disabled selected>-- select --</option>
            <option value="aves">glucose-6-phosphatase proteins from Aves (birds)</option>
            <option value="mammals">ABC transporters in mammals</option>
            <option value="rodents">kinases in rodents</option>
            <option value="vertebrates">adenyl cyclases in vertebrates</option>
        </select>

        <button type="submit" class="enter-button">Search</button>
    </form>

    <hr>

    <?php if ($error_message): ?>
        <p class="error">Error fetching data: <?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <?php if (!empty($taxon) && !empty($data)): ?>

        <form method="post">
            <table class="result-table">
                <thead>
                    <tr>
			<th><input type="checkbox" id="selectAll"></th>
			<th>
			<a href="?sort=accession_id&order=<?= $order === 'asc' ? 'desc' : 'asc' ?>&taxon=<?= urlencode($taxon) ?>">
			Accession
			</a>
			</th>
			<th>Description</th>
			<th>
			<a href="?sort=seq_length&order=<?= $order === 'asc' ? 'desc' : 'asc' ?>&taxon=<?= urlencode($taxon) ?>">
			Length
			</a>
			</th>
                        <th>Taxon</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td><input type="checkbox" name="selected[]" value="<?= htmlspecialchars($row['accession_id']) ?>"></td>
                        <td><?= htmlspecialchars($row['accession_id']) ?></td>
                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= $row['seq_length'] ?></td>
                        <td><?= $row['taxon_group'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" name="msa" class="enter-button">Run MSA</button>
        </form>

        <script>
        document.getElementById('selectAll').addEventListener('click', function(){
            const checkboxes = document.querySelectorAll('input[name="selected[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
        });
        </script>

    <?php elseif (!empty($taxon) && empty($error_message)): ?>
        <p>No sequences found for <strong><?= htmlspecialchars($taxon) ?></strong>.</p>
    <?php endif; ?>

</div>

</body>
</html>

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
// 参数获取
// ---------------------------
$taxon = $_POST['taxon'] ?? $_GET['taxon'] ?? '';
$protein = $_POST['protein'] ?? $_GET['protein'] ?? '';
$selected_ids = $_GET['selected'] ?? [];

$taxon = trim($taxon);
$protein = trim($protein);

$error_message = '';
$data = [];

// ---------------------------
// 排序
// ---------------------------
$sort = $_GET['sort'] ?? 'accession_id';
$sort = in_array($sort, ['seq_length', 'accession_id']) ? $sort : 'accession_id';

$order = $_GET['order'] ?? 'asc';
$order = $order === 'desc' ? 'desc' : 'asc';

// ---------------------------
// 查询逻辑
// ---------------------------
if (!empty($taxon) && !empty($protein)) {

    // 只有在没有 selected_ids 时才触发抓取
    if (empty($selected_ids)) {

        // 检查缓存
        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM protein_data 
             WHERE taxon_group = ? AND protein_name = ?"
        );
        $stmt->execute([$taxon, $protein]);
        $count = $stmt->fetchColumn();

        // 没有缓存 → 调 Python
        if ($count == 0) {

            $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/fetch_protein.py "
                 . escapeshellarg($taxon) . " "
                 . escapeshellarg($protein) . " 2>&1";

            $fasta = shell_exec($cmd);

            if (!$fasta || stripos($fasta, 'Error') !== false) {
                $error_message = $fasta ?: "Error fetching data from NCBI";
            } else {

                $entries = explode(">", $fasta);

                $insertStmt = $conn->prepare(
                    "INSERT IGNORE INTO protein_data
                    (taxon_group, protein_name, accession_id, description, sequence, seq_length)
                    VALUES (:taxon_group, :protein_name, :accession_id, :description, :sequence, :seq_length)"
                );

                foreach ($entries as $entry) {

                    if (trim($entry) == "") continue;

                    $lines = explode("\n", $entry);
                    $header = array_shift($lines);
                    $sequence = strtoupper(implode("", $lines));

                    preg_match('/^(\S+)/', $header, $match);
                    $accession_id = $match[1] ?? '';

                    $seq_length = strlen($sequence);

                    if (!$accession_id || !$sequence) continue;

                    $insertStmt->execute([
                        ':taxon_group' => $taxon,
                        ':protein_name' => $protein,
                        ':accession_id' => $accession_id,
                        ':description' => $header,
                        ':sequence' => $sequence,
                        ':seq_length' => $seq_length
                    ]);
                }
            }
        }
    }

    // ---------------------------
    // 数据读取（两种模式）
    // ---------------------------

    if (empty($error_message)) {

        // ⭐ 模式1：只显示选中序列（从 MSA 返回）
        if (!empty($selected_ids)) {

            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

            $stmt = $conn->prepare(
                "SELECT accession_id, description, seq_length, taxon_group
                 FROM protein_data
                 WHERE accession_id IN ($placeholders)
                 ORDER BY $sort $order"
            );

            $stmt->execute($selected_ids);
            $data = $stmt->fetchAll();

        } else {

            // ⭐ 模式2：正常查询
            $stmt = $conn->prepare(
                "SELECT accession_id, description, seq_length, taxon_group
                 FROM protein_data
                 WHERE taxon_group = ? AND protein_name = ?
                 ORDER BY $sort $order"
            );

            $stmt->execute([$taxon, $protein]);
            $data = $stmt->fetchAll();
        }
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

    <a href="/~s2845297/B290295_website/index.php" class="back-button">
        ← Back to Home
    </a>

    <br><br>

    <!-- 输入框 -->
    <form method="post">
        <input type="text" name="taxon"
               placeholder="Enter taxon (e.g. Mammalia, Aves)"
               value="<?= htmlspecialchars($taxon) ?>" required>

        <input type="text" name="protein"
               placeholder="Enter protein (e.g. kinase, ABC-transporter)"
               value="<?= htmlspecialchars($protein) ?>" required>

        <button type="submit" class="enter-button">Search</button>
    </form>

    <small>
        Examples of Taxon:Mammalia, Rodentia, Aves, cat | Examples of Protein: kinase, ABC-transporter, nuclease
    </small>

    <hr>

    <?php if ($error_message): ?>
        <p class="error"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <?php if (!empty($data)): ?>

        <form method="post" action="msa.php" id="msaForm">

            <!-- ⭐ 关键：把 taxon + protein 传给 MSA -->
            <input type="hidden" name="taxon" value="<?= htmlspecialchars($taxon) ?>">
            <input type="hidden" name="protein" value="<?= htmlspecialchars($protein) ?>">

            <table class="result-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>

                        <th>
                            <a href="?sort=accession_id&order=<?= $order === 'asc' ? 'desc' : 'asc' ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>">
                                Accession
                            </a>
                        </th>

                        <th>Description</th>

                        <th>
                            <a href="?sort=seq_length&order=<?= $order === 'asc' ? 'desc' : 'asc' ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>">
                                Length
                            </a>
                        </th>

                        <th>Taxon</th>
                    </tr>
                </thead>

                <tbody>
                <?php foreach ($data as $row): ?>
                    <tr>
                        <td>
                            <input type="checkbox"
                                   name="selected[]"
                                   value="<?= htmlspecialchars($row['accession_id']) ?>"
                                   <?= in_array($row['accession_id'], $selected_ids) ? 'checked' : '' ?>>
                        </td>

                        <td>
                            <strong><?= htmlspecialchars($row['accession_id']) ?></strong><br>

                            <!-- ✅ 正确路径 -->
			    <a href="prosite_scan.php?accession=<?= urlencode($row['accession_id']) ?>&taxon=<?= urlencode($taxon) ?>
&protein=<?= urlencode($protein) ?>
<?php foreach ($selected_ids as $id): ?>&selected[]=<?= urlencode($id) ?><?php endforeach; ?>"
                               class="scan-link">
                               Scan Motifs
                            </a>
                        </td>

                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= $row['seq_length'] ?></td>
                        <td><?= htmlspecialchars($row['taxon_group']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <button type="submit" class="enter-button" id="msaBtn">
                Run MSA
            </button>

        </form>

        <!-- Select All -->
        <script>
        document.getElementById('selectAll').addEventListener('click', function(){
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => {
                cb.checked = this.checked;
            });
        });
        </script>

    <?php elseif (!empty($taxon) && !empty($protein) && empty($error_message)): ?>
        <p>No sequences found.</p>
    <?php endif; ?>

</div>

</body>
</html>

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
$taxon = trim($_POST['taxon'] ?? $_GET['taxon'] ?? '');
$protein = trim($_POST['protein'] ?? $_GET['protein'] ?? '');
$selected_ids = $_GET['selected'] ?? [];

$error_message = '';
$data = [];

// ---------------------------
// 分页
// ---------------------------
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;
$total_rows = 0;
$total_pages = 1;

// ---------------------------
// 排序
// ---------------------------
$sort = $_GET['sort'] ?? 'accession_id';
$sort = in_array($sort, ['seq_length', 'accession_id']) ? $sort : 'accession_id';

$order = $_GET['order'] ?? 'asc';
$order = $order === 'desc' ? 'desc' : 'asc';

// ---------------------------
// 表单校验
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($taxon) && empty($protein)) {
    $error_message = "Please enter at least one search term: taxon or protein.";
}

// ---------------------------
// 查询逻辑
// ---------------------------
if ((!empty($taxon) || !empty($protein)) || !empty($selected_ids)) {

    // 只有 taxon 和 protein 都填写时，才远程抓取并缓存
    if (!empty($taxon) && !empty($protein) && empty($selected_ids)) {

        $stmt = $conn->prepare(
            "SELECT COUNT(*) FROM protein_data
             WHERE taxon_group = ? AND protein_name = ?"
        );
        $stmt->execute([$taxon, $protein]);
        $count = $stmt->fetchColumn();

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
    // 数据读取
    // ---------------------------
    if (empty($error_message)) {

        if (!empty($selected_ids)) {
            // 从其他页面返回，只显示选中的序列
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));

            $countStmt = $conn->prepare(
                "SELECT COUNT(*) 
                 FROM protein_data
                 WHERE accession_id IN ($placeholders)"
            );
            $countStmt->execute($selected_ids);
            $total_rows = (int)$countStmt->fetchColumn();
            $total_pages = max(1, ceil($total_rows / $limit));

            $stmt = $conn->prepare(
                "SELECT accession_id, description, seq_length, taxon_group, protein_name
                 FROM protein_data
                 WHERE accession_id IN ($placeholders)
                 ORDER BY $sort $order
                 LIMIT $limit OFFSET $offset"
            );
            $stmt->execute($selected_ids);
            $data = $stmt->fetchAll();

        } else {
            // 动态 where
            $where = [];
            $params = [];

            if (!empty($taxon)) {
                $where[] = "taxon_group LIKE ?";
                $params[] = "%" . $taxon . "%";
            }

            if (!empty($protein)) {
                $where[] = "protein_name LIKE ?";
                $params[] = "%" . $protein . "%";
            }

            $countSql = "SELECT COUNT(*) FROM protein_data";
            if (!empty($where)) {
                $countSql .= " WHERE " . implode(" AND ", $where);
            }

            $countStmt = $conn->prepare($countSql);
            $countStmt->execute($params);
            $total_rows = (int)$countStmt->fetchColumn();
            $total_pages = max(1, ceil($total_rows / $limit));

            $sql = "SELECT accession_id, description, seq_length, taxon_group, protein_name
                    FROM protein_data";
            if (!empty($where)) {
                $sql .= " WHERE " . implode(" AND ", $where);
            }
            $sql .= " ORDER BY $sort $order LIMIT $limit OFFSET $offset";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $data = $stmt->fetchAll();
        }
    }
}

// ---------------------------
// 记录 QUERY 历史
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!empty($taxon) || !empty($protein))) {
    $history = $conn->prepare(
        "INSERT INTO analysis_history (taxon, protein, action)
         VALUES (?, ?, 'QUERY')"
    );
    $history->execute([$taxon, $protein]);
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Protein Query</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">

    <h2>Protein Query</h2>

    <a href="/~s2845297/B290295_website/index.php" class="back-button">
        ← Back to Home
    </a>

    <br><br>

    <form method="post">
        <input type="text" name="taxon"
               placeholder="Enter taxon (e.g. Mammalia, Aves)"
               value="<?= htmlspecialchars($taxon) ?>">

        <input type="text" name="protein"
               placeholder="Enter protein (e.g. kinase, ABC-transporter)"
               value="<?= htmlspecialchars($protein) ?>">

        <button type="submit" class="enter-button">Search</button>
    </form>

    <small>
        You can search by taxon only, protein only, or both together.
    </small>
    <br>
    <small>
        Examples of Taxon: Mammalia, Rodentia, Aves, cat | Examples of Protein: kinase, ABC-transporter, adenylyl
    </small>

    <hr>

    <?php if ($error_message): ?>
        <p class="error"><?= htmlspecialchars($error_message) ?></p>
    <?php endif; ?>

    <?php if (!empty($data)): ?>

        <form method="post" action="msa.php" id="msaForm">
            <input type="hidden" name="taxon" value="<?= htmlspecialchars($taxon) ?>">
            <input type="hidden" name="protein" value="<?= htmlspecialchars($protein) ?>">

            <table class="result-table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAll"></th>

                        <th>
                            <a href="?sort=accession_id&order=<?= $order === 'asc' ? 'desc' : 'asc' ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>&page=<?= $page ?>">
                                Accession
                            </a>
                        </th>

                        <th>Description</th>

                        <th>
                            <a href="?sort=seq_length&order=<?= $order === 'asc' ? 'desc' : 'asc' ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>&page=<?= $page ?>">
                                Length
                            </a>
                        </th>

                        <th>Taxon</th>
                        <th>Protein</th>
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
                            <a href="prosite_scan.php?accession=<?= urlencode($row['accession_id']) ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?><?php foreach ($selected_ids as $id): ?>&selected[]=<?= urlencode($id) ?><?php endforeach; ?>"
                               class="scan-link">
                               Scan Motifs
                            </a>
                        </td>

                        <td><?= htmlspecialchars($row['description']) ?></td>
                        <td><?= htmlspecialchars((string)$row['seq_length']) ?></td>
                        <td><?= htmlspecialchars($row['taxon_group']) ?></td>
                        <td><?= htmlspecialchars($row['protein_name']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:15px;">
                <button type="submit" class="enter-button" id="msaBtn">
                    Run MSA
                </button>
            </div>
        </form>

        <form method="post" action="alphafold_redirect.php" style="margin-top:15px;">
            <input type="hidden" name="taxon" value="<?= htmlspecialchars($taxon) ?>">
            <input type="hidden" name="protein" value="<?= htmlspecialchars($protein) ?>">

            <?php foreach ($data as $row): ?>
                <input type="hidden"
                       class="alphafold-hidden"
                       data-accession="<?= htmlspecialchars($row['accession_id']) ?>"
                       value="<?= htmlspecialchars($row['accession_id']) ?>">
            <?php endforeach; ?>

            <div id="alphafoldSelectedContainer"></div>

            <button type="submit" class="enter-button" id="alphafoldBtn">
                View 3D Structure
            </button>
        </form>

        <div style="margin-top:20px;">
            <a href="?page=1&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>" class="scan-link">First</a>
            &nbsp;|&nbsp;
            <a href="?page=<?= max(1, $page - 1) ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>" class="scan-link">Previous</a>
            &nbsp;|&nbsp;
            <a href="?page=<?= min($total_pages, $page + 1) ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>" class="scan-link">Next</a>
            &nbsp;|&nbsp;
            <a href="?page=<?= $total_pages ?>&taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>&sort=<?= urlencode($sort) ?>&order=<?= urlencode($order) ?>" class="scan-link">Last</a>

            <span style="margin-left:15px;">
                Page <?= htmlspecialchars((string)$page) ?> / <?= htmlspecialchars((string)$total_pages) ?>
            </span>
        </div>

        <script>
        document.getElementById('selectAll').addEventListener('click', function(){
            document.querySelectorAll('input[name="selected[]"]').forEach(cb => {
                cb.checked = this.checked;
            });
            syncAlphaFoldSelection();
        });

        document.querySelectorAll('input[name="selected[]"]').forEach(cb => {
            cb.addEventListener('change', syncAlphaFoldSelection);
        });

        function syncAlphaFoldSelection() {
            const container = document.getElementById('alphafoldSelectedContainer');
            container.innerHTML = '';

            document.querySelectorAll('input[name="selected[]"]:checked').forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected[]';
                input.value = cb.value;
                container.appendChild(input);
            });
        }

        syncAlphaFoldSelection();

        document.getElementById('alphafoldBtn').addEventListener('click', function(e) {
            const checked = document.querySelectorAll('input[name="selected[]"]:checked');
            if (checked.length !== 1) {
                e.preventDefault();
                alert('Please select exactly 1 protein to view 3D structure.');
            }
        });
        </script>

    <?php elseif ((!empty($taxon) || !empty($protein)) && empty($error_message)): ?>
        <p>No sequences found.</p>
    <?php endif; ?>

</div>

</body>
</html>

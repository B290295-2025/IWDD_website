<?php
$conn = new mysqli("localhost", "s2845297", "YuQ1LiN030709!", "s2845297_website");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$taxon = $_POST['taxon'] ?? '';

if (!empty($taxon)) {

    // 1️⃣ 检查数据库是否已有
    $check = $conn->prepare("SELECT COUNT(*) FROM proteins WHERE taxon=?");
    $check->bind_param("s", $taxon);
    $check->execute();
    $check->bind_result($count);
    $check->fetch();
    $check->close();

    // 2️⃣ 如果没有 → 调 Python + 存数据库
    if ($count == 0) {

        $safe_taxon = escapeshellarg($taxon);

        $fasta = shell_exec(
            "python3 " . __DIR__ . "/../backend/fetch_protein.py $safe_taxon"
        );

        $entries = explode(">", $fasta);

        foreach ($entries as $entry) {
            if (trim($entry) == "") continue;

            $lines = explode("\n", $entry);
            $header = array_shift($lines);
            $sequence = implode("", $lines);

            preg_match('/^(\S+)/', $header, $match);
            $accession = $match[1];

            $description = $header;
            $length = strlen($sequence);

            $stmt = $conn->prepare(
                "INSERT IGNORE INTO proteins (accession, description, sequence, length, taxon)
                 VALUES (?, ?, ?, ?, ?)"
            );

            $stmt->bind_param("sssis", $accession, $description, $sequence, $length, $taxon);
            $stmt->execute();
        }
    }

    // 3️⃣ 从数据库读取（缓存或新数据）
    $stmt = $conn->prepare(
        "SELECT accession, description, length, taxon 
         FROM proteins WHERE taxon=?"
    );
    $stmt->bind_param("s", $taxon);
    $stmt->execute();
    $data = $stmt->get_result();
}
?>

<?php include __DIR__ . '/../components/header.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <title>Protein Query</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/assets/css/style.css">
</head>

<body>

<div class="page-container">

    <h2>Protein Query</h2>

    <a href="/~s2845297/B290295_website/index.php" class="back-button">
        ← Back to Home
    </a>

    <br><br>

    <!-- 🔥 查询表单 -->
    <form method="post">
        <select name="taxon" required>
            <option value="" disabled selected>-- select --</option>
            <option value="aves">glucose-6-phosphatase proteins from Aves (birds)</option>
            <option value="mammals">ABC transporters in mammals</option>
            <option value="rodents">kinases in rodents</option>
            <option value="vertebrates">adenyl cyclases in vertebrates</option>
        </select>

        <button type="submit">Search</button>
    </form>

    <hr>

    <!-- 🔥 表格展示 -->
    <?php if (!empty($taxon)): ?>

        <table class="result-table">
            <tr>
                <th>Accession</th>
                <th>Description</th>
                <th>Length</th>
                <th>Taxon</th>
            </tr>

            <?php while($row = $data->fetch_assoc()): ?>
            <tr>
                <td><?= htmlspecialchars($row['accession']) ?></td>
                <td><?= htmlspecialchars($row['description']) ?></td>
                <td><?= $row['length'] ?></td>
                <td><?= $row['taxon'] ?></td>
            </tr>
            <?php endwhile; ?>

        </table>

    <?php endif; ?>

</div>

</body>
</html>

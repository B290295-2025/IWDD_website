<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && !empty($_POST["taxon"])) {
    $taxon = escapeshellarg($_POST["taxon"]);
    $output = shell_exec("python3 " . __DIR__ . "/../backend/fetch_protein.py $taxon");
}
?>

<?php include __DIR__ . '/../components/header.php'; ?>

<?php
$conn = new mysqli("localhost", "user", "password", "database");

$taxon = $_POST['taxon'] ?? '';

// 检查数据库是否已有数据
$check = $conn->prepare("SELECT COUNT(*) FROM proteins WHERE taxon=?");
$check->bind_param("s", $taxon);
$check->execute();
$check->bind_result($count);
$check->fetch();
$check->close();
?>

## if dont have the sequence data
<?php
if ($count == 0 && !empty($taxon)) {

    $safe_taxon = escapeshellarg($taxon);

    $fasta = shell_exec(
        "python3 " . __DIR__ . "/../backend/fetch_protein.py $safe_taxon"
    );

    // 解析 FASTA
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
            "INSERT INTO proteins (accession, description, sequence, length, taxon)
             VALUES (?, ?, ?, ?, ?)"
        );

        $stmt->bind_param("sssis", $accession, $description, $sequence, $length, $taxon);
        $stmt->execute();
    }
}
?>

#whether have or not read from SQL
<?php
$result = $conn->prepare("SELECT accession, description, length, taxon FROM proteins WHERE taxon=?");
$result->bind_param("s", $taxon);
$result->execute();
$data = $result->get_result();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Protein Query</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>

<body>

<h2>Protein Query</h2>
<a href="../index.php" class="back-button">← Back to Home</a>

<br><br>

<div class="page-container">

    <h2>Protein Query</h2>

    <form method="post">
        <select name="taxon">
            <option value="" disabled selected>-- select --</option>
            <option value="aves">glucose-6-phosphatase proteins from Aves (birds)</option>
            <option value="mammals">ABC transporters in mammals</option>
            <option value="rodents">kinases in rodents</option>
            <option value="vertebrates">adenyl cyclases in vertebrates</option>
        </select>

        <button type="submit">Search</button>
    </form>

    <hr>

    <pre>
<?php
if (isset($output)) {
    echo htmlspecialchars($output);
}
?>
    </pre>

</div>

</body>
</html>

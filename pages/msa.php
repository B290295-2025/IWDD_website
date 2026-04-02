<?php
// ---------------------------
// PDO 连接
// ---------------------------
$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$msa_result = '';
$alignment_display = '';
$conservation_scores = [];
$error = '';
$selected_ids = $_POST['selected'] ?? [];
$taxon = $_POST['taxon'] ?? '';
$protein = $_POST['protein'] ?? '';
$selected_data = [];

function split_alignment_and_scores($raw) {
    $scores = [];
    $alignment = $raw;

    if (preg_match('/###SCORES_JSON_START###(.*?)###SCORES_JSON_END###/s', $raw, $match)) {
        $json = trim($match[1]);
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            $scores = $decoded;
        }
        $alignment = preg_replace('/###SCORES_JSON_START###.*###SCORES_JSON_END###/s', '', $raw);
        $alignment = trim($alignment);
    }

    return [$alignment, $scores];
}

function parse_clustal_sequences($msa) {
    $lines = explode("\n", $msa);
    $seqs = [];

    foreach ($lines as $line) {
        if (trim($line) === '' || strpos($line, 'CLUSTAL') !== false) continue;

        preg_match('/^(\S+)\s+([A-Z\-]+)/', $line, $match);

        if ($match) {
            $id = $match[1];
            $seq = $match[2];

            if (!isset($seqs[$id])) $seqs[$id] = '';
            $seqs[$id] .= $seq;
        }
    }

    return $seqs;
}

function build_msa_report($scores, $seq_count) {
    if (empty($scores)) {
        return [
            'alignment_length' => 0,
            'sequence_count' => $seq_count,
            'average_score' => 0,
            'high_sites' => 0,
            'message' => 'Conservation score is not available for this alignment.'
        ];
    }

    $avg = array_sum($scores) / count($scores);
    $high_sites = 0;
    foreach ($scores as $s) {
        if ($s >= 0.9) $high_sites++;
    }

    $message = 'Moderate sequence conservation detected.';
    if ($avg >= 0.85) {
        $message = 'Strong overall conservation detected across the alignment.';
    } elseif ($avg < 0.5) {
        $message = 'The selected sequences are relatively divergent.';
    }

    return [
        'alignment_length' => count($scores),
        'sequence_count' => $seq_count,
        'average_score' => round($avg, 3),
        'high_sites' => $high_sites,
        'message' => $message
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['selected'])) {

    $ids = $_POST['selected'];
    $selected_ids = $ids;

    if (count($ids) < 2) {
        $error = "Please select at least 2 sequences.";
    } else {

        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $conn->prepare(
            "SELECT accession_id, description, seq_length, taxon_group, sequence
             FROM protein_data
             WHERE accession_id IN ($placeholders)"
        );

        $stmt->execute($ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $selected_data = $rows;

        $fasta = '';
        foreach ($rows as $r) {
            $fasta .= ">" . $r['accession_id'] . "\n";
            $fasta .= $r['sequence'] . "\n";
        }

        $input_file = "/tmp/msa_" . uniqid() . ".fasta";
        file_put_contents($input_file, $fasta);

        $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/msa.py " . escapeshellarg($input_file) . " 2>&1";
        $msa_result = shell_exec($cmd);

        if (strpos($msa_result, "Error:") === 0) {
            $error = $msa_result;
        } else {
            list($alignment_display, $conservation_scores) = split_alignment_and_scores($msa_result);
        }
    }
}

if (!empty($selected_ids)) {
    $history = $conn->prepare(
        "INSERT INTO analysis_history (taxon, protein, selected_ids, action)
         VALUES (?, ?, ?, 'MSA')"
    );

    $history->execute([
        $_POST['taxon'] ?? '',
        $_POST['protein'] ?? '',
        implode(',', $selected_ids)
    ]);
}

// ---------------------------
// 可视化函数（原样保留）
// ---------------------------
function render_msa_html($msa) {

    $lines = explode("\n", $msa);
    $seqs = [];

    foreach ($lines as $line) {
        if (trim($line) === '' || strpos($line, 'CLUSTAL') !== false) continue;

        preg_match('/^(\S+)\s+([A-Z\-]+)/', $line, $match);

        if ($match) {
            $id = $match[1];
            $seq = $match[2];

            if (!isset($seqs[$id])) $seqs[$id] = '';
            $seqs[$id] .= $seq;
        }
    }

    if (empty($seqs)) return '';

    $ids = array_keys($seqs);
    $length = strlen(current($seqs));
    $block_size = 60;

    $html = "<div class='msa-blast'>";

    for ($i = 0; $i < $length; $i += $block_size) {

        $block = [];
        foreach ($ids as $id) {
            $block[$id] = substr($seqs[$id], $i, $block_size);
        }

        foreach ($block as $id => $seq) {

            $start = $i + 1;
            $end = $i + strlen($seq);

            $html .= "<div class='msa-row'>";
            $html .= "<span class='msa-id'>$id</span>";
            $html .= "<span class='msa-pos'>$start</span>";

            $colored = '';
            foreach (str_split($seq) as $c) {

                $class = "aa-default";

                if (strpos("AVLIMFWY", $c) !== false) $class = "aa-hydrophobic";
                elseif (strpos("STNQ", $c) !== false) $class = "aa-polar";
                elseif (strpos("KRH", $c) !== false) $class = "aa-positive";
                elseif (strpos("DE", $c) !== false) $class = "aa-negative";
                elseif (strpos("GPC", $c) !== false) $class = "aa-special";
                elseif ($c === '-') $class = "aa-gap";

                $colored .= "<span class='$class'>$c</span>";
            }

            $html .= "<span class='msa-seq'>$colored</span>";
            $html .= "<span class='msa-pos'>$end</span>";
            $html .= "</div>";
        }

        $cons = '';
        for ($j = 0; $j < $block_size; $j++) {

            $chars = [];
            foreach ($block as $seq) {
                if (isset($seq[$j])) $chars[] = $seq[$j];
            }

            if (count(array_unique($chars)) === 1 && $chars[0] !== '-') {
                $cons .= '*';
            } else {
                $cons .= ' ';
            }
        }

        $html .= "<div class='msa-cons'>$cons</div><br>";
    }

    $html .= "</div>";
    return $html;
}

// 下载
if (isset($_GET['download'])) {
    $file = "/tmp/" . basename($_GET['download']);

    if (file_exists($file)) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="' . basename($file) . '"');
        readfile($file);
        exit;
    } else {
        echo "File not found";
        exit;
    }
}

$download_file = '';
$msa_report = [
    'alignment_length' => 0,
    'sequence_count' => count($selected_ids),
    'average_score' => 0,
    'high_sites' => 0,
    'message' => ''
];

if (!empty($alignment_display) && strpos($alignment_display, "Error:") !== 0) {
    $filename = "msa_" . time() . ".aln";
    file_put_contents("/tmp/" . $filename, $alignment_display);
    $download_file = $filename;

    $msa_report = build_msa_report($conservation_scores, count($selected_ids));
}

// STRING one-click link
$string_ids = implode("%0d", array_map('rawurlencode', $selected_ids));
$string_url = "https://string-db.org/cgi/network?identifiers=" . $string_ids;
?>

<!DOCTYPE html>
<html>
<head>
    <title>MSA Result</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="msa-container">
    <div class="msa-sidebar">
        <h2>Protein Query</h2>
        <a href="protein_query.php?taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>" 
           class="back-button">
            ← Back to Choose Sequence
        </a>
        <hr>
        <h3>Selected Proteins</h3>

        <?php if (!empty($selected_data)): ?>
            <?php foreach ($selected_data as $row): ?>
                <div class="protein-card">
                    <strong>
                        <a href="https://www.ncbi.nlm.nih.gov/protein/<?= urlencode($row['accession_id']) ?>" target="_blank" rel="noopener noreferrer">
                            <?= htmlspecialchars($row['accession_id']) ?>
                        </a>
                    </strong><br>
                    <?= htmlspecialchars($row['description']) ?><br>
                    <small>
                        Length: <?= $row['seq_length'] ?><br>
                        Taxon: <?= htmlspecialchars($row['taxon_group']) ?>
                    </small>
                </div>
            <?php endforeach; ?>

            <form method="get" action="protein_query.php">
                <?php foreach ($selected_ids as $id): ?>
                    <input type="hidden" name="selected[]" value="<?= htmlspecialchars($id) ?>">
                <?php endforeach; ?>
                <input type="hidden" name="taxon" value="<?= htmlspecialchars($taxon) ?>">
                <input type="hidden" name="protein" value="<?= htmlspecialchars($protein) ?>">
                <button class="enter-button">Motifs Scan</button>
            </form>

            <br>

            <form method="post" action="tree.php">
                <?php foreach ($selected_ids as $id): ?>
                    <input type="hidden" name="selected[]" value="<?= htmlspecialchars($id) ?>">
                <?php endforeach; ?>
                <button class="enter-button">Phylogenetic Tree</button>
            </form>

            <br>

            <a href="<?= htmlspecialchars($string_url) ?>" target="_blank" rel="noopener noreferrer" class="enter-button" style="display:inline-block; text-decoration:none;">
                STRING Interactions
            </a>
        <?php endif; ?>
    </div>

    <div class="msa-main">

        <?php if ($error): ?>
            <p style="color:red"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (!empty($alignment_display)): ?>

            <div class="msa-header">
                <?php if (!empty($download_file)): ?>
                    <a href="msa.php?download=<?= urlencode($download_file) ?>" class="download-btn">
                        Download Alignment
                    </a>
                <?php endif; ?>

                <br><br>

                <form method="get" action="protein_query.php" style="display:inline;">
                    <?php foreach ($selected_ids as $id): ?>
                        <input type="hidden" name="selected[]" value="<?= htmlspecialchars($id) ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="taxon" value="<?= htmlspecialchars($taxon) ?>">
                    <input type="hidden" name="protein" value="<?= htmlspecialchars($protein) ?>">
                    <button class="enter-button">Motifs Scan</button>
                </form>

                <form method="post" action="tree.php" style="display:inline;">
                    <?php foreach ($selected_ids as $id): ?>
                        <input type="hidden" name="selected[]" value="<?= htmlspecialchars($id) ?>">
                    <?php endforeach; ?>
                    <button class="enter-button">Phylogenetic Tree</button>
                </form>
            </div>

            <div class="msa-box">
                <?= render_msa_html($alignment_display) ?>
            </div>

            <div style="margin-top:20px;">
                <h3>MSA Report</h3>
                <table class="result-table">
                    <thead>
                        <tr>
                            <th>Metric</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Number of selected sequences</td>
                            <td><?= htmlspecialchars((string)$msa_report['sequence_count']) ?></td>
                        </tr>
                        <tr>
                            <td>Alignment length</td>
                            <td><?= htmlspecialchars((string)$msa_report['alignment_length']) ?></td>
                        </tr>
                        <tr>
                            <td>Average conservation score</td>
                            <td><?= htmlspecialchars((string)$msa_report['average_score']) ?></td>
                        </tr>
                        <tr>
                            <td>Highly conserved positions (≥ 0.9)</td>
                            <td><?= htmlspecialchars((string)$msa_report['high_sites']) ?></td>
                        </tr>
                        <tr>
                            <td>Interpretation</td>
                            <td><?= htmlspecialchars($msa_report['message']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div style="margin-top:20px;">
                <h3>Conservation Score</h3>
                <div style="background:#ffffff; padding:12px; border-radius:6px;">
                    <canvas id="scoreChart" height="120"></canvas>
                </div>
            </div>

            <script>
            const whiteBgPlugin = {
                id: 'whiteBgPlugin',
                beforeDraw(chart) {
                    const {ctx, width, height} = chart;
                    ctx.save();
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, width, height);
                    ctx.restore();
                }
            };

            const msaScores = <?= json_encode($conservation_scores) ?>;
            const msaLabels = msaScores.map((_, i) => i + 1);

            if (msaScores.length > 0) {
                new Chart(document.getElementById('scoreChart'), {
                    type: 'line',
                    data: {
                        labels: msaLabels,
                        datasets: [{
                            label: 'Conservation score',
                            data: msaScores,
                            tension: 0.2,
                            pointRadius: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: true,
                        scales: {
                            y: {
                                min: 0,
                                max: 1,
                                title: {
                                    display: true,
                                    text: 'Score'
                                }
                            },
                            x: {
                                title: {
                                    display: true,
                                    text: 'Alignment position'
                                }
                            }
                        }
                    },
                    plugins: [whiteBgPlugin]
                });
            }
            </script>

        <?php endif; ?>

    </div>
</div>

</body>
</html>

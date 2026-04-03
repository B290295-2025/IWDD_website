<?php
require_once __DIR__ . '/example_helpers.php';

$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";
$dataset_name = 'aves_g6pase_example';

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$dataStmt = $conn->prepare(
    "SELECT accession_id, description, seq_length, taxon_group
     FROM example_dataset
     WHERE dataset_name = ?
     ORDER BY display_order ASC"
);
$dataStmt->execute([$dataset_name]);
$selected_data = $dataStmt->fetchAll();

$resultStmt = $conn->prepare(
    "SELECT msa_alignment, conservation_scores, msa_report
     FROM example_results
     WHERE dataset_name = ?
     ORDER BY id DESC
     LIMIT 1"
);
$resultStmt->execute([$dataset_name]);
$result = $resultStmt->fetch();

$alignment_display = $result['msa_alignment'] ?? '';
$conservation_scores = json_decode($result['conservation_scores'] ?? '[]', true);
$msa_report = json_decode($result['msa_report'] ?? '[]', true);

if (!is_array($conservation_scores)) {
    $conservation_scores = [];
}
if (!is_array($msa_report)) {
    $msa_report = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Example MSA</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="msa-container">
    <div class="msa-sidebar">
        <h2>Example Dataset</h2>
        <a href="example.php" class="back-button">← Back to Example</a>
        <hr>
        <h3>Selected Proteins</h3>

        <?php foreach ($selected_data as $row): ?>
            <div class="protein-card">
                <strong><?= htmlspecialchars($row['accession_id']) ?></strong><br>
                <?= htmlspecialchars($row['description']) ?><br>
                <small>
                    Length: <?= htmlspecialchars((string)$row['seq_length']) ?><br>
                    Taxon: <?= htmlspecialchars($row['taxon_group']) ?>
                </small>
            </div>
        <?php endforeach; ?>

        <a href="example_prosite.php" class="enter-button" style="display:inline-block; text-decoration:none;">Example Motif Analysis</a>
        <br><br>
        <a href="example_tree.php" class="enter-button" style="display:inline-block; text-decoration:none;">Example Tree</a>
        <br><br>
        <a href="analysis_summary.php" class="enter-button" style="display:inline-block; text-decoration:none;">Summary Report</a>
    </div>

    <div class="msa-main">
        <?php if (!empty($alignment_display)): ?>

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
                            <td><?= htmlspecialchars((string)($msa_report['sequence_count'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <td>Alignment length</td>
                            <td><?= htmlspecialchars((string)($msa_report['alignment_length'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <td>Average conservation score</td>
                            <td><?= htmlspecialchars((string)($msa_report['average_score'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <td>Highly conserved positions (≥ 0.9)</td>
                            <td><?= htmlspecialchars((string)($msa_report['high_sites'] ?? 0)) ?></td>
                        </tr>
                        <tr>
                            <td>Interpretation</td>
                            <td><?= htmlspecialchars($msa_report['message'] ?? '') ?></td>
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

            <div class="msa-box" style="margin-top:20px;">
                <?= render_msa_html($alignment_display) ?>
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

        <?php else: ?>
            <p>No example MSA result found. Please run <strong>example_generate.php</strong> first.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

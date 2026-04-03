<?php
$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";
$dataset_name = 'aves_g6pase_example';

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

$proteinStmt = $conn->prepare(
    "SELECT accession_id, description, seq_length, taxon_group, protein_name
     FROM example_dataset
     WHERE dataset_name = ?
     ORDER BY display_order ASC"
);
$proteinStmt->execute([$dataset_name]);
$proteins = $proteinStmt->fetchAll();

$accession = $_GET['accession'] ?? ($proteins[0]['accession_id'] ?? '');

$protein_info = null;
foreach ($proteins as $p) {
    if ($p['accession_id'] === $accession) {
        $protein_info = $p;
        break;
    }
}

$motifStmt = $conn->prepare(
    "SELECT motif_json, residue_scores_json, motif_report_json
     FROM example_motif_results
     WHERE dataset_name = ? AND accession_id = ?
     LIMIT 1"
);
$motifStmt->execute([$dataset_name, $accession]);
$motifData = $motifStmt->fetch();

$results = json_decode($motifData['motif_json'] ?? '[]', true);
$residue_scores = json_decode($motifData['residue_scores_json'] ?? '[]', true);
$motif_reports = json_decode($motifData['motif_report_json'] ?? '[]', true);

if (!is_array($results)) {
    $results = [];
}
if (!is_array($residue_scores)) {
    $residue_scores = [];
}
if (!is_array($motif_reports)) {
    $motif_reports = [];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Example PROSITE Analysis</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>Example PROSITE Motif Analysis</h2>

    <a href="example.php" class="back-button">← Back to Example</a>
    <a href="example_msa.php" class="back-button">← Back to Example MSA</a>

    <br><br>

    <form method="get">
        <label for="accession"><strong>Select example protein:</strong></label>
        <select name="accession" id="accession" onchange="this.form.submit()">
            <?php foreach ($proteins as $p): ?>
                <option value="<?= htmlspecialchars($p['accession_id']) ?>" <?= $p['accession_id'] === $accession ? 'selected' : '' ?>>
                    <?= htmlspecialchars($p['accession_id']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if ($protein_info): ?>
        <div class="card">
            <h3>Protein: <?= htmlspecialchars($protein_info['accession_id']) ?></h3>
            <p><?= htmlspecialchars($protein_info['description']) ?></p>
            <p><strong>Length:</strong> <?= htmlspecialchars((string)$protein_info['seq_length']) ?> aa</p>
        </div>

        <?php if (!empty($residue_scores)): ?>
            <h3>Conservation and Motif Overview</h3>
            <div style="background:#ffffff; padding:12px; border-radius:6px;">
                <canvas id="combinedChart" height="140"></canvas>
            </div>
        <?php endif; ?>

        <h3>Functional Architecture</h3>
        <p>Hover over the block to view details</p>
        <div class="motif-bar-container">
            <?php
            $seq_len = max(1, intval($protein_info['seq_length']));
            foreach ($results as $m):
                $left = ($m['start'] / $seq_len) * 100;
                $width = (($m['end'] - $m['start'] + 1) / $seq_len) * 100;
                if ($width < 1.2) $width = 1.2;

                $color = '#3498db';
                if (strpos($m['name'], 'PHOSPHO') !== false) $color = '#e67e22';
                if (strpos($m['name'], 'GLYCO') !== false) $color = '#9b59b6';

                $tooltip = $m['accession'] . ": " . $m['description'] . " (" . $m['start'] . "-" . $m['end'] . ")";
            ?>
                <div class="motif-track"
                     style="left: <?= $left ?>%; width: <?= $width ?>%; background-color: <?= $color ?>;"
                     data-tooltip="<?= htmlspecialchars($tooltip) ?>">
                </div>
            <?php endforeach; ?>
        </div>
        <p>Phospho site in orange; Glyco site in purple; Others in blue</p>

        <?php if (!empty($motif_reports)): ?>
            <h3>Conservation-linked Report</h3>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>Motif</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Average Conservation</th>
                        <th>Interpretation</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($motif_reports as $r): ?>
                        <tr>
                            <td><?= htmlspecialchars($r['name']) ?></td>
                            <td><?= htmlspecialchars((string)$r['start']) ?></td>
                            <td><?= htmlspecialchars((string)$r['end']) ?></td>
                            <td><?= htmlspecialchars((string)$r['avg_score']) ?></td>
                            <td><?= htmlspecialchars($r['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

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
                            <td>
                                <?php if (preg_match('/^PS\d{5}$/', $m['accession'] ?? '')): ?>
                                    <a href="https://prosite.expasy.org/<?= rawurlencode($m['accession']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($m['accession']) ?></a>
                                <?php else: ?>
                                    <?= htmlspecialchars($m['accession']) ?>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['name']) ?></td>
                            <td><?= htmlspecialchars($m['description']) ?></td>
                            <td><?= htmlspecialchars((string)$m['start']) ?></td>
                            <td><?= htmlspecialchars((string)$m['end']) ?></td>
                            <td style="font-family: monospace; font-weight: bold; color: #d62728;"><?= htmlspecialchars($m['match']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if (!empty($residue_scores)): ?>
        <script>
        const combinedScores = <?= json_encode($residue_scores) ?>;
        const combinedLabels = combinedScores.map((_, i) => i + 1);
        const motifs = <?= json_encode($results) ?>;

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

        const motifOverlayPlugin = {
            id: 'motifOverlayPlugin',
            afterDatasetsDraw(chart) {
                const {ctx, chartArea, scales} = chart;
                if (!chartArea) return;

                const baseY = chartArea.bottom - 20;
                const barHeight = 10;

                motifs.forEach(m => {
                    let color = '#3498db';
                    if ((m.name || '').includes('PHOSPHO')) color = '#e67e22';
                    if ((m.name || '').includes('GLYCO')) color = '#9b59b6';

                    const xStart = scales.x.getPixelForValue(m.start - 1);
                    const xEnd = scales.x.getPixelForValue(m.end - 1);

                    ctx.save();
                    ctx.fillStyle = color;
                    ctx.fillRect(xStart, baseY, Math.max(2, xEnd - xStart), barHeight);
                    ctx.restore();
                });
            }
        };

        new Chart(document.getElementById('combinedChart'), {
            type: 'line',
            data: {
                labels: combinedLabels,
                datasets: [{
                    label: 'Conservation score',
                    data: combinedScores,
                    tension: 0.2,
                    pointRadius: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                layout: {
                    padding: {
                        bottom: 24
                    }
                },
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
                            text: 'Residue position'
                        }
                    }
                }
            },
            plugins: [whiteBgPlugin, motifOverlayPlugin]
        });
        </script>
        <?php endif; ?>

    <?php else: ?>
        <p>No example protein found.</p>
    <?php endif; ?>
</div>

</body>
</html>

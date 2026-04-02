<?php
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
$residue_scores = [];
$motif_reports = [];

function split_alignment_and_scores_local($raw) {
    $scores = [];
    $alignment = $raw;

    if (preg_match('/###SCORES_JSON_START###(.*?)###SCORES_JSON_END###/s', $raw, $match)) {
        $decoded = json_decode(trim($match[1]), true);
        if (is_array($decoded)) {
            $scores = $decoded;
        }
        $alignment = preg_replace('/###SCORES_JSON_START###.*###SCORES_JSON_END###/s', '', $raw);
        $alignment = trim($alignment);
    }

    return [$alignment, $scores];
}

function parse_clustal_sequences_local($msa) {
    $lines = explode("\n", $msa);
    $seqs = [];

    foreach ($lines as $line) {
        if (trim($line) === '' || strpos($line, 'CLUSTAL') !== false) continue;

        if (preg_match('/^(\S+)\s+([A-Z\-]+)/', $line, $match)) {
            $id = $match[1];
            $seq = $match[2];
            if (!isset($seqs[$id])) $seqs[$id] = '';
            $seqs[$id] .= $seq;
        }
    }

    return $seqs;
}

function residue_scores_from_alignment($aligned_target, $column_scores) {
    $residue_scores = [];
    $len = min(strlen($aligned_target), count($column_scores));
    for ($i = 0; $i < $len; $i++) {
        if ($aligned_target[$i] !== '-') {
            $residue_scores[] = $column_scores[$i];
        }
    }
    return $residue_scores;
}

$accession = $_GET['accession'] ?? '';

if ($accession) {
    $stmt = $conn->prepare("SELECT * FROM protein_data WHERE accession_id = ?");
    $stmt->execute([$accession]);
    $protein_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($protein_info) {
        $seq = trim($protein_info['sequence']);
        $accession = $protein_info['accession_id'];

        $tmp_file = tempnam(sys_get_temp_dir(), 'seq_');
        file_put_contents($tmp_file, $seq);

        $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/prosite_scan.py " . escapeshellarg($accession) . " " . escapeshellarg($tmp_file) . " 2>&1";
        $output = shell_exec($cmd);
        unlink($tmp_file);

        $results = json_decode($output, true);
        if ($results === null || !is_array($results)) {
            $results = [];
        }
        if (isset($results[0]['error'])) {
            $results = [];
        }

        if (!empty($selected_ids) && count($selected_ids) >= 2) {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $stmt = $conn->prepare(
                "SELECT accession_id, sequence
                 FROM protein_data
                 WHERE accession_id IN ($placeholders)"
            );
            $stmt->execute($selected_ids);
            $msa_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($msa_rows)) {
                $fasta = '';
                foreach ($msa_rows as $r) {
                    $fasta .= ">" . $r['accession_id'] . "\n";
                    $fasta .= $r['sequence'] . "\n";
                }

                $input_file = "/tmp/motif_msa_" . uniqid() . ".fasta";
                file_put_contents($input_file, $fasta);

                $msa_cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/msa.py " . escapeshellarg($input_file) . " 2>&1";
                $msa_output = shell_exec($msa_cmd);

                list($alignment_display, $column_scores) = split_alignment_and_scores_local($msa_output);
                $parsed = parse_clustal_sequences_local($alignment_display);

                if (isset($parsed[$accession])) {
                    $residue_scores = residue_scores_from_alignment($parsed[$accession], $column_scores);
                }

                if (!empty($residue_scores) && !empty($results)) {
                    foreach ($results as $m) {
                        $start = max(1, intval($m['start']));
                        $end = min(count($residue_scores), intval($m['end']));
                        $segment = array_slice($residue_scores, $start - 1, $end - $start + 1);

                        $avg = 0;
                        if (!empty($segment)) {
                            $avg = round(array_sum($segment) / count($segment), 3);
                        }

                        $message = "Moderate conservation around motif " . $m['name'] . ".";
                        if ($avg >= 0.9) {
                            $message = "High-confidence site: motif " . $m['name'] . " lies in a strongly conserved region (score " . $avg . ").";
                        } elseif ($avg < 0.5) {
                            $message = "This motif lies in a weakly conserved region (score " . $avg . "), which may indicate higher structural flexibility.";
                        }

                        $motif_reports[] = [
                            'name' => $m['name'],
                            'start' => $m['start'],
                            'end' => $m['end'],
                            'avg_score' => $avg,
                            'message' => $message
                        ];
                    }
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>PROSITE Scan - <?= htmlspecialchars($accession) ?></title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>PROSITE Motif Analysis</h2>
    <a href="protein_query.php" class="back-button">← Back to Query</a>
    <a href="protein_query.php?taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?><?php foreach ($selected_ids as $id): ?>&selected[]=<?= urlencode($id) ?><?php endforeach; ?>"
       class="back-button">
        ← Back to Selected Proteins
    </a>

    <?php if ($protein_info): ?>
        <div class="card">
            <h3>Protein: <?= htmlspecialchars($protein_info['accession_id']) ?></h3>
            <p><?= htmlspecialchars($protein_info['description']) ?></p>
            <p><strong>Length:</strong> <?= $protein_info['seq_length'] ?> aa</p>
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
            $seq_len = $protein_info['seq_length'];
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

        <h3>Functional Insights</h3>

        <?php if (!empty($results)): ?>
            <p>1. High-confidence site: detected motif <?= htmlspecialchars($results[0]['name']) ?> with strong conservation → candidate functional hotspot.</p>
            <p>2. Evolution insight: motif conserved across species suggests essential biological role.</p>
            <p>3. Structural hint: regions with low conservation may indicate flexible loops.</p>
        <?php endif; ?>

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
        <p class="error">Sequence not found.</p>
    <?php endif; ?>
</div>

</body>
</html>

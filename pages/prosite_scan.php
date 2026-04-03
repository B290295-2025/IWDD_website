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
$confidence_site = null;


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

function residue_scores_from_alignment_local($aligned_target, $column_scores) {
    $residue_scores = [];
    $len = min(strlen($aligned_target), count($column_scores));
    for ($i = 0; $i < $len; $i++) {
        if ($aligned_target[$i] !== '-') {
            $residue_scores[] = $column_scores[$i];
        }
    }
    return $residue_scores;
}

function is_official_prosite_accession_local($acc) {
    return is_string($acc) && preg_match('/^PS\d{5}$/', $acc);
}

function is_frequent_pattern_local($acc) {
    return in_array($acc, ['PS00001','PS00004','PS00005','PS00006','PS00008','PS00009','PS00017'], true);
}

function motif_specificity_factor_local($start, $end) {
    $length = max(1, intval($end) - intval($start) + 1);
    if ($length <= 3) return 0.85;
    if ($length <= 5) return 0.95;
    if ($length <= 8) return 1.05;
    return 1.15;
}

function motif_authority_factor_local($accession) {
    if (is_official_prosite_accession_local($accession)) {
        return is_frequent_pattern_local($accession) ? 0.75 : 1.2;
    }
    return 0.85;
}

function build_motif_confidence_report_local($motif, $residue_scores) {
    $start = max(1, intval($motif['start'] ?? 0));
    $end = min(count($residue_scores), intval($motif['end'] ?? 0));
    $segment = [];
    if ($end >= $start && !empty($residue_scores)) {
        $segment = array_slice($residue_scores, $start - 1, $end - $start + 1);
    }

    $avg = 0.0;
    $min_score = 0.0;
    $high_fraction = 0.0;
    if (!empty($segment)) {
        $avg = round(array_sum($segment) / count($segment), 3);
        $min_score = round(min($segment), 3);
        $high_count = 0;
        foreach ($segment as $s) {
            if ($s >= 0.75) $high_count++;
        }
        $high_fraction = round($high_count / count($segment), 3);
    }

    $accession = $motif['accession'] ?? '';
    $authority_factor = motif_authority_factor_local($accession);
    $specificity_factor = motif_specificity_factor_local($start, $end);
    $base_score = (0.6 * $avg) + (0.25 * $min_score) + (0.15 * $high_fraction);
    $weighted_score = round($base_score * 10 * $authority_factor * $specificity_factor, 2);

    $official = is_official_prosite_accession_local($accession);
    $frequent = is_frequent_pattern_local($accession);
    $confidence = 'low';
    $message = 'Low-support motif hit under the BLOSUM62-weighted scoring scheme.';

    if ($official && !$frequent && $weighted_score >= 8.0 && $avg >= 0.7 && $min_score >= 0.45) {
        $confidence = 'high';
        $message = 'High-confidence site: official PROSITE motif with strong BLOSUM62 support (weighted score ' . $weighted_score . ').';
    } elseif ($official && !$frequent && $weighted_score >= 5.5 && $avg >= 0.55) {
        $confidence = 'medium';
        $message = 'Moderate-confidence site: official PROSITE motif with moderate BLOSUM62 support (weighted score ' . $weighted_score . ').';
    } elseif ($official && $frequent) {
        $confidence = 'supporting';
        $message = 'Supporting evidence only: frequent PROSITE pattern down-weighted to reduce over-calling (weighted score ' . $weighted_score . ').';
    } elseif (!$official) {
        $confidence = 'custom';
        $message = 'Custom motif hit: retained for project-specific interpretation (weighted score ' . $weighted_score . ').';
    }

    return [
        'accession' => $accession,
        'name' => $motif['name'] ?? '',
        'start' => intval($motif['start'] ?? 0),
        'end' => intval($motif['end'] ?? 0),
        'avg_score' => $avg,
        'min_score' => $min_score,
        'high_fraction' => $high_fraction,
        'weighted_score' => $weighted_score,
        'confidence' => $confidence,
        'message' => $message
    ];
}

function sort_motif_reports_by_weight_local(&$motif_reports) {
    usort($motif_reports, function($a, $b) {
        $cmp = ($b['weighted_score'] ?? 0) <=> ($a['weighted_score'] ?? 0);
        if ($cmp !== 0) return $cmp;
        return ($b['avg_score'] ?? 0) <=> ($a['avg_score'] ?? 0);
    });
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
        if ($results === null || !is_array($results) || isset($results[0]['error'])) {
            $results = [];
        }

        if (!empty($selected_ids) && count($selected_ids) >= 2) {
            $placeholders = implode(',', array_fill(0, count($selected_ids), '?'));
            $stmt = $conn->prepare("SELECT accession_id, sequence FROM protein_data WHERE accession_id IN ($placeholders)");
            $stmt->execute($selected_ids);
            $msa_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($msa_rows)) {
                $fasta = '';
                foreach ($msa_rows as $r) {
                    $fasta .= ">" . $r['accession_id'] . "
" . $r['sequence'] . "
";
                }

                $input_file = "/tmp/motif_msa_" . uniqid() . ".fasta";
                file_put_contents($input_file, $fasta);

                $msa_cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/msa.py " . escapeshellarg($input_file) . " 2>&1";
                $msa_output = shell_exec($msa_cmd);

                list($alignment_display, $column_scores) = split_alignment_and_scores_local($msa_output);
                $parsed = parse_clustal_sequences_local($alignment_display);

                if (isset($parsed[$accession])) {
                    $residue_scores = residue_scores_from_alignment_local($parsed[$accession], $column_scores);
                }

                if (!empty($residue_scores) && !empty($results)) {
                    foreach ($results as $m) {
                        $motif_reports[] = build_motif_confidence_report_local($m, $residue_scores);
                    }
                    sort_motif_reports_by_weight_local($motif_reports);
                    $confidence_site = $motif_reports[0] ?? null;
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

    <style>
        .motif-track {
            position: absolute;
            top: 8px;
            height: 22px;
            border-radius: 6px;
            cursor: pointer;
        }
        .motif-track:hover::after {
            content: attr(data-tooltip);
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 130%;
            background: rgba(30, 41, 59, 0.96);
            color: #fff;
            padding: 8px 10px;
            border-radius: 6px;
            font-size: 12px;
            line-height: 1.35;
            white-space: nowrap;
            z-index: 9999;
            box-shadow: 0 6px 14px rgba(0,0,0,0.2);
            pointer-events: none;
        }
        .motif-track:hover::before {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: 118%;
            border-width: 6px;
            border-style: solid;
            border-color: rgba(30, 41, 59, 0.96) transparent transparent transparent;
            z-index: 9999;
            pointer-events: none;
        }
        .motif-score-table {
            width: 100%;
            table-layout: auto;
            border-collapse: collapse;
        }
        .motif-score-table th,
        .motif-score-table td {
            padding: 10px 8px;
            text-align: left;
            vertical-align: top;
            line-height: 1.4;
            word-break: normal;
            overflow-wrap: break-word;
            white-space: normal;
        }
        .motif-score-table th:nth-child(2),
        .motif-score-table td:nth-child(2),
        .motif-score-table th:nth-child(3),
        .motif-score-table td:nth-child(3) {
            white-space: nowrap;
        }
    </style>

</head>
<body>
<?php include __DIR__ . '/../components/header.php'; ?>
<div class="page-container">
    <h2>PROSITE Motif Analysis</h2>
    <a href="protein_query.php" class="back-button">← Back to Query</a>
    <a href="protein_query.php?taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?><?php foreach ($selected_ids as $id): ?>&selected[]=<?= urlencode($id) ?><?php endforeach; ?>" class="back-button">← Back to Selected Proteins</a>

    <?php if ($protein_info): ?>
        <div class="card">
            <h3>Protein: <?= htmlspecialchars($protein_info['accession_id']) ?></h3>
            <p><?= htmlspecialchars($protein_info['description']) ?></p>
            <p><strong>Length:</strong> <?= htmlspecialchars((string)$protein_info['seq_length']) ?> aa</p>
        </div>

        <?php if (!empty($residue_scores)): ?>
            <h3>BLOSUM62 Conservation and Motif Overview</h3>
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
                $tooltip = $m['accession'] . ': ' . $m['description'] . ' (' . $m['start'] . '-' . $m['end'] . ')';
            ?>
                <div class="motif-track" style="left: <?= $left ?>%; width: <?= $width ?>%; background-color: <?= $color ?>;" data-tooltip="<?= htmlspecialchars($tooltip) ?>"></div>
            <?php endforeach; ?>
        </div>
        <p>Phospho site in orange; Glyco site in purple; Others in blue</p>

        <h3>Functional Insights</h3>
        <?php if ($confidence_site): ?>
            <p>1. Confidence site: <strong><?= htmlspecialchars($confidence_site['name']) ?></strong> (<?= htmlspecialchars($confidence_site['accession']) ?>) with weighted score <strong><?= htmlspecialchars((string)$confidence_site['weighted_score']) ?></strong> and confidence level <strong><?= htmlspecialchars($confidence_site['confidence']) ?></strong>.</p>
            <p>2. BLOSUM62 support: average motif score <?= htmlspecialchars((string)$confidence_site['avg_score']) ?>; minimum motif score <?= htmlspecialchars((string)$confidence_site['min_score']) ?>.</p>
            <p>3. Interpretation: <?= htmlspecialchars($confidence_site['message']) ?></p>
        <?php elseif (!empty($results)): ?>
            <p>Motifs were detected, but no MSA-based BLOSUM62 support is available for weighted ranking.</p>
        <?php endif; ?>

        <?php if (!empty($motif_reports)): ?>
            <h3>Conservation-linked Report</h3>
            <table class="result-table motif-score-table">
                <thead>
                    <tr>
                        <th>Motif</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Avg BLOSUM62</th>
                        <th>Min BLOSUM62</th>
                        <th>High-score Fraction</th>
                        <th>Weighted Score</th>
                        <th>Confidence</th>
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
                            <td><?= htmlspecialchars((string)$r['min_score']) ?></td>
                            <td><?= htmlspecialchars((string)$r['high_fraction']) ?></td>
                            <td><?= htmlspecialchars((string)$r['weighted_score']) ?></td>
                            <td><?= htmlspecialchars($r['confidence']) ?></td>
                            <td><?= htmlspecialchars($r['message']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <h3>Detected Motifs</h3>
        <table class="result-table">
            <thead><tr><th>Accession</th><th>Name</th><th>Description</th><th>Start</th><th>End</th><th>Match Sequence</th></tr></thead>
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
                ctx.save(); ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, width, height); ctx.restore();
            }
        };
        const motifOverlayPlugin = {
            id: 'motifOverlayPlugin',
            afterDatasetsDraw(chart) {
                const {ctx, chartArea, scales} = chart; if (!chartArea) return;
                const baseY = chartArea.bottom - 20; const barHeight = 10;
                motifs.forEach(m => {
                    let color = '#3498db';
                    if ((m.name || '').includes('PHOSPHO')) color = '#e67e22';
                    if ((m.name || '').includes('GLYCO')) color = '#9b59b6';
                    const xStart = scales.x.getPixelForValue(m.start - 1);
                    const xEnd = scales.x.getPixelForValue(m.end - 1);
                    ctx.save(); ctx.fillStyle = color; ctx.fillRect(xStart, baseY, Math.max(2, xEnd - xStart), barHeight); ctx.restore();
                });
            }
        };
        new Chart(document.getElementById('combinedChart'), {
            type: 'line',
            data: { labels: combinedLabels, datasets: [{ label: 'BLOSUM62 conservation score', data: combinedScores, tension: 0.2, pointRadius: 0 }] },
            options: { responsive: true, maintainAspectRatio: true, layout: { padding: { bottom: 24 } }, scales: { y: { min: 0, max: 1, title: { display: true, text: 'Score' } }, x: { title: { display: true, text: 'Residue position' } } } },
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

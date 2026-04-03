<?php
require_once __DIR__ . '/example_helpers.php';

$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";

$conn = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$dataset_name = 'aves_g6pase_example';
$taxon = 'Aves';
$protein = 'glucose-6-phosphatase';

function parse_fasta_entries($fasta) {
    $entries = explode(">", $fasta);
    $parsed = [];
    foreach ($entries as $entry) {
        if (trim($entry) === '') continue;
        $lines = explode("\n", trim($entry));
        $header = array_shift($lines);
        $sequence = strtoupper(trim(implode("", $lines)));
        preg_match('/^(\S+)/', $header, $match);
        $accession_id = $match[1] ?? '';
        if ($accession_id !== '' && $sequence !== '') {
            $parsed[] = [
                'accession_id' => $accession_id,
                'description' => $header,
                'sequence' => $sequence,
                'seq_length' => strlen($sequence)
            ];
        }
    }
    return $parsed;
}

$conn->prepare("DELETE FROM example_dataset WHERE dataset_name = ?")->execute([$dataset_name]);
$conn->prepare("DELETE FROM example_results WHERE dataset_name = ?")->execute([$dataset_name]);
$conn->prepare("DELETE FROM example_motif_results WHERE dataset_name = ?")->execute([$dataset_name]);

$stmt = $conn->prepare(
    "SELECT accession_id, description, sequence, seq_length, taxon_group, protein_name
     FROM protein_data
     WHERE taxon_group = ? AND protein_name = ?
     ORDER BY accession_id ASC
     LIMIT 5"
);
$stmt->execute([$taxon, $protein]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (count($rows) < 5) {
    $cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/fetch_protein.py " . escapeshellarg($taxon) . " " . escapeshellarg($protein) . " 2>&1";
    $fasta = shell_exec($cmd);
    if (!$fasta || stripos($fasta, 'Error') !== false) {
        die("Error generating example dataset: " . htmlspecialchars($fasta ?: 'Fetch failed'));
    }
    $parsed_entries = parse_fasta_entries($fasta);
    $insertStmt = $conn->prepare(
        "INSERT IGNORE INTO protein_data (taxon_group, protein_name, accession_id, description, sequence, seq_length)
         VALUES (:taxon_group, :protein_name, :accession_id, :description, :sequence, :seq_length)"
    );
    foreach ($parsed_entries as $entry) {
        $insertStmt->execute([
            ':taxon_group' => $taxon,
            ':protein_name' => $protein,
            ':accession_id' => $entry['accession_id'],
            ':description' => $entry['description'],
            ':sequence' => $entry['sequence'],
            ':seq_length' => $entry['seq_length']
        ]);
    }
    $stmt->execute([$taxon, $protein]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (count($rows) < 2) {
    die("Not enough sequences found to generate example dataset.");
}

$exampleInsert = $conn->prepare(
    "INSERT INTO example_dataset (dataset_name, accession_id, description, sequence, seq_length, taxon_group, protein_name, display_order)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);

$selected_ids = [];
$order_index = 1;
foreach ($rows as $row) {
    $selected_ids[] = $row['accession_id'];
    $exampleInsert->execute([
        $dataset_name, $row['accession_id'], $row['description'], $row['sequence'], $row['seq_length'],
        $row['taxon_group'], $row['protein_name'], $order_index
    ]);
    $order_index++;
}

$fasta_text = '';
foreach ($rows as $row) {
    $fasta_text .= '>' . $row['accession_id'] . "\n" . $row['sequence'] . "\n";
}

$input_file = "/tmp/example_msa_" . uniqid() . ".fasta";
file_put_contents($input_file, $fasta_text);

$msa_cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/msa.py " . escapeshellarg($input_file) . " 2>&1";
$msa_output = shell_exec($msa_cmd);
if (!$msa_output || strpos($msa_output, 'Error:') === 0) {
    die("MSA generation failed: " . htmlspecialchars($msa_output ?: 'Unknown MSA error'));
}

list($alignment_display, $conservation_scores) = split_alignment_and_scores($msa_output);
$msa_report = build_msa_report($conservation_scores, count($rows));

$aln_file = "/tmp/example_tree_aln_" . uniqid() . ".fasta";
$clustalo_cmd = "clustalo -i " . escapeshellarg($input_file) . " -o " . escapeshellarg($aln_file) . " --force --outfmt=fasta 2>&1";
shell_exec($clustalo_cmd);

$tree_newick = '';
$tree_png_base64 = '';
if (file_exists($aln_file) && filesize($aln_file) > 0) {
    $tree_cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/tree.py " . escapeshellarg($aln_file) . " 2>&1";
    $tree_output = shell_exec($tree_cmd);
    $tree_data = json_decode($tree_output, true);
    if (is_array($tree_data) && !isset($tree_data['error'])) {
        $tree_newick = $tree_data['newick'] ?? '';
        $png_file = $tree_data['png_file'] ?? '';
        if ($png_file && file_exists($png_file)) {
            $tree_png_base64 = base64_encode(file_get_contents($png_file));
        }
    }
}

$parsed_alignment = parse_clustal_sequences($alignment_display);
$motif_counts = [];
$motifInsert = $conn->prepare(
    "INSERT INTO example_motif_results (dataset_name, accession_id, motif_json, residue_scores_json, motif_report_json)
     VALUES (?, ?, ?, ?, ?)"
);

foreach ($rows as $row) {
    $acc = $row['accession_id'];
    $seq = trim($row['sequence']);
    $tmp_file = tempnam(sys_get_temp_dir(), 'example_seq_');
    file_put_contents($tmp_file, $seq);

    $prosite_cmd = "/usr/bin/python3 " . __DIR__ . "/../backend/prosite_scan.py " . escapeshellarg($acc) . " " . escapeshellarg($tmp_file) . " 2>&1";
    $prosite_output = shell_exec($prosite_cmd);
    unlink($tmp_file);

    $motifs = json_decode($prosite_output, true);
    if (!is_array($motifs) || isset($motifs[0]['error'])) {
        $motifs = [];
    }

    $residue_scores = [];
    if (isset($parsed_alignment[$acc])) {
        $residue_scores = residue_scores_from_alignment($parsed_alignment[$acc], $conservation_scores);
    }

    $motif_reports = [];
    foreach ($motifs as $m) {
        $motif_reports[] = build_motif_confidence_report($m, $residue_scores);
    }
    sort_motif_reports_by_weight($motif_reports);
    $motif_counts[] = count($motifs);

    $motifInsert->execute([
        $dataset_name,
        $acc,
        json_encode($motifs),
        json_encode($residue_scores),
        json_encode($motif_reports)
    ]);
}

$summary_report = build_summary_report($rows, $msa_report, $motif_counts);
$exampleResultsInsert = $conn->prepare(
    "INSERT INTO example_results (dataset_name, selected_ids, msa_alignment, conservation_scores, msa_report, tree_newick, tree_png_base64, summary_report)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
);
$exampleResultsInsert->execute([
    $dataset_name,
    implode(',', $selected_ids),
    $alignment_display,
    json_encode($conservation_scores),
    json_encode($msa_report),
    $tree_newick,
    $tree_png_base64,
    json_encode($summary_report)
]);

echo 'Example dataset generated successfully.';

<?php

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
        if (trim($line) === '' || strpos($line, 'CLUSTAL') !== false) {
            continue;
        }

        if (preg_match('/^(\S+)\s+([A-Z\-]+)/', $line, $match)) {
            $id = $match[1];
            $seq = $match[2];

            if (!isset($seqs[$id])) {
                $seqs[$id] = '';
            }
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
        if ($s >= 0.9) {
            $high_sites++;
        }
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

function render_msa_html($msa) {
    $lines = explode("\n", $msa);
    $seqs = [];

    foreach ($lines as $line) {
        if (trim($line) === '' || strpos($line, 'CLUSTAL') !== false) {
            continue;
        }

        if (preg_match('/^(\S+)\s+([A-Z\-]+)/', $line, $match)) {
            $id = $match[1];
            $seq = $match[2];

            if (!isset($seqs[$id])) {
                $seqs[$id] = '';
            }
            $seqs[$id] .= $seq;
        }
    }

    if (empty($seqs)) {
        return '';
    }

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

                if (strpos("AVLIMFWY", $c) !== false) {
                    $class = "aa-hydrophobic";
                } elseif (strpos("STNQ", $c) !== false) {
                    $class = "aa-polar";
                } elseif (strpos("KRH", $c) !== false) {
                    $class = "aa-positive";
                } elseif (strpos("DE", $c) !== false) {
                    $class = "aa-negative";
                } elseif (strpos("GPC", $c) !== false) {
                    $class = "aa-special";
                } elseif ($c === '-') {
                    $class = "aa-gap";
                }

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
                if (isset($seq[$j])) {
                    $chars[] = $seq[$j];
                }
            }

            if (count($chars) > 0 && count(array_unique($chars)) === 1 && $chars[0] !== '-') {
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

function build_summary_report($rows, $msa_report, $motif_counts) {
    $seq_lengths = [];
    foreach ($rows as $row) {
        $seq_lengths[] = intval($row['seq_length']);
    }

    $min_len = !empty($seq_lengths) ? min($seq_lengths) : 0;
    $max_len = !empty($seq_lengths) ? max($seq_lengths) : 0;
    $avg_len = !empty($seq_lengths) ? round(array_sum($seq_lengths) / count($seq_lengths), 2) : 0;

    $total_motifs = array_sum($motif_counts);

    return [
        'dataset' => 'Aves glucose-6-phosphatase example dataset',
        'query_taxon' => 'Aves',
        'query_protein' => 'glucose-6-phosphatase',
        'sequence_count' => count($rows),
        'length_min' => $min_len,
        'length_max' => $max_len,
        'length_avg' => $avg_len,
        'alignment_length' => $msa_report['alignment_length'] ?? 0,
        'average_conservation' => $msa_report['average_score'] ?? 0,
        'high_sites' => $msa_report['high_sites'] ?? 0,
        'total_motifs' => $total_motifs,
        'message' => $msa_report['message'] ?? ''
    ];
}

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
            'message' => 'BLOSUM62 conservation score is not available for this alignment.'
        ];
    }

    $avg = array_sum($scores) / count($scores);
    $high_sites = 0;

    foreach ($scores as $s) {
        if ($s >= 0.8) {
            $high_sites++;
        }
    }

    $message = 'Moderate BLOSUM62-supported conservation detected across the alignment.';
    if ($avg >= 0.75) {
        $message = 'Strong BLOSUM62-supported conservation detected across the alignment.';
    } elseif ($avg < 0.45) {
        $message = 'The selected sequences are relatively divergent under the BLOSUM62-based conservation metric.';
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

function is_official_prosite_accession($acc) {
    return is_string($acc) && preg_match('/^PS\d{5}$/', $acc);
}

function is_frequent_pattern($acc) {
    $frequent = [
        'PS00001',
        'PS00004',
        'PS00005',
        'PS00006',
        'PS00008',
        'PS00009',
        'PS00017'
    ];

    return in_array($acc, $frequent, true);
}

function build_motif_confidence_report($motif, $residue_scores) {
    $start = max(1, intval($motif['start'] ?? 0));
    $end = min(count($residue_scores), intval($motif['end'] ?? 0));
    $segment = [];

    if ($end >= $start && !empty($residue_scores)) {
        $segment = array_slice($residue_scores, $start - 1, $end - $start + 1);
    }

    $avg = 0.0;
    $min_score = 0.0;
    $high_count = 0;
    $high_fraction = 0.0;
    $motif_length = max(1, $end - $start + 1);

    if (!empty($segment)) {
        $avg = round(array_sum($segment) / count($segment), 3);
        $min_score = round(min($segment), 3);
        foreach ($segment as $s) {
            if ($s >= 0.8) {
                $high_count++;
            }
        }
        $high_fraction = round($high_count / count($segment), 3);
    }

    $accession = $motif['accession'] ?? '';
    $official = is_official_prosite_accession($accession);
    $frequent = is_frequent_pattern($accession);

    $authority_factor = 0.55;
    if ($official && !$frequent) {
        $authority_factor = 1.0;
    } elseif ($official && $frequent) {
        $authority_factor = 0.7;
    }

    $specificity_factor = min(1.0, 0.45 + (min($motif_length, 12) / 20.0));
    $conservation_support = round((0.6 * $avg) + (0.25 * $min_score) + (0.15 * $high_fraction), 3);
    $weighted_score = round(100 * $conservation_support * $authority_factor * $specificity_factor, 2);

    $confidence = 'low';
    $message = 'Low-support motif hit. Consider it tentative unless backed by stronger conservation or external evidence.';

    if ($official && !$frequent && $weighted_score >= 75 && $avg >= 0.75 && $min_score >= 0.45) {
        $confidence = 'high';
        $message = "High-confidence site: official PROSITE motif with strong BLOSUM-supported conservation (weighted score {$weighted_score}).";
    } elseif ($official && $weighted_score >= 55) {
        $confidence = 'medium';
        $message = "Moderate-confidence site: official PROSITE motif with moderate BLOSUM conservation support (weighted score {$weighted_score}).";
    } elseif ($official && $frequent) {
        $confidence = 'supporting';
        $message = "Supporting evidence only: frequent PROSITE pattern down-weighted to reduce over-calling (weighted score {$weighted_score}).";
    } elseif (!$official) {
        $confidence = 'custom';
        $message = "Custom project motif: informative for your dataset, but not an official PROSITE assignment (weighted score {$weighted_score}).";
    }

    return [
        'accession' => $accession,
        'name' => $motif['name'] ?? '',
        'start' => $motif['start'] ?? 0,
        'end' => $motif['end'] ?? 0,
        'avg_score' => $avg,
        'min_score' => $min_score,
        'high_fraction' => $high_fraction,
        'motif_length' => $motif_length,
        'conservation_support' => $conservation_support,
        'weighted_score' => $weighted_score,
        'confidence' => $confidence,
        'message' => $message
    ];
}

function sort_motif_reports_by_weight(&$motif_reports) {
    usort($motif_reports, function ($a, $b) {
        return ($b['weighted_score'] ?? 0) <=> ($a['weighted_score'] ?? 0);
    });
}

function pick_confidence_site($motif_reports) {
    if (empty($motif_reports) || !is_array($motif_reports)) {
        return null;
    }

    $sorted = $motif_reports;
    sort_motif_reports_by_weight($sorted);
    return $sorted[0] ?? null;
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

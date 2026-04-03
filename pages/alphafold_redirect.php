<?php
$selected_ids = $_POST['selected'] ?? $_GET['selected'] ?? [];
$taxon = trim($_POST['taxon'] ?? $_GET['taxon'] ?? '');
$protein = trim($_POST['protein'] ?? $_GET['protein'] ?? '');
$error = '';

$dsn = "mysql:host=127.0.0.1;dbname=s2845297_website;charset=utf8mb4";
$user = "s2845297";
$pass = "YuQ1LiN030709!";

try {
    $conn = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function http_request($url, $method = 'GET', $post_fields = null, $headers = []) {
    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($post_fields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);

    curl_close($ch);

    return [
        'body' => $response,
        'http_code' => $http_code,
        'error' => $curl_error
    ];
}

function submit_uniprot_mapping_job($accession_id) {
    $url = "https://rest.uniprot.org/idmapping/run";

    $post_fields = http_build_query([
        'from' => 'RefSeq_Protein',
        'to' => 'UniProtKB',
        'ids' => $accession_id
    ]);

    $response = http_request(
        $url,
        'POST',
        $post_fields,
        ['Content-Type: application/x-www-form-urlencoded']
    );

    if (!empty($response['error']) || empty($response['body']) || $response['http_code'] >= 400) {
        return [null, "Failed to connect to UniProt mapping service."];
    }

    $json = json_decode($response['body'], true);

    if (!is_array($json) || empty($json['jobId'])) {
        return [null, "UniProt mapping job submission failed."];
    }

    return [$json['jobId'], null];
}

function fetch_uniprot_results($job_id) {
    $status_url = "https://rest.uniprot.org/idmapping/status/" . rawurlencode($job_id);
    $results_url = "https://rest.uniprot.org/idmapping/results/" . rawurlencode($job_id);

    for ($i = 0; $i < 15; $i++) {
        $status_response = http_request($status_url);

        if (!empty($status_response['body']) && $status_response['http_code'] < 400) {
            $status_json = json_decode($status_response['body'], true);

            if (is_array($status_json)) {
                if (isset($status_json['results']) || isset($status_json['failedIds'])) {
                    $results_response = http_request($results_url);

                    if (!empty($results_response['body']) && $results_response['http_code'] < 400) {
                        $results_json = json_decode($results_response['body'], true);
                        if (is_array($results_json)) {
                            return [$results_json, null];
                        }
                    }

                    return [null, "UniProt mapping results could not be retrieved."];
                }

                if (isset($status_json['jobStatus']) && $status_json['jobStatus'] === 'FAILED') {
                    return [null, "UniProt mapping job failed."];
                }
            }
        }

        sleep(2);
    }

    return [null, "UniProt mapping timed out."];
}

function extract_uniprot_accession($results_json) {
    if (!is_array($results_json) || empty($results_json['results']) || !is_array($results_json['results'])) {
        return '';
    }

    foreach ($results_json['results'] as $item) {
        if (!isset($item['to'])) {
            continue;
        }

        if (is_string($item['to']) && trim($item['to']) !== '') {
            return trim($item['to']);
        }

        if (is_array($item['to'])) {
            if (!empty($item['to']['primaryAccession'])) {
                return trim($item['to']['primaryAccession']);
            }

            if (!empty($item['to']['uniProtkbId'])) {
                return trim($item['to']['uniProtkbId']);
            }

            if (!empty($item['to']['id'])) {
                return trim($item['to']['id']);
            }
        }
    }

    return '';
}

function map_ncbi_to_uniprot($accession_id) {
    $try_ids = [$accession_id];

    if (preg_match('/^([A-Z_0-9]+)\.\d+$/i', $accession_id, $m)) {
        $try_ids[] = $m[1];
    }

    $try_ids = array_values(array_unique($try_ids));

    foreach ($try_ids as $id) {
        list($job_id, $job_error) = submit_uniprot_mapping_job($id);
        if ($job_error || !$job_id) {
            continue;
        }

        list($results_json, $results_error) = fetch_uniprot_results($job_id);
        if ($results_error || !$results_json) {
            continue;
        }

        $uniprot_accession = extract_uniprot_accession($results_json);
        if ($uniprot_accession !== '') {
            return [$uniprot_accession, null];
        }
    }

    return ['', "No UniProt accession was found for this NCBI accession."];
}

function fetch_alphafold_entry_id($uniprot_accession) {
    $url = "https://alphafold.ebi.ac.uk/api/prediction/" . rawurlencode($uniprot_accession);
    $response = http_request($url);

    if (!empty($response['error']) || empty($response['body']) || $response['http_code'] >= 400) {
        return ['', "Failed to connect to AlphaFold API."];
    }

    $json = json_decode($response['body'], true);

    if (!is_array($json) || empty($json)) {
        return ['', "AlphaFold did not return prediction metadata."];
    }

    if (isset($json[0]) && is_array($json[0]) && !empty($json[0]['entryId'])) {
        return [trim($json[0]['entryId']), null];
    }

    if (isset($json['entryId']) && !empty($json['entryId'])) {
        return [trim($json['entryId']), null];
    }

    return ['', "AlphaFold entry ID was not found."];
}

function clean_protein_sequence($sequence) {
    $sequence = strtoupper(trim($sequence));
    $sequence = preg_replace('/[^A-Z]/', '', $sequence);
    return $sequence;
}

function is_valid_alphafold_sequence($sequence) {
    if ($sequence === '' || strlen($sequence) < 20) {
        return false;
    }

    return preg_match('/^[ACDEFGHIKLMNPQRSTVWY]+$/', $sequence) === 1;
}

if (count($selected_ids) !== 1) {
    $error = "Please select exactly 1 protein to view 3D structure.";
} else {
    $accession_id = $selected_ids[0];

    $stmt = $conn->prepare(
        "SELECT accession_id, description, taxon_group, protein_name, sequence
         FROM protein_data
         WHERE accession_id = ?"
    );
    $stmt->execute([$accession_id]);
    $row = $stmt->fetch();

    if (!$row) {
        $error = "Selected protein was not found in database.";
    } else {
        list($uniprot_accession, $map_error) = map_ncbi_to_uniprot($row['accession_id']);

        if ($uniprot_accession !== '') {
            list($entry_id, $af_error) = fetch_alphafold_entry_id($uniprot_accession);

            if ($entry_id !== '') {
                $alphafold_url = "https://alphafold.ebi.ac.uk/entry/" . rawurlencode($entry_id);
                header("Location: " . $alphafold_url);
                exit;
            }

            $fallback_uniprot_search_url = "https://alphafold.ebi.ac.uk/search/text/" . rawurlencode($uniprot_accession);
            header("Location: " . $fallback_uniprot_search_url);
            exit;
        }

        $sequence = clean_protein_sequence($row['sequence'] ?? '');

        if (is_valid_alphafold_sequence($sequence)) {
            $sequence_search_url = "https://alphafold.ebi.ac.uk/search/text/" . rawurlencode($sequence);
            header("Location: " . $sequence_search_url);
            exit;
        }

        $error = "UniProt mapping failed, and the protein sequence is not suitable for AlphaFold sequence search.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>AlphaFold Redirect</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>
<body>

<?php include __DIR__ . '/../components/header.php'; ?>

<div class="page-container">
    <h2>AlphaFold 3D Structure</h2>

    <?php if ($error): ?>
        <p class="error"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <a href="protein_query.php?taxon=<?= urlencode($taxon) ?>&protein=<?= urlencode($protein) ?>" class="back-button">
        ← Back to Protein Query
    </a>
</div>

</body>
</html>

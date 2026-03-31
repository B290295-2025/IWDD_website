<!DOCTYPE html>
<html>

<head>
    <title>BioSeq Analysis Platform</title>
    <link rel="stylesheet" href="/~s2845297/B290295_website/frontend/assets/css/style.css">
</head>

<body>

<?php include __DIR__ . '/components/header.php'; ?>

<div class="hero">
    <h1>BioSeq Analysis Platform</h1>
    <p>A web-based toolkit for protein sequence analysis</p>
</div>

<div class="container">

    <!-- Row 1 -->
    <div class="row">
        <div class="card">
            <h3>Protein Query</h3>
            <p>Retrieve protein sequences from NCBI database</p>
            <a href="pages/protein_query.php" class="enter-button">Enter</a>
        </div>

        <div class="card">
            <h3>MSA</h3>
            <p>Perform multiple sequence alignment</p>
            <a href="pages/msa.php" class="enter-button">Enter</a>
        </div>

        <div class="card">
            <h3>MSA Visualization</h3>
            <p>Visualize conserved regions in alignments</p>
            <a href="pages/msa_visualization.php" class="enter-button">Enter</a>
        </div>
    </div>

    <!-- Row 2 -->
    <div class="row">
        <div class="card">
            <h3>PROSITE Scan</h3>
            <p>Identify motifs and domains</p>
            <a href="pages/prosite_scan.php" class="enter-button">Enter</a>
        </div>

        <div class="card">
            <h3>EMBOSS Analysis</h3>
            <p>Run EMBOSS tools</p>
            <a href="pages/emboss.php" class="enter-button">Enter</a>
        </div>

        <div class="card">
            <h3>History</h3>
            <p>View previous results</p>
            <a href="pages/history.php" class="enter-button">Enter</a>
        </div>
    </div>

    <!-- Row 3 -->
    <div class="row">
        <div class="card">
            <h3>Examples</h3>
            <p>Explore workflows</p>
            <a href="pages/examples.php" class="enter-button">Enter</a>
        </div>

        <div class="card">
            <h3>Credits_Statement</h3>
            <p>Project information</p>
            <a href="pages/credits_statement.php" class="enter-button">Enter</a>
        </div>
    </div>

</div>
</body>
</html>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
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
    <div class="row">
        <div class="card">
            <div class="card-icon"></div>
            <h3>Protein Query</h3>
            <p>Search proteins in NCBI to begin your workflow.</p>
            <a href="pages/protein_query.php" class="enter-button">Enter</a>
        </div>

	<div class="card">
            	<h3>MSA Analysis</h3>
            	<p>Run multiple sequence alignment</p>
            	<a href="pages/protein_query.php" class="enter-button">Enter</a>
        </div>

                <div class="card">
            <h3>Build Phylogenetic Tree</h3>
            <p>Upload alignment file</p>
            <a href="pages/tree_upload.php" class="enter-button">Enter</a>
        </div>
    </div>

    <div class="row" style="margin-top: 20px;">
        <div class="card">
            <h3>PROSITE Scan</h3>
            <p>Scan motifs in proteins</p>
            <a href="pages/protein_query.php" class="enter-button">Enter</a>
        </div>

        <div class="card">
            <h3> View Example</h3>
            <p>Provide a Example for how to use this website</p>
            <a href="pages/example.php" class="enter-button">Enter</a>
        </div>


        <div class="card">
            <div class="card-icon"></div>
            <h3>History</h3>
            <p>View your previous analysis results and sessions.</p>
            <a href="pages/history.php" class="enter-button">Enter</a>
        </div>




        
    </div>
</div>

</body>
</html>

<?php ob_start(); ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

<div class="page-container">
<a href="/~s2845297/B290295_website/index.php" class="back-button">
        ← Back to Home
    </a>

    <br><br>

    <h2>About</h2>
    <h3>Overview</h3>

    <div class="card">
        <p>
            This website is an integrated protein sequence analysis platform that utilises web-based search to combine front-end user interaction with back-end analytical workflows. It allows users to search for target proteins by customising taxon groups and protein sets, retrieve protein sequences, and perform downstream analyses, including multiple sequence alignment (MSA), conservation scoring, motif identification and phylogenetic reconstruction.
        </p>
    </div>

    <h3>System Architecture</h3>
    <div class="card">
        <p>
            Through its modular design, this web platform divides data retrieval, processing and visualisation into three independent components. The front-end handles user input, the menu bar and the display of results. The back-end executes analytical tasks and returns the output results.
        </p>
    </div>

    <h3>Data Retrieval</h3>
    <div class="card">
        <p>
            Protein sequences are retrieved via dynamic queries, where users define search parameters to fetch data from external NCBI databases. The results are subsequently saved locally to facilitate reuse and enhance analytical efficiency.
        </p>
    </div>

    <h3>Analysis Workflow</h3>
    <div class="card">
        <p>
            The analysis pipeline adopts a sequential structure. Once the user has selected a set of proteins, these sequences undergo a series of downstream analyses. Data generated at each stage is presented to the user and reused in subsequent analyses, ensuring consistency throughout the process.
        </p>
    </div>

    <h3>Data Reuse and Integration</h3>
    <div class="card">
        <p>
            The alignment data generated during the MSA stage is reused in subsequent calculations of conservation scores. These scores are visualised in the front-end interface and, combined with motif analysis, provide users with additional biological context. The alignment data is utilised multiple times as intermediate data throughout the analysis workflow to reduce redundant calculations and link the various functionalities together. Furthermore, the aligned sequences are used to reconstruct phylogenetic trees to infer evolutionary relationships, generating visual tree structures and Newick-formatted files, whilst also providing users with an export function to facilitate subsequent report writing.
        </p>
    </div>

    <h3>Example Dataset</h3>
    <div class="card">
        <p>
            The website provides sample datasets, utilising pre-processed data to demonstrate all functionalities and minimise real-time queries. This demonstration ensures that new users can understand how each component works and what results to expect.
        </p>
    </div>

    <h3>User Workflow and History</h3>
    <div class="card">
        <p>
            Navigation between different functional pages on the website provides users with a continuous analysis workflow and includes a history storage feature, allowing users to revisit previously generated datasets.
        </p>
    </div>

</div>

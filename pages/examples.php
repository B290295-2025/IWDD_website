<?php ob_start(); ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

<div class="page-container">
<h2>Example</h2>

<p>Example workflow:</p>

<ul>
<li>Protein Query → search ABC transporter</li>
<li>Select sequences → Run MSA</li>
<li>Analyze motifs</li>
<li>Build phylogenetic tree</li>
</ul>

</div>


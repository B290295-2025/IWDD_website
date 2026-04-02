<?php ob_start(); ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>

<div class="page-container">
<h2>Help</h2>

<p>How to use:</p>

<ul>
<li>Enter taxon and protein</li>
<li>Select sequences</li>
<li>Run MSA or motif scan</li>
<li>Build tree</li>
</ul>

</div>

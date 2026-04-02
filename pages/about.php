<?php ob_start(); ?>
<?php
$content = ob_get_clean();
include __DIR__ . '/../layout.php';
?>


<div class="page-container">
<h2>About</h2>

<p>This platform integrates:</p>

<ul>
<li>Sequence retrieval</li>
<li>MSA analysis</li>
<li>Motif detection</li>
<li>Phylogenetic tree construction</li>
</ul>

</div>

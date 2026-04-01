<?php include('../components/header.php'); ?>

<h2 style="text-align:center;">Phylogenetic Tree</h2>

<?php
if(isset($_GET['file'])) {
    $alignment_input = urldecode($_GET['file']); // 用户上传文件

    $output_image = '../uploads/tree.png'; // 输出树图

    // 调用 Python 脚本（Python 内部会先做 MSA，然后构建树）
    $cmd = escapeshellcmd("python3 ../backend/draw_tree.py $alignment_input $output_image");
    exec($cmd, $output, $return_var);

    if($return_var === 0 && file_exists($output_image)) {
        echo "<img src='$output_image' alt='Phylogenetic Tree' style='max-width:90%;height:auto;margin:auto;display:block;'>";
    } else {
        echo "<p style='color:red;'>Error generating tree.</p>";
        echo "<pre>" . implode("\n", $output) . "</pre>";
    }
} else {
    echo "<p>No input file provided.</p>";
}
?>

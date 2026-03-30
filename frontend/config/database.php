<?php
try {
    $pdo = new PDO("sqlite:../data/database.db");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "database connect error: " . $e->getMessage();
}
?>


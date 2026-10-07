<?php
$db = new PDO('sqlite:database/database.sqlite');
$stmt = $db->query('SELECT * FROM facilities');
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($results);
?>
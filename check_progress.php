<?php
require __DIR__ . '/includes/db.php';
$db = Database::connect();
$stmt = $db->query("SELECT slug, title, reading_progress FROM topics WHERE reading_progress > 0");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);

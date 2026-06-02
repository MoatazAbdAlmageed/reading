<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug = $_POST['slug'] ?? '';
    $progress = $_POST['progress'] ?? 0;
    
    if ($slug) {
        $db = Database::connect();
        
        $progress = (float)$progress;
        if ($progress < 0) $progress = 0;
        if ($progress > 100) $progress = 100;
        
        $stmt = $db->prepare("UPDATE topics SET reading_progress = :progress WHERE slug = :slug");
        $success = $stmt->execute([
            ':progress' => $progress,
            ':slug' => $slug
        ]);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => $success]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['error' => 'Invalid request']);

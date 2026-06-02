<?php
/**
 * Reading System — Safe Backup Downloader
 */

require_once __DIR__ . '/includes/functions.php';

if (isset($_GET['file'])) {
    $file = basename($_GET['file']); // Prevents directory traversal attacks like ../../../etc/passwd
    $filepath = __DIR__ . '/backups/' . $file;
    
    if (file_exists($filepath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filepath));
        
        // Clear system output buffers to prevent corrupted file downloads
        ob_clean();
        flush();
        
        readfile($filepath);
        exit;
    }
}

// Redirect back to dashboard if download request is invalid or missing
header("Location: index.php");
exit;

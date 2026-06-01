<?php
/**
 * Reading System Helper Functions with Database & File Synchronization
 */

require_once __DIR__ . '/db.php';

// Define directory for storing markdown topics
define('TOPICS_DIR', __DIR__ . '/../topics');

// Make sure the topics directory exists
if (!is_dir(TOPICS_DIR)) {
    mkdir(TOPICS_DIR, 0777, true);
}

/**
 * Generate a clean, URL-safe slug from a string (supports both English and Arabic)
 */
function slugify($text) {
    $text = strip_tags($text);
    // Replace non-letters or digits with hyphens (supporting Unicode)
    $text = preg_replace('~[^\p{L}\p{N}]+~u', '-', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = mb_strtolower($text, 'UTF-8');
    
    if (empty($text)) {
        return uniqid('topic-');
    }
    
    return $text;
}

/**
 * Parse a markdown file to separate its YAML Front Matter metadata from the Markdown content
 */
function parse_topic_file($filepath) {
    if (!file_exists($filepath)) {
        return null;
    }
    
    $content = file_get_contents($filepath);
    $filename = basename($filepath);
    $slug = pathinfo($filename, PATHINFO_FILENAME);
    
    // Default metadata
    $metadata = [
        'title' => ucwords(str_replace('-', ' ', $slug)),
        'lang' => 'en',
        'date' => date('Y-m-d H:i:s', filemtime($filepath)),
        'slug' => $slug
    ];
    
    $markdown = $content;
    
    // Pattern to match YAML front matter enclosed by ---
    $pattern = '/^---\s*\r?\n(.*?)\r?\n---\s*\r?\n(.*)$/s';
    
    if (preg_match($pattern, $content, $matches)) {
        $front_matter = $matches[1];
        $markdown = $matches[2];
        
        // Parse simple key-value lines
        $lines = explode("\n", $front_matter);
        foreach ($lines as $line) {
            $parts = explode(":", $line, 2);
            if (count($parts) === 2) {
                $key = trim($parts[0]);
                $val = trim($parts[1]);
                // Remove surrounding quotes
                $val = trim($val, "\"'");
                if ($key === 'lang') {
                    $val = strtolower($val) === 'ar' || strtolower($val) === 'arabic' ? 'ar' : 'en';
                }
                $metadata[$key] = $val;
            }
        }
    }
    
    $metadata['slug'] = $slug;
    
    // Simple word count helper that is clean
    $clean_text = strip_tags($markdown);
    // Support Arabic word counting using regex
    $word_count = count(preg_split('/\s+/u', trim($clean_text)));
    $metadata['word_count'] = $word_count;
    $metadata['read_time'] = max(1, ceil($word_count / 150)); // Average reading speed for technical: 150 wpm
    
    return [
        'metadata' => $metadata,
        'markdown' => trim($markdown)
    ];
}

/**
 * Synchronize the database with files on disk
 * - Inserts new files into database
 * - Recreates files if they exist in database but are missing from disk
 */
function sync_database_and_files() {
    $db = Database::connect();
    
    // 1. Get all markdown files in TOPICS_DIR
    $files = glob(TOPICS_DIR . '/*.md');
    $file_slugs = [];
    
    // 2. Fetch all database records
    $stmt = $db->query("SELECT slug, title, lang, created_at FROM topics");
    $db_topics = [];
    while ($row = $stmt->fetch()) {
        $db_topics[$row['slug']] = $row;
    }
    
    foreach ($files as $file) {
        $slug = pathinfo($file, PATHINFO_FILENAME);
        $file_slugs[] = $slug;
        $file_mtime = filemtime($file);
        
        // If file exists on disk but not in the database, insert it
        if (!isset($db_topics[$slug])) {
            $parsed = parse_topic_file($file);
            if ($parsed) {
                $stmt_insert = $db->prepare("INSERT INTO topics (slug, title, lang, content, created_at, updated_at) VALUES (:slug, :title, :lang, :content, :created, :updated)");
                $stmt_insert->execute([
                    ':slug' => $slug,
                    ':title' => $parsed['metadata']['title'],
                    ':lang' => $parsed['metadata']['lang'],
                    ':content' => $parsed['markdown'],
                    ':created' => $parsed['metadata']['date'] ?? date('Y-m-d H:i:s', $file_mtime),
                    ':updated' => date('Y-m-d H:i:s', $file_mtime)
                ]);
            }
        }
    }
    
    // 3. If a record exists in DB but file doesn't exist on disk, recreate the file!
    foreach ($db_topics as $slug => $db_topic) {
        if (!in_array($slug, $file_slugs)) {
            $stmt_content = $db->prepare("SELECT title, lang, content, created_at FROM topics WHERE slug = :slug");
            $stmt_content->execute([':slug' => $slug]);
            $row = $stmt_content->fetch();
            if ($row) {
                $filepath = TOPICS_DIR . '/' . $slug . '.md';
                $file_content = "---\n";
                $file_content .= "title: " . $row['title'] . "\n";
                $file_content .= "lang: " . $row['lang'] . "\n";
                $file_content .= "date: " . $row['created_at'] . "\n";
                $file_content .= "---\n";
                $file_content .= $row['content'];
                file_put_contents($filepath, $file_content);
            }
        }
    }
}

/**
 * Get all topics from the database (with auto-sync)
 */
function get_topics() {
    sync_database_and_files();
    
    $db = Database::connect();
    // Sort by creation date descending
    $stmt = $db->query("SELECT slug, title, lang, created_at, content FROM topics ORDER BY created_at DESC");
    $topics = [];
    
    while ($row = $stmt->fetch()) {
        $clean_text = strip_tags($row['content']);
        $word_count = count(preg_split('/\s+/u', trim($clean_text)));
        $read_time = max(1, ceil($word_count / 150));
        
        $topics[] = [
            'slug' => $row['slug'],
            'title' => $row['title'],
            'lang' => $row['lang'],
            'date' => $row['created_at'],
            'word_count' => $word_count,
            'read_time' => $read_time
        ];
    }
    
    return $topics;
}

/**
 * Get a single topic by slug (with disk fallback check)
 */
function get_topic($slug) {
    $slug = basename($slug);
    $db = Database::connect();
    
    $stmt = $db->prepare("SELECT * FROM topics WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    
    if ($row) {
        $clean_text = strip_tags($row['content']);
        $word_count = count(preg_split('/\s+/u', trim($clean_text)));
        
        return [
            'metadata' => [
                'title' => $row['title'],
                'lang' => $row['lang'],
                'date' => $row['created_at'],
                'slug' => $row['slug'],
                'word_count' => $word_count,
                'read_time' => max(1, ceil($word_count / 150))
            ],
            'markdown' => $row['content']
        ];
    }
    
    // Fallback: If not in DB but file exists, parse and save to DB
    $filepath = TOPICS_DIR . '/' . $slug . '.md';
    if (file_exists($filepath)) {
        $parsed = parse_topic_file($filepath);
        if ($parsed) {
            // Save to DB
            $stmt_insert = $db->prepare("INSERT INTO topics (slug, title, lang, content, created_at, updated_at) VALUES (:slug, :title, :lang, :content, :created, :updated)");
            $stmt_insert->execute([
                ':slug' => $slug,
                ':title' => $parsed['metadata']['title'],
                ':lang' => $parsed['metadata']['lang'],
                ':content' => $parsed['markdown'],
                ':created' => $parsed['metadata']['date'],
                ':updated' => date('Y-m-d H:i:s')
            ]);
            return $parsed;
        }
    }
    
    return null;
}

/**
 * Save a topic (updates filesystem and database)
 */
function save_topic($slug, $title, $lang, $content) {
    $slug = basename($slug);
    $db = Database::connect();
    
    $lang = $lang === 'ar' ? 'ar' : 'en';
    $title = str_replace(["\n", "\r"], "", trim($title));
    $content = trim($content);
    
    // Check if slug exists in DB
    $stmt_check = $db->prepare("SELECT id, created_at FROM topics WHERE slug = :slug");
    $stmt_check->execute([':slug' => $slug]);
    $existing = $stmt_check->fetch();
    
    $now = date('Y-m-d H:i:s');
    
    if ($existing) {
        // Update DB
        $stmt_update = $db->prepare("UPDATE topics SET title = :title, lang = :lang, content = :content, updated_at = :updated WHERE slug = :slug");
        $stmt_update->execute([
            ':title' => $title,
            ':lang' => $lang,
            ':content' => $content,
            ':updated' => $now,
            ':slug' => $slug
        ]);
        $date = $existing['created_at'];
    } else {
        // Insert DB
        if (Database::isSQLite()) {
            $stmt_insert = $db->prepare("INSERT INTO topics (slug, title, lang, content, created_at, updated_at) VALUES (:slug, :title, :lang, :content, :created, :updated)");
            $stmt_insert->execute([
                ':slug' => $slug,
                ':title' => $title,
                ':lang' => $lang,
                ':content' => $content,
                ':created' => $now,
                ':updated' => $now
            ]);
        } else {
            // MySQL auto-handles timestamp but we can set it explicitly
            $stmt_insert = $db->prepare("INSERT INTO topics (slug, title, lang, content, created_at, updated_at) VALUES (:slug, :title, :lang, :content, :created, :updated)");
            $stmt_insert->execute([
                ':slug' => $slug,
                ':title' => $title,
                ':lang' => $lang,
                ':content' => $content,
                ':created' => $now,
                ':updated' => $now
            ]);
        }
        $date = $now;
    }
    
    // Save to Markdown File
    $filepath = TOPICS_DIR . '/' . $slug . '.md';
    $file_content = "---\n";
    $file_content .= "title: $title\n";
    $file_content .= "lang: $lang\n";
    $file_content .= "date: $date\n";
    $file_content .= "---\n";
    $file_content .= $content;
    
    return file_put_contents($filepath, $file_content) !== false;
}

/**
 * Delete a topic (removes from filesystem and database)
 */
function delete_topic($slug) {
    $slug = basename($slug);
    $db = Database::connect();
    
    // Delete from DB
    $stmt = $db->prepare("DELETE FROM topics WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    
    // Delete from Filesystem
    $filepath = TOPICS_DIR . '/' . $slug . '.md';
    if (file_exists($filepath)) {
        return unlink($filepath);
    }
    
    return true;
}

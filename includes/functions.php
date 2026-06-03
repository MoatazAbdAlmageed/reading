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
        'slug' => $slug,
        'categories' => []
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
                if ($key === 'categories') {
                    $cats = array_map('trim', explode(',', $val));
                    $metadata['categories'] = array_filter($cats);
                } else {
                    $metadata[$key] = $val;
                }
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
 * Find or create a category by name, returning its ID
 */
function get_or_create_category($name) {
    $name = trim($name);
    if (empty($name)) {
        return null;
    }
    
    $db = Database::connect();
    $slug = slugify($name);
    
    $stmt = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    $row = $stmt->fetch();
    
    if ($row) {
        return $row['id'];
    }
    
    $stmt_insert = $db->prepare("INSERT INTO categories (name, slug) VALUES (:name, :slug)");
    $stmt_insert->execute([':name' => $name, ':slug' => $slug]);
    return $db->lastInsertId();
}

/**
 * Get all categories with topic counts
 */
function get_categories() {
    $db = Database::connect();
    // Count associated topics
    $sql = "SELECT c.id, c.name, c.slug, COUNT(tc.topic_id) as topic_count 
            FROM categories c 
            LEFT JOIN topic_categories tc ON c.id = tc.category_id 
            GROUP BY c.id, c.name, c.slug 
            ORDER BY c.name ASC";
    $stmt = $db->query($sql);
    return $stmt->fetchAll();
}

/**
 * Get a single category by ID
 */
function get_category($id) {
    $db = Database::connect();
    $stmt = $db->prepare("SELECT * FROM categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Save or update a category
 */
function save_category($id, $name) {
    $name = trim($name);
    if (empty($name)) {
        return false;
    }
    $db = Database::connect();
    $slug = slugify($name);
    
    if ($id) {
        // Update
        $stmt = $db->prepare("UPDATE categories SET name = :name, slug = :slug WHERE id = :id");
        return $stmt->execute([':name' => $name, ':slug' => $slug, ':id' => $id]);
    } else {
        // Insert
        // Check if slug exists
        $stmt_check = $db->prepare("SELECT id FROM categories WHERE slug = :slug");
        $stmt_check->execute([':slug' => $slug]);
        if ($stmt_check->fetch()) {
            return false; // Duplicate
        }
        $stmt = $db->prepare("INSERT INTO categories (name, slug) VALUES (:name, :slug)");
        return $stmt->execute([':name' => $name, ':slug' => $slug]);
    }
}

/**
 * Delete a category
 */
function delete_category($id) {
    $db = Database::connect();
    
    // Delete topic category relationships
    $stmt_rel = $db->prepare("DELETE FROM topic_categories WHERE category_id = :category_id");
    $stmt_rel->execute([':category_id' => $id]);
    
    // Delete category
    $stmt = $db->prepare("DELETE FROM categories WHERE id = :id");
    return $stmt->execute([':id' => $id]);
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
    $stmt = $db->query("SELECT id, slug, title, lang, created_at, updated_at FROM topics");
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
                    ':title' => $slug,
                    ':lang' => $parsed['metadata']['lang'],
                    ':content' => $parsed['markdown'],
                    ':created' => $parsed['metadata']['date'] ?? date('Y-m-d H:i:s', $file_mtime),
                    ':updated' => date('Y-m-d H:i:s', $file_mtime)
                ]);
                $topic_id = $db->lastInsertId();
                
                // Add categories
                if (!empty($parsed['metadata']['categories'])) {
                    foreach ($parsed['metadata']['categories'] as $cat_name) {
                        $cat_id = get_or_create_category($cat_name);
                        if ($cat_id) {
                            $stmt_link = $db->prepare("INSERT INTO topic_categories (topic_id, category_id) VALUES (:topic_id, :category_id)");
                            $stmt_link->execute([':topic_id' => $topic_id, ':category_id' => $cat_id]);
                        }
                    }
                }
            }
        } else {
            // File exists in DB, check if it's newer than database record
            $db_topic = $db_topics[$slug];
            $db_updated = strtotime($db_topic['updated_at']);
            if ($file_mtime > $db_updated + 2) {
                $parsed = parse_topic_file($file);
                if ($parsed) {
                    $stmt_update = $db->prepare("UPDATE topics SET title = :title, lang = :lang, content = :content, updated_at = :updated WHERE slug = :slug");
                    $stmt_update->execute([
                        ':title' => $slug,
                        ':lang' => $parsed['metadata']['lang'],
                        ':content' => $parsed['markdown'],
                        ':updated' => date('Y-m-d H:i:s', $file_mtime),
                        ':slug' => $slug
                    ]);
                    
                    $topic_id = $db_topic['id'];
                    
                    // Sync categories
                    $stmt_del = $db->prepare("DELETE FROM topic_categories WHERE topic_id = :topic_id");
                    $stmt_del->execute([':topic_id' => $topic_id]);
                    
                    if (!empty($parsed['metadata']['categories'])) {
                        foreach ($parsed['metadata']['categories'] as $cat_name) {
                            $cat_id = get_or_create_category($cat_name);
                            if ($cat_id) {
                                $stmt_link = $db->prepare("INSERT INTO topic_categories (topic_id, category_id) VALUES (:topic_id, :category_id)");
                                $stmt_link->execute([':topic_id' => $topic_id, ':category_id' => $cat_id]);
                            }
                        }
                    }
                }
            }
        }
    }
    
    // 3. If a record exists in DB but file doesn't exist on disk, recreate the file!
    foreach ($db_topics as $slug => $db_topic) {
        if (!in_array($slug, $file_slugs)) {
            $stmt_content = $db->prepare("SELECT id, title, lang, content, created_at FROM topics WHERE slug = :slug");
            $stmt_content->execute([':slug' => $slug]);
            $row = $stmt_content->fetch();
            if ($row) {
                $topic_id = $row['id'];
                
                // Fetch categories
                $stmt_cats = $db->prepare("SELECT c.name FROM categories c JOIN topic_categories tc ON c.id = tc.category_id WHERE tc.topic_id = :topic_id");
                $stmt_cats->execute([':topic_id' => $topic_id]);
                $cat_names = [];
                while ($cat_row = $stmt_cats->fetch()) {
                    $cat_names[] = $cat_row['name'];
                }
                
                $filepath = TOPICS_DIR . '/' . $slug . '.md';
                $file_content = "---\n";
                $file_content .= "title: " . $row['title'] . "\n";
                $file_content .= "lang: " . $row['lang'] . "\n";
                $file_content .= "date: " . $row['created_at'] . "\n";
                if (!empty($cat_names)) {
                    $file_content .= "categories: " . implode(', ', $cat_names) . "\n";
                }
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
    $stmt = $db->query("SELECT id, slug, title, lang, created_at, content, reading_progress FROM topics ORDER BY created_at DESC");
    $topics = [];
    
    while ($row = $stmt->fetch()) {
        $clean_text = strip_tags($row['content']);
        $word_count = count(preg_split('/\s+/u', trim($clean_text)));
        $read_time = max(1, ceil($word_count / 150));
        
        // Fetch categories for this topic
        $stmt_cats = $db->prepare("SELECT c.id, c.name, c.slug FROM categories c JOIN topic_categories tc ON c.id = tc.category_id WHERE tc.topic_id = :topic_id");
        $stmt_cats->execute([':topic_id' => $row['id']]);
        $categories = $stmt_cats->fetchAll();
        
        $topics[] = [
            'id' => $row['id'],
            'slug' => $row['slug'],
            'title' => $row['title'],
            'lang' => $row['lang'],
            'date' => $row['created_at'],
            'word_count' => $word_count,
            'read_time' => $read_time,
            'reading_progress' => $row['reading_progress'] ?? 0,
            'categories' => $categories
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
        
        // Fetch categories
        $stmt_cats = $db->prepare("SELECT c.id, c.name, c.slug FROM categories c JOIN topic_categories tc ON c.id = tc.category_id WHERE tc.topic_id = :topic_id");
        $stmt_cats->execute([':topic_id' => $row['id']]);
        $categories = $stmt_cats->fetchAll();
        
        return [
            'id' => $row['id'],
            'metadata' => [
                'title' => $row['title'],
                'lang' => $row['lang'],
                'date' => $row['created_at'],
                'slug' => $row['slug'],
                'word_count' => $word_count,
                'read_time' => max(1, ceil($word_count / 150)),
                'reading_progress' => $row['reading_progress'] ?? 0,
                'categories' => $categories
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
                ':title' => $slug,
                ':lang' => $parsed['metadata']['lang'],
                ':content' => $parsed['markdown'],
                ':created' => $parsed['metadata']['date'],
                ':updated' => date('Y-m-d H:i:s')
            ]);
            $topic_id = $db->lastInsertId();
            
            // Add categories
            if (!empty($parsed['metadata']['categories'])) {
                foreach ($parsed['metadata']['categories'] as $cat_name) {
                    $cat_id = get_or_create_category($cat_name);
                    if ($cat_id) {
                        $stmt_link = $db->prepare("INSERT INTO topic_categories (topic_id, category_id) VALUES (:topic_id, :category_id)");
                        $stmt_link->execute([':topic_id' => $topic_id, ':category_id' => $cat_id]);
                    }
                }
            }
            
            // Refetch with categories
            return get_topic($slug);
        }
    }
    
    return null;
}

/**
 * Save a topic (updates filesystem and database)
 */
function save_topic($slug, $title, $lang, $content, $category_ids = []) {
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
        $topic_id = $existing['id'];
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
        $stmt_insert = $db->prepare("INSERT INTO topics (slug, title, lang, content, created_at, updated_at) VALUES (:slug, :title, :lang, :content, :created, :updated)");
        $stmt_insert->execute([
            ':slug' => $slug,
            ':title' => $title,
            ':lang' => $lang,
            ':content' => $content,
            ':created' => $now,
            ':updated' => $now
        ]);
        $topic_id = $db->lastInsertId();
        $date = $now;
    }
    
    // Sync Category relations
    $stmt_del = $db->prepare("DELETE FROM topic_categories WHERE topic_id = :topic_id");
    $stmt_del->execute([':topic_id' => $topic_id]);
    
    $cat_names = [];
    if (!empty($category_ids)) {
        foreach ($category_ids as $cat_id) {
            $stmt_add = $db->prepare("INSERT INTO topic_categories (topic_id, category_id) VALUES (:topic_id, :category_id)");
            $stmt_add->execute([':topic_id' => $topic_id, ':category_id' => $cat_id]);
            
            // Get category name
            $stmt_cat = $db->prepare("SELECT name FROM categories WHERE id = :id");
            $stmt_cat->execute([':id' => $cat_id]);
            $cat_row = $stmt_cat->fetch();
            if ($cat_row) {
                $cat_names[] = $cat_row['name'];
            }
        }
    }
    
    // Save to Markdown File
    $filepath = TOPICS_DIR . '/' . $slug . '.md';
    $file_content = "---\n";
    $file_content .= "title: $title\n";
    $file_content .= "lang: $lang\n";
    $file_content .= "date: $date\n";
    if (!empty($cat_names)) {
        $file_content .= "categories: " . implode(', ', $cat_names) . "\n";
    }
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
    
    // Get ID of topic first to delete links
    $stmt_id = $db->prepare("SELECT id FROM topics WHERE slug = :slug");
    $stmt_id->execute([':slug' => $slug]);
    $row = $stmt_id->fetch();
    if ($row) {
        $stmt_rel = $db->prepare("DELETE FROM topic_categories WHERE topic_id = :topic_id");
        $stmt_rel->execute([':topic_id' => $row['id']]);
    }
    
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

/**
 * Backup the database (generates standardized portable SQL file for both MySQL & SQLite)
 */
function backup_database() {
    $db = Database::connect();
    $is_sqlite = Database::isSQLite();
    
    $backup_dir = __DIR__ . '/../backups';
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0777, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $filename = "backup_" . ($is_sqlite ? "sqlite" : "mysql") . "_{$timestamp}.sql";
    $filepath = $backup_dir . '/' . $filename;
    
    $sql_dump = "-- Knowledge Reader Database Backup\n";
    $sql_dump .= "-- Generated on " . date('Y-m-d H:i:s') . "\n";
    $sql_dump .= "-- Database engine: " . ($is_sqlite ? "SQLite" : "MySQL") . "\n\n";
    
    $tables = ['categories', 'topics', 'topic_categories'];
    
    foreach ($tables as $table) {
        $sql_dump .= "-- --------------------------------------------------------\n";
        $sql_dump .= "-- Table structure for `{$table}`\n";
        $sql_dump .= "-- --------------------------------------------------------\n";
        
        if ($is_sqlite) {
            // Get SQLite schema
            $stmt = $db->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name = :name");
            $stmt->execute([':name' => $table]);
            $row = $stmt->fetch();
            if ($row) {
                $sql_dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql_dump .= $row['sql'] . ";\n\n";
            }
        } else {
            // Get MySQL schema
            $stmt = $db->query("SHOW CREATE TABLE `{$table}`");
            $row = $stmt->fetch();
            if ($row && isset($row['Create Table'])) {
                $sql_dump .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql_dump .= $row['Create Table'] . ";\n\n";
            }
        }
        
        $sql_dump .= "-- Dumping data for `{$table}`\n";
        $stmt_data = $db->query("SELECT * FROM `{$table}`");
        $rows = $stmt_data->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($rows)) {
            foreach ($rows as $row) {
                $keys = array_keys($row);
                $escaped_keys = array_map(function($k) { return "`{$k}`"; }, $keys);
                
                $values = array_values($row);
                $escaped_values = array_map(function($v) use ($db) {
                    if ($v === null) {
                        return "NULL";
                    }
                    return $db->quote($v);
                }, $values);
                
                $sql_dump .= "INSERT INTO `{$table}` (" . implode(', ', $escaped_keys) . ") VALUES (" . implode(', ', $escaped_values) . ");\n";
            }
        }
        $sql_dump .= "\n";
    }
    
    if (file_put_contents($filepath, $sql_dump) !== false) {
        return $filename;
    }
    
    return false;
}

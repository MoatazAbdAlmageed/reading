<?php
/**
 * Database Helper - MySQL with SQLite Failover/Fallback
 */

class Database {
    private static $pdo = null;
    private static $is_sqlite = false;

    /**
     * Connect to MySQL with failover to SQLite
     */
    public static function connect() {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $host = 'localhost';
        $user = 'root';
        $pass = '';
        $dbname = 'reading_system';

        try {
            // 1. Try to connect to MySQL to check if service is running
            $pdo = new PDO("mysql:host=$host", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Create database if not exists
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            
            // Reconnect to the created database
            self::$pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            self::$is_sqlite = false;
            
            // Initialize tables
            self::initializeMySQLTables(self::$pdo);
            return self::$pdo;
        } catch (PDOException $e) {
            // MySQL failed (not running, bad credentials, etc.) - fallback to SQLite
            return self::connectSQLite();
        }
    }

    /**
     * Fallback connection to SQLite
     */
    private static function connectSQLite() {
        $dbDir = __DIR__ . '/../data';
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0777, true);
        }
        
        $dbPath = $dbDir . '/database.sqlite';
        try {
            self::$pdo = new PDO("sqlite:" . $dbPath);
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            
            self::$is_sqlite = true;
            
            // Initialize tables for SQLite
            self::initializeSQLiteTables(self::$pdo);
            return self::$pdo;
        } catch (PDOException $ex) {
            die("Database Connection failed (Both MySQL and SQLite fallback failed): " . $ex->getMessage());
        }
    }

    /**
     * Check if current connection is SQLite
     */
    public static function isSQLite() {
        self::connect(); // Ensure connection is made
        return self::$is_sqlite;
    }

    /**
     * Create database tables for MySQL
     */
    private static function initializeMySQLTables($pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS `topics` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `title` VARCHAR(255) NOT NULL,
            `lang` VARCHAR(10) NOT NULL DEFAULT 'en',
            `content` LONGTEXT NOT NULL,
            `reading_progress` DECIMAL(5,2) DEFAULT 0,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
        
        try {
            $pdo->exec("ALTER TABLE `topics` ADD COLUMN `reading_progress` DECIMAL(5,2) DEFAULT 0");
        } catch (PDOException $e) {
            // Ignore if column already exists
        }

        $sql_cats = "CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL UNIQUE,
            `slug` VARCHAR(255) NOT NULL UNIQUE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql_cats);

        $sql_topic_cats = "CREATE TABLE IF NOT EXISTS `topic_categories` (
            `topic_id` INT NOT NULL,
            `category_id` INT NOT NULL,
            PRIMARY KEY (`topic_id`, `category_id`),
            FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql_topic_cats);
    }

    /**
     * Create database tables for SQLite
     */
    private static function initializeSQLiteTables($pdo) {
        $sql = "CREATE TABLE IF NOT EXISTS `topics` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `title` VARCHAR(255) NOT NULL,
            `lang` VARCHAR(10) NOT NULL DEFAULT 'en',
            `content` TEXT NOT NULL,
            `reading_progress` DECIMAL(5,2) DEFAULT 0,
            `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
            `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP
        );";
        $pdo->exec($sql);
        
        try {
            $pdo->exec("ALTER TABLE `topics` ADD COLUMN `reading_progress` DECIMAL(5,2) DEFAULT 0");
        } catch (PDOException $e) {
            // Ignore if column already exists
        }

        $sql_cats = "CREATE TABLE IF NOT EXISTS `categories` (
            `id` INTEGER PRIMARY KEY AUTOINCREMENT,
            `name` VARCHAR(255) NOT NULL UNIQUE,
            `slug` VARCHAR(255) NOT NULL UNIQUE
        );";
        $pdo->exec($sql_cats);

        $sql_topic_cats = "CREATE TABLE IF NOT EXISTS `topic_categories` (
            `topic_id` INTEGER NOT NULL,
            `category_id` INTEGER NOT NULL,
            PRIMARY KEY (`topic_id`, `category_id`),
            FOREIGN KEY (`topic_id`) REFERENCES `topics` (`id`) ON DELETE CASCADE,
            FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
        );";
        $pdo->exec($sql_topic_cats);
    }
}

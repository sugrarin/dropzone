<?php
/**
 * Database abstraction layer for file storage
 * Uses SQLite with WAL mode for better concurrent access
 */

define('DB_FILE', __DIR__ . '/data/files.db');

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        try {
            // Create database connection
            $this->pdo = new PDO('sqlite:' . DB_FILE);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Enable WAL mode for better concurrent access
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
            
            // Initialize database schema
            $this->initSchema();
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function initSchema() {
        // Create files table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS files (
                id TEXT PRIMARY KEY,
                name TEXT NOT NULL,
                original_name TEXT NOT NULL,
                extension TEXT NOT NULL,
                size INTEGER NOT NULL,
                upload_date TEXT NOT NULL,
                modified INTEGER DEFAULT 0,
                category TEXT NOT NULL DEFAULT 'All Files'
            )
        ");
        
        // Create categories table
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS categories (
                name TEXT PRIMARY KEY,
                sort_order INTEGER
            )
        ");
        
        // Create index for faster category lookups
        $this->pdo->exec("
            CREATE INDEX IF NOT EXISTS idx_files_category ON files(category)
        ");
        
        // Ensure 'All Files' category exists
        $stmt = $this->pdo->prepare("INSERT OR IGNORE INTO categories (name, sort_order) VALUES (?, ?)");
        $stmt->execute(['All Files', 0]);
    }
    
    public function getPDO() {
        return $this->pdo;
    }
    
    // File operations
    
    public function getAllFiles() {
        $stmt = $this->pdo->query("SELECT * FROM files ORDER BY upload_date DESC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    public function getFileById($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    public function fileExists($id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM files WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }
    
    public function insertFile($fileData) {
        $stmt = $this->pdo->prepare("
            INSERT INTO files (id, name, original_name, extension, size, upload_date, modified, category)
            VALUES (:id, :name, :original_name, :extension, :size, :upload_date, :modified, :category)
        ");
        
        return $stmt->execute([
            ':id' => $fileData['id'],
            ':name' => $fileData['name'],
            ':original_name' => $fileData['originalName'],
            ':extension' => $fileData['extension'],
            ':size' => $fileData['size'],
            ':upload_date' => $fileData['uploadDate'],
            ':modified' => $fileData['modified'] ? 1 : 0,
            ':category' => $fileData['category']
        ]);
    }
    
    public function updateFile($id, $data) {
        $fields = [];
        $params = [':id' => $id];
        
        if (isset($data['name'])) {
            $fields[] = "name = :name";
            $params[':name'] = $data['name'];
        }
        if (isset($data['extension'])) {
            $fields[] = "extension = :extension";
            $params[':extension'] = $data['extension'];
        }
        if (isset($data['size'])) {
            $fields[] = "size = :size";
            $params[':size'] = $data['size'];
        }
        if (isset($data['modified'])) {
            $fields[] = "modified = :modified";
            $params[':modified'] = $data['modified'] ? 1 : 0;
        }
        if (isset($data['category'])) {
            $fields[] = "category = :category";
            $params[':category'] = $data['category'];
        }
        
        if (empty($fields)) {
            return true;
        }
        
        $sql = "UPDATE files SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
    
    public function deleteFile($id) {
        $stmt = $this->pdo->prepare("DELETE FROM files WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
    // Category operations
    
    public function getAllCategories() {
        $stmt = $this->pdo->query("SELECT name FROM categories ORDER BY sort_order, name");
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
    }
    
    public function categoryExists($name) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM categories WHERE name = ?");
        $stmt->execute([$name]);
        return $stmt->fetchColumn() > 0;
    }
    
    public function insertCategory($name) {
        // Get max sort_order
        $maxOrder = $this->pdo->query("SELECT MAX(sort_order) FROM categories")->fetchColumn();
        $sortOrder = $maxOrder !== null ? $maxOrder + 1 : 1;
        
        $stmt = $this->pdo->prepare("INSERT INTO categories (name, sort_order) VALUES (?, ?)");
        return $stmt->execute([$name, $sortOrder]);
    }
    
    public function renameCategory($oldName, $newName) {
        $this->pdo->beginTransaction();
        try {
            // Update category name
            $stmt = $this->pdo->prepare("UPDATE categories SET name = ? WHERE name = ?");
            $stmt->execute([$newName, $oldName]);
            
            // Update all files with this category
            $stmt = $this->pdo->prepare("UPDATE files SET category = ? WHERE category = ?");
            $stmt->execute([$newName, $oldName]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function deleteCategory($name) {
        $this->pdo->beginTransaction();
        try {
            // Update all files with this category to 'All Files'
            $stmt = $this->pdo->prepare("UPDATE files SET category = 'All Files' WHERE category = ?");
            $stmt->execute([$name]);
            
            // Delete category
            $stmt = $this->pdo->prepare("DELETE FROM categories WHERE name = ?");
            $stmt->execute([$name]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    public function resortCategories() {
        // Get all categories except 'All Files'
        $stmt = $this->pdo->query("SELECT name FROM categories WHERE name != 'All Files' ORDER BY name COLLATE NOCASE");
        $categories = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'name');
        
        $this->pdo->beginTransaction();
        try {
            // Set 'All Files' to order 0
            $stmt = $this->pdo->prepare("UPDATE categories SET sort_order = 0 WHERE name = 'All Files'");
            $stmt->execute();
            
            // Set other categories in alphabetical order
            $stmt = $this->pdo->prepare("UPDATE categories SET sort_order = ? WHERE name = ?");
            foreach ($categories as $index => $name) {
                $stmt->execute([$index + 1, $name]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // Transaction support
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
}

// Helper functions for backward compatibility

function getDatabase() {
    return Database::getInstance();
}

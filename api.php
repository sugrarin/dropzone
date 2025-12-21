<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

require_once 'auth.php';

if (!isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once 'db.php';

define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', __DIR__ . '/s');
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Initialize database
$db = getDatabase();

function generateFileId() {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $id = '';
    for ($i = 0; $i < 5; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}

function getUniqueFileId($db) {
    $maxAttempts = 100;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $id = generateFileId();
        if (!$db->fileExists($id)) {
            return $id;
        }
    }
    return null;
}

$jsonInput = file_get_contents('php://input');
$input = json_decode($jsonInput, true);

$action = $_GET['action'] ?? $_POST['action'] ?? ($input['action'] ?? null);

switch ($action) {
    case 'list':
        $files = $db->getAllFiles();
        $categories = $db->getAllCategories();
        
        // Convert database format to API format
        $filesFormatted = array_map(function($file) {
            return [
                'id' => $file['id'],
                'name' => $file['name'],
                'originalName' => $file['original_name'],
                'extension' => $file['extension'],
                'size' => (int)$file['size'],
                'uploadDate' => $file['upload_date'],
                'modified' => (bool)$file['modified'],
                'category' => $file['category']
            ];
        }, $files);
        
        echo json_encode([
            'success' => true,
            'files' => $filesFormatted,
            'categories' => $categories
        ]);
        break;
    
    case 'upload':
        if (!isset($_FILES['file'])) {
            echo json_encode(['success' => false, 'error' => 'No file selected']);
            exit;
        }
        
        $file = $_FILES['file'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error uploading file']);
            exit;
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'error' => 'File too large']);
            exit;
        }
        
        $fileId = null;
        if (isset($_POST['id'])) {
            $requestedId = $_POST['id'];
            if (!$db->fileExists($requestedId)) {
                $fileId = $requestedId;
            } else {
                echo json_encode(['success' => false, 'error' => 'ID file already exists, try again']);
                exit;
            }
        } else {
            $fileId = getUniqueFileId($db);
        }
        
        if (!$fileId) {
            echo json_encode(['success' => false, 'error' => 'Failed to generate ID']);
            exit;
        }
        
        $originalName = $file['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = $fileId . '.' . $extension;
        $filePath = UPLOAD_DIR . '/' . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            exit;
        }
        
        $category = $_POST['category'] ?? 'All Files';
        
        $fileData = [
            'id' => $fileId,
            'name' => $originalName,
            'originalName' => $originalName,
            'extension' => $extension,
            'size' => $file['size'],
            'uploadDate' => date('c'),
            'modified' => false,
            'category' => $category
        ];
        
        try {
            $db->insertFile($fileData);
        } catch (Exception $e) {
            // If database insert fails, delete the uploaded file
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            echo json_encode(['success' => false, 'error' => 'Failed to save file metadata']);
            exit;
        }
        
        echo json_encode([
            'success' => true,
            'file' => $fileData
        ]);
        break;
    
    case 'replace':
        if (!isset($_FILES['file']) || !isset($_POST['id'])) {
            echo json_encode(['success' => false, 'error' => 'Insufficient data']);
            exit;
        }
        
        $file = $_FILES['file'];
        $fileId = $_POST['id'];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'error' => 'Error uploading file']);
            exit;
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            echo json_encode(['success' => false, 'error' => 'File too large']);
            exit;
        }
        
        $existingFile = $db->getFileById($fileId);
        
        if (!$existingFile) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $originalName = $file['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = $fileId . '.' . $extension;
        $filePath = UPLOAD_DIR . '/' . $fileName;
        
        $oldExtension = $existingFile['extension'];
        if ($oldExtension !== $extension) {
            $oldFilePath = UPLOAD_DIR . '/' . $fileId . '.' . $oldExtension;
            if (file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }
        }
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to save file']);
            exit;
        }
        
        $db->updateFile($fileId, [
            'name' => $originalName,
            'extension' => $extension,
            'size' => $file['size'],
            'modified' => true
        ]);
        
        $updatedFile = $db->getFileById($fileId);
        
        echo json_encode([
            'success' => true,
            'file' => [
                'id' => $updatedFile['id'],
                'name' => $updatedFile['name'],
                'originalName' => $updatedFile['original_name'],
                'extension' => $updatedFile['extension'],
                'size' => (int)$updatedFile['size'],
                'uploadDate' => $updatedFile['upload_date'],
                'modified' => (bool)$updatedFile['modified'],
                'category' => $updatedFile['category']
            ]
        ]);
        break;
    
    case 'rename':
        if (!isset($input['id']) || !isset($input['name'])) {
            echo json_encode(['success' => false, 'error' => 'Insufficient data']);
            exit;
        }
        
        if (!$db->fileExists($input['id'])) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $db->updateFile($input['id'], ['name' => $input['name']]);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'delete':
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID not specified']);
            exit;
        }
        
        $file = $db->getFileById($input['id']);
        
        if (!$file) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $filePath = UPLOAD_DIR . '/' . $file['id'] . '.' . $file['extension'];
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $db->deleteFile($input['id']);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'update_category':
        if (!isset($input['id']) || !isset($input['category'])) {
            echo json_encode(['success' => false, 'error' => 'Insufficient data']);
            exit;
        }
        
        if (!$db->fileExists($input['id'])) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $db->updateFile($input['id'], ['category' => $input['category']]);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'categories':
        $categories = $db->getAllCategories();
        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
        break;
    
    case 'category_create':
        if (!isset($input['name']) || trim($input['name']) === '') {
            echo json_encode(['success' => false, 'error' => 'Name not specified']);
            exit;
        }
        
        $name = trim($input['name']);
        
        if ($db->categoryExists($name)) {
            echo json_encode(['success' => false, 'error' => 'Category already exists']);
            exit;
        }
        
        $db->insertCategory($name);
        $db->resortCategories();
        
        $categories = $db->getAllCategories();
        
        echo json_encode([
            'success' => true,
            'categories' => $categories
        ]);
        break;
    
    case 'category_rename':
        if (!isset($input['oldName']) || !isset($input['newName'])) {
            echo json_encode(['success' => false, 'error' => 'Insufficient data']);
            exit;
        }
        
        $oldName = trim($input['oldName']);
        $newName = trim($input['newName']);
        
        if ($oldName === 'All Files') {
            echo json_encode(['success' => false, 'error' => 'Cannot rename this category']);
            exit;
        }
        
        if (!$db->categoryExists($oldName)) {
            echo json_encode(['success' => false, 'error' => 'Category not found']);
            exit;
        }
        
        if ($db->categoryExists($newName)) {
            echo json_encode(['success' => false, 'error' => 'Category with this name already exists']);
            exit;
        }
        
        $db->renameCategory($oldName, $newName);
        $db->resortCategories();
        
        $categories = $db->getAllCategories();
        $files = $db->getAllFiles();
        
        // Convert database format to API format
        $filesFormatted = array_map(function($file) {
            return [
                'id' => $file['id'],
                'name' => $file['name'],
                'originalName' => $file['original_name'],
                'extension' => $file['extension'],
                'size' => (int)$file['size'],
                'uploadDate' => $file['upload_date'],
                'modified' => (bool)$file['modified'],
                'category' => $file['category']
            ];
        }, $files);
        
        echo json_encode([
            'success' => true,
            'categories' => $categories,
            'files' => $filesFormatted
        ]);
        break;
    
    case 'category_delete':
        if (!isset($input['name'])) {
            echo json_encode(['success' => false, 'error' => 'Name not specified']);
            exit;
        }
        
        $name = trim($input['name']);
        
        if ($name === 'All Files') {
            echo json_encode(['success' => false, 'error' => 'Cannot delete this category']);
            exit;
        }
        
        if (!$db->categoryExists($name)) {
            echo json_encode(['success' => false, 'error' => 'Category not found']);
            exit;
        }
        
        $db->deleteCategory($name);
        
        $categories = $db->getAllCategories();
        $files = $db->getAllFiles();
        
        // Convert database format to API format
        $filesFormatted = array_map(function($file) {
            return [
                'id' => $file['id'],
                'name' => $file['name'],
                'originalName' => $file['original_name'],
                'extension' => $file['extension'],
                'size' => (int)$file['size'],
                'uploadDate' => $file['upload_date'],
                'modified' => (bool)$file['modified'],
                'category' => $file['category']
            ];
        }, $files);
        
        echo json_encode([
            'success' => true,
            'categories' => $categories,
            'files' => $filesFormatted
        ]);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

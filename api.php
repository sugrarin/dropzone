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

define('DATA_DIR', __DIR__ . '/data');
define('UPLOAD_DIR', __DIR__ . '/s');
define('DATA_FILE', DATA_DIR . '/files.json');
define('MAX_FILE_SIZE', 100 * 1024 * 1024);

if (!file_exists(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if (!file_exists(DATA_FILE)) {
    $initialData = [
        'files' => [],
        'categories' => ['All Files']
    ];
    file_put_contents(DATA_FILE, json_encode($initialData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function loadData() {
    $json = file_get_contents(DATA_FILE);
    return json_decode($json, true);
}

function saveData($data) {
    file_put_contents(DATA_FILE, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function generateFileId() {
    $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $id = '';
    for ($i = 0; $i < 5; $i++) {
        $id .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $id;
}

function getUniqueFileId($data) {
    $maxAttempts = 100;
    for ($i = 0; $i < $maxAttempts; $i++) {
        $id = generateFileId();
        $exists = false;
        foreach ($data['files'] as $file) {
            if ($file['id'] === $id) {
                $exists = true;
                break;
            }
        }
        if (!$exists) {
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
        $data = loadData();
        echo json_encode([
            'success' => true,
            'files' => $data['files'],
            'categories' => $data['categories']
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
        
        $data = loadData();
        
        $fileId = null;
        if (isset($_POST['id'])) {
            $requestedId = $_POST['id'];
            $isUnique = true;
            foreach ($data['files'] as $f) {
                if ($f['id'] === $requestedId) {
                    $isUnique = false;
                    break;
                }
            }
            
            if ($isUnique) {
                $fileId = $requestedId;
            } else {
                echo json_encode(['success' => false, 'error' => 'ID file already exists, try again']);
                exit;
            }
        } else {
            $fileId = getUniqueFileId($data);
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
        
        $data['files'][] = $fileData;
        saveData($data);
        
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
        
        $data = loadData();
        $fileIndex = -1;
        
        foreach ($data['files'] as $index => $f) {
            if ($f['id'] === $fileId) {
                $fileIndex = $index;
                break;
            }
        }
        
        if ($fileIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $originalName = $file['name'];
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $fileName = $fileId . '.' . $extension;
        $filePath = UPLOAD_DIR . '/' . $fileName;
        
        $oldExtension = $data['files'][$fileIndex]['extension'];
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
        
        $data['files'][$fileIndex]['name'] = $originalName;
        $data['files'][$fileIndex]['extension'] = $extension;
        $data['files'][$fileIndex]['size'] = $file['size'];
        $data['files'][$fileIndex]['modified'] = true;
        
        saveData($data);
        
        echo json_encode([
            'success' => true,
            'file' => $data['files'][$fileIndex]
        ]);
        break;
    
    case 'rename':
        if (!isset($input['id']) || !isset($input['name'])) {
            echo json_encode(['success' => false, 'error' => 'Insufficient data']);
            exit;
        }
        
        $data = loadData();
        $fileIndex = -1;
        
        foreach ($data['files'] as $index => $f) {
            if ($f['id'] === $input['id']) {
                $fileIndex = $index;
                break;
            }
        }
        
        if ($fileIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $data['files'][$fileIndex]['name'] = $input['name'];
        saveData($data);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'delete':
        if (!isset($input['id'])) {
            echo json_encode(['success' => false, 'error' => 'ID not specified']);
            exit;
        }
        
        $data = loadData();
        $fileIndex = -1;
        
        foreach ($data['files'] as $index => $f) {
            if ($f['id'] === $input['id']) {
                $fileIndex = $index;
                break;
            }
        }
        
        if ($fileIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $file = $data['files'][$fileIndex];
        $filePath = UPLOAD_DIR . '/' . $file['id'] . '.' . $file['extension'];
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        array_splice($data['files'], $fileIndex, 1);
        saveData($data);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'update_category':
        if (!isset($input['id']) || !isset($input['category'])) {
            echo json_encode(['success' => false, 'error' => 'Insufficient data']);
            exit;
        }
        
        $data = loadData();
        $fileIndex = -1;
        
        foreach ($data['files'] as $index => $f) {
            if ($f['id'] === $input['id']) {
                $fileIndex = $index;
                break;
            }
        }
        
        if ($fileIndex === -1) {
            echo json_encode(['success' => false, 'error' => 'File not found']);
            exit;
        }
        
        $data['files'][$fileIndex]['category'] = $input['category'];
        saveData($data);
        
        echo json_encode(['success' => true]);
        break;
    
    case 'categories':
        $data = loadData();
        echo json_encode([
            'success' => true,
            'categories' => $data['categories']
        ]);
        break;
    
    case 'category_create':
        if (!isset($input['name']) || trim($input['name']) === '') {
            echo json_encode(['success' => false, 'error' => 'Name not specified']);
            exit;
        }
        
        $data = loadData();
        $name = trim($input['name']);
        
        if (in_array($name, $data['categories'])) {
            echo json_encode(['success' => false, 'error' => 'Category already exists']);
            exit;
        }
        
        $data['categories'][] = $name;
        
        $allFiles = array_shift($data['categories']);
        sort($data['categories'], SORT_NATURAL | SORT_FLAG_CASE);
        array_unshift($data['categories'], $allFiles);
        
        saveData($data);
        
        echo json_encode([
            'success' => true,
            'categories' => $data['categories']
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
        
        $data = loadData();
        
        $categoryIndex = array_search($oldName, $data['categories']);
        if ($categoryIndex === false) {
            echo json_encode(['success' => false, 'error' => 'Category not found']);
            exit;
        }
        
        if (in_array($newName, $data['categories'])) {
            echo json_encode(['success' => false, 'error' => 'Category with this name already exists']);
            exit;
        }
        
        $data['categories'][$categoryIndex] = $newName;
        
        foreach ($data['files'] as $index => $file) {
            if ($file['category'] === $oldName) {
                $data['files'][$index]['category'] = $newName;
            }
        }
        
        $allFiles = array_shift($data['categories']);
        sort($data['categories'], SORT_NATURAL | SORT_FLAG_CASE);
        array_unshift($data['categories'], $allFiles);
        
        saveData($data);
        
        echo json_encode([
            'success' => true,
            'categories' => $data['categories'],
            'files' => $data['files']
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
        
        $data = loadData();
        
        $categoryIndex = array_search($name, $data['categories']);
        if ($categoryIndex === false) {
            echo json_encode(['success' => false, 'error' => 'Category not found']);
            exit;
        }
        
        array_splice($data['categories'], $categoryIndex, 1);
        
        foreach ($data['files'] as $index => $file) {
            if ($file['category'] === $name) {
                $data['files'][$index]['category'] = 'All Files';
            }
        }
        
        saveData($data);
        
        echo json_encode([
            'success' => true,
            'categories' => $data['categories'],
            'files' => $data['files']
        ]);
        break;
    
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
        break;
}

<?php
// ======================= HEADERS =======================
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ======================= DB =======================
require_once './config/Database.php';
$database = new Database();
$db = $database->getConnection();

// ======================= REQUEST =======================
$method = $_SERVER['REQUEST_METHOD'];
$rawData = file_get_contents("php://input");
$data = json_decode($rawData, true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;


// ======================= RESOURCE FUNCTIONS =======================

function getAllResources($db) {
    $query = "SELECT id, title, description, link, created_at FROM resources";
    $params = [];

    if (!empty($_GET['search'])) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . $_GET['search'] . '%';
    }

    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort, ['title', 'created_at'])) {
        $sort = 'created_at';
    }

    $order = strtolower($_GET['order'] ?? 'desc');
    if (!in_array($order, ['asc', 'desc'])) {
        $order = 'desc';
    }

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }

    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $resources]);
}


function getResourceById($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($resource) {
        sendResponse(['success' => true, 'data' => $resource]);
    } else {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }
}


function createResource($db, $data) {
    $validation = validateRequiredFields($data, ['title', 'link']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    $title = sanitizeInput($data['title']);
    $description = sanitizeInput($data['description'] ?? '');
    $link = sanitizeInput($data['link']);

    if (!validateUrl($link)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $description, $link]);

    sendResponse([
        'success' => true,
        'message' => 'Resource created successfully',
        'id' => $db->lastInsertId()
    ], 201);
}


function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid ID required'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data['id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $values[] = sanitizeInput($data['title']);
    }

    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $values[] = sanitizeInput($data['description']);
    }

    if (isset($data['link'])) {
        $link = sanitizeInput($data['link']);
        if (!validateUrl($link)) {
            sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
        }
        $fields[] = "link = ?";
        $values[] = $link;
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $values[] = $data['id'];

    $query = "UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute($values);

    sendResponse(['success' => true, 'message' => 'Resource updated successfully']);
}


function deleteResource($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse(['success' => true, 'message' => 'Resource deleted successfully']);
}


// ======================= COMMENTS =======================

function getCommentsByResourceId($db, $resource_id) {
    if (!$resource_id || !is_numeric($resource_id)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }

    $stmt = $db->prepare("SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC");
    $stmt->execute([$resource_id]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}


function createComment($db, $data) {
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $stmt->execute([$data['resource_id']]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $author = sanitizeInput($data['author']);
    $text = sanitizeInput($data['text']);

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$data['resource_id'], $author, $text]);

    sendResponse([
        'success' => true,
        'message' => 'Comment added successfully',
        'id' => $db->lastInsertId()
    ], 201);
}


function deleteComment($db, $comment_id) {
    if (!$comment_id || !is_numeric($comment_id)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment ID'], 400);
    }

    $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $stmt->execute([$comment_id]);
    if (!$stmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$comment_id]);

    sendResponse(['success' => true, 'message' => 'Comment deleted successfully']);
}


// ======================= ROUTER =======================

try {
    switch ($method) {

        case 'GET':
            if ($action === 'comments') {
                getCommentsByResourceId($db, $resource_id);
            } elseif ($id) {
                getResourceById($db, $id);
            } else {
                getAllResources($db);
            }
            break;

        case 'POST':
            if ($action === 'comment') {
                createComment($db, $data);
            } else {
                createResource($db, $data);
            }
            break;

        case 'PUT':
            updateResource($db, $data);
            break;

        case 'DELETE':
            if ($action === 'delete_comment') {
                deleteComment($db, $comment_id);
            } else {
                deleteResource($db, $id);
            }
            break;

        default:
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}


// ======================= HELPERS =======================

function sendResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit();
}

function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function validateRequiredFields($data, $fields) {
    $missing = [];

    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }

    return [
        'valid' => empty($missing),
        'missing' => $missing
    ];
}

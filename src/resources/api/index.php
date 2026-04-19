<?php

// ================= HEADERS =================
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// ================= INIT =================
require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];
$data = json_decode(file_get_contents("php://input"), true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

// ================= RESOURCE FUNCTIONS =================

function getAllResources($db) {
    $search = $_GET['search'] ?? null;
    $sort = in_array($_GET['sort'] ?? '', ['title', 'created_at']) ? $_GET['sort'] : 'created_at';
    $order = strtolower($_GET['order'] ?? '') === 'asc' ? 'ASC' : 'DESC';

    $query = "SELECT id, title, description, link, created_at FROM resources";

    if ($search) {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $query .= " ORDER BY $sort $order";

    $stmt = $db->prepare($query);

    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }

    $stmt->execute();
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $resources]);
}

function getResourceById($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    $resource = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$resource) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    sendResponse(['success' => true, 'data' => $resource]);
}

function createResource($db, $data) {
    $validation = validateRequiredFields($data, ['title', 'link']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    if (!validateUrl($data['link'])) {
        sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
    }

    $title = sanitizeInput($data['title']);
    $desc = sanitizeInput($data['description'] ?? '');
    $link = $data['link'];

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");

    if ($stmt->execute([$title, $desc, $link])) {
        sendResponse([
            'success' => true,
            'message' => 'Resource created successfully',
            'id' => $db->lastInsertId()
        ], 201);
    }

    sendResponse(['success' => false, 'message' => 'Database error'], 500);
}

function updateResource($db, $data) {
    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Valid ID required'], 400);
    }

    // check existence
    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$data['id']]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $fields = [];
    $params = [];

    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $params[] = sanitizeInput($data['title']);
    }

    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $params[] = sanitizeInput($data['description']);
    }

    if (isset($data['link'])) {
        if (!validateUrl($data['link'])) {
            sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
        }
        $fields[] = "link = ?";
        $params[] = $data['link'];
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $params[] = $data['id'];

    $stmt = $db->prepare("UPDATE resources SET " . implode(', ', $fields) . " WHERE id = ?");

    if ($stmt->execute($params)) {
        sendResponse(['success' => true, 'message' => 'Resource updated successfully']);
    }

    sendResponse(['success' => false, 'message' => 'Update failed'], 500);
}

function deleteResource($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Valid ID required'], 400);
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse(['success' => true, 'message' => 'Resource deleted successfully']);
}

// ================= COMMENTS =================

function getCommentsByResourceId($db, $resource_id) {
    if (!$resource_id || !is_numeric($resource_id)) {
        sendResponse(['success' => false, 'message' => 'Valid resource ID required'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC");
    $stmt->execute([$resource_id]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db, $data) {
    $validation = validateRequiredFields($data, ['resource_id', 'author', 'text']);

    if (!$validation['valid']) {
        sendResponse([
            'success' => false,
            'message' => 'Missing fields: ' . implode(', ', $validation['missing'])
        ], 400);
    }

    if (!is_numeric($data['resource_id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid resource_id'], 400);
    }

    // check resource exists
    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$data['resource_id']]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");

    if ($stmt->execute([
        $data['resource_id'],
        sanitizeInput($data['author']),
        sanitizeInput($data['text'])
    ])) {

        $id = $db->lastInsertId();

        $fetch = $db->prepare("SELECT * FROM comments_resource WHERE id = ?");
        $fetch->execute([$id]);

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully',
            'id' => $id,
            'data' => $fetch->fetch(PDO::FETCH_ASSOC)
        ], 201);
    }

    sendResponse(['success' => false, 'message' => 'Database error'], 500);
}

function deleteComment($db, $comment_id) {
    if (!$comment_id || !is_numeric($comment_id)) {
        sendResponse(['success' => false, 'message' => 'Valid comment ID required'], 400);
    }

    $check = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $check->execute([$comment_id]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$comment_id]);

    sendResponse(['success' => true, 'message' => 'Comment deleted successfully']);
}

// ================= ROUTER =================

try {

    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByResourceId($db, $resource_id);
        } elseif ($id) {
            getResourceById($db, $id);
        } else {
            getAllResources($db);
        }

    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createResource($db, $data);
        }

    } elseif ($method === 'PUT') {
        updateResource($db, $data);

    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $comment_id);
        } else {
            deleteResource($db, $id);
        }

    } else {
        sendResponse(['success' => false, 'message' => 'Method Not Allowed'], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}

// ================= HELPERS =================

function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
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
        if (!isset($data[$field]) || empty(trim((string)$data[$field]))) {
            $missing[] = $field;
        }
    }
    return ['valid' => empty($missing), 'missing' => $missing];
}

?>

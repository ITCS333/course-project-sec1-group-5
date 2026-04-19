<?php
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

/* =========================
   SEND RESPONSE
========================= */
function sendResponse($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/* =========================
   VALIDATION HELPERS
========================= */
function validateUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

function sanitize($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}

function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $f) {
        if (!isset($data[$f]) || trim($data[$f]) === '') {
            $missing[] = $f;
        }
    }
    return $missing;
}

/* =========================
   GET ALL RESOURCES
========================= */
function getAllResources($db) {

    $search = $_GET['search'] ?? null;

    $sql = "SELECT id, title, description, link, created_at FROM resources";

    if ($search) {
        $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }

    $sort = $_GET['sort'] ?? 'created_at';
    if (!in_array($sort, ['title', 'created_at'])) $sort = 'created_at';

    $order = strtoupper($_GET['order'] ?? 'DESC');
    if (!in_array($order, ['ASC', 'DESC'])) $order = 'DESC';

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);

    if ($search) {
        $stmt->bindValue(':search', "%$search%");
    }

    $stmt->execute();

    sendResponse([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

/* =========================
   GET ONE RESOURCE
========================= */
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

/* =========================
   CREATE RESOURCE
========================= */
function createResource($db, $data) {

    $missing = validateRequired($data, ['title', 'link']);
    if (!empty($missing)) {
        sendResponse(['success' => false, 'message' => 'Missing: ' . implode(',', $missing)], 400);
    }

    if (!validateUrl($data['link'])) {
        sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
    }

    $title = sanitize($data['title']);
    $desc = sanitize($data['description'] ?? '');
    $link = $data['link'];

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
    $stmt->execute([$title, $desc, $link]);

    sendResponse([
        'success' => true,
        'id' => $db->lastInsertId()
    ], 201);
}

/* =========================
   UPDATE RESOURCE
========================= */
function updateResource($db, $data) {

    if (!isset($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $fields = [];
    $values = [];

    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $values[] = sanitize($data['title']);
    }

    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $values[] = sanitize($data['description']);
    }

    if (isset($data['link'])) {
        if (!validateUrl($data['link'])) {
            sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
        }
        $fields[] = "link = ?";
        $values[] = $data['link'];
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update'], 400);
    }

    $values[] = $data['id'];

    $sql = "UPDATE resources SET " . implode(',', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute($values);

    sendResponse(['success' => true]);
}

/* =========================
   DELETE RESOURCE
========================= */
function deleteResource($db, $id) {

    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['success' => false, 'message' => 'Not found'], 404);
    }

    sendResponse(['success' => true]);
}

/* =========================
   COMMENTS
========================= */
function getComments($db, $resource_id) {

    if (!$resource_id || !is_numeric($resource_id)) {
        sendResponse(['success' => false, 'message' => 'Invalid resource ID'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id = ?");
    $stmt->execute([$resource_id]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db, $data) {

    $missing = validateRequired($data, ['resource_id', 'author', 'text']);
    if (!empty($missing)) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([
        $data['resource_id'],
        sanitize($data['author']),
        sanitize($data['text'])
    ]);

    sendResponse([
        'success' => true,
        'id' => $db->lastInsertId()
    ], 201);
}

function deleteComment($db, $id) {

    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        sendResponse(['success' => false, 'message' => 'Not found'], 404);
    }

    sendResponse(['success' => true]);
}

/* =========================
   ROUTER
========================= */
try {

    if ($method === 'GET') {

        if ($action === 'comments') {
            getComments($db, $resource_id);
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
        sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error'], 500);
}

<?php

// ================= SAFE START =================
error_reporting(0); // prevent PHP warnings breaking JSON
ini_set('display_errors', 0);

// ================= HEADERS =================
header("Content-Type: application/json; charset=UTF-8");

// ================= LOAD DB SAFELY =================
$db = null;

try {
    require_once __DIR__ . '/config/Database.php';

    if (class_exists('Database')) {
        $database = new Database();
        $db = $database->getConnection();
    }

} catch (Throwable $e) {
    sendResponse(['success' => false, 'message' => 'Database connection failed'], 500);
}

// ================= INPUT =================
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$data = json_decode(file_get_contents("php://input"), true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

// ================= ROUTER =================
try {

    if ($method === 'GET') {

        if ($action === 'comments') {

            if (!$resource_id || !is_numeric($resource_id)) {
                sendResponse(['success' => false, 'message' => 'Invalid resource_id'], 400);
            }

            getComments($db, $resource_id);

        } elseif ($id) {

            getResource($db, $id);

        } else {

            getAll($db);
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

} catch (Throwable $e) {
    sendResponse(['success' => false, 'message' => 'Internal Server Error'], 500);
}


// ================= FUNCTIONS =================

function getAll($db) {
    $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources ORDER BY created_at DESC");
    $stmt->execute();
    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function getResource($db, $id) {
    if (!is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$data) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    sendResponse(['success' => true, 'data' => $data]);
}

function createResource($db, $data) {
    if (empty($data['title']) || empty($data['link'])) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    if (!filter_var($data['link'], FILTER_VALIDATE_URL)) {
        sendResponse(['success' => false, 'message' => 'Invalid URL'], 400);
    }

    $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");

    $stmt->execute([
        trim($data['title']),
        trim($data['description'] ?? ''),
        $data['link']
    ]);

    sendResponse(['success' => true, 'id' => $db->lastInsertId()], 201);
}

function updateResource($db, $data) {
    if (empty($data['id']) || !is_numeric($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$data['id']]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare("UPDATE resources SET title = ?, description = ?, link = ? WHERE id = ?");
    $stmt->execute([
        $data['title'] ?? '',
        $data['description'] ?? '',
        $data['link'] ?? '',
        $data['id']
    ]);

    sendResponse(['success' => true, 'message' => 'Updated']);
}

function deleteResource($db, $id) {
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$id]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
    $stmt->execute([$id]);

    sendResponse(['success' => true]);
}

// ===== COMMENTS =====

function getComments($db, $resource_id) {
    $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC");
    $stmt->execute([$resource_id]);

    sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

function createComment($db, $data) {
    if (empty($data['resource_id']) || empty($data['author']) || empty($data['text'])) {
        sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
    }

    $check = $db->prepare("SELECT id FROM resources WHERE id = ?");
    $check->execute([$data['resource_id']]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Resource not found'], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$data['resource_id'], $data['author'], $data['text']]);

    sendResponse(['success' => true], 201);
}

function deleteComment($db, $comment_id) {
    if (!$comment_id || !is_numeric($comment_id)) {
        sendResponse(['success' => false, 'message' => 'Invalid ID'], 400);
    }

    $check = $db->prepare("SELECT id FROM comments_resource WHERE id = ?");
    $check->execute([$comment_id]);

    if (!$check->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
    $stmt->execute([$comment_id]);

    sendResponse(['success' => true]);
}

// ================= RESPONSE =================
function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

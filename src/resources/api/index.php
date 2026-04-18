<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// Parse inputs
$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;

try {
    switch ($method) {
        case 'GET':
            if ($action === 'comments') {
                if (!$resource_id) sendResponse(['success' => false, 'message' => 'Resource ID required'], 400);
                $stmt = $db->prepare("SELECT * FROM comments_resource WHERE resource_id = ? ORDER BY created_at ASC");
                $stmt->execute([$resource_id]);
                sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            } elseif ($id) {
                $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
                $stmt->execute([$id]);
                $res = $stmt->fetch(PDO::FETCH_ASSOC);
                $res ? sendResponse(['success' => true, 'data' => $res]) : sendResponse(['success' => false, 'message' => 'Not found'], 404);
            } else {
                // Search and Sort logic
                $search = $_GET['search'] ?? null;
                $sort = in_array($_GET['sort'] ?? '', ['title', 'created_at']) ? $_GET['sort'] : 'created_at';
                $order = strtoupper($_GET['order'] ?? '') === 'ASC' ? 'ASC' : 'DESC';
                
                $sql = "SELECT * FROM resources";
                if ($search) $sql .= " WHERE title LIKE :s OR description LIKE :s";
                $sql .= " ORDER BY $sort $order";
                
                $stmt = $db->prepare($sql);
                if ($search) $stmt->bindValue(':s', "%$search%");
                $stmt->execute();
                sendResponse(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
            }
            break;

        case 'POST':
            if ($action === 'comment') {
                if (empty($data['resource_id']) || empty($data['author']) || empty($data['text'])) {
                    sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
                }
                $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?, ?, ?)");
                $stmt->execute([$data['resource_id'], sanitizeInput($data['author']), sanitizeInput($data['text'])]);
                sendResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Comment added'], 201);
            } else {
                if (empty($data['title']) || empty($data['link'])) {
                    sendResponse(['success' => false, 'message' => 'Missing fields'], 400);
                }
                $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?, ?, ?)");
                $stmt->execute([sanitizeInput($data['title']), sanitizeInput($data['description'] ?? ''), $data['link']]);
                sendResponse(['success' => true, 'id' => $db->lastInsertId(), 'message' => 'Created'], 201);
            }
            break;

        case 'PUT':
            if (empty($data['id'])) sendResponse(['success' => false, 'message' => 'ID required'], 400);
            $stmt = $db->prepare("UPDATE resources SET title=?, description=?, link=? WHERE id=?");
            $stmt->execute([$data['title'], $data['description'], $data['link'], $data['id']]);
            sendResponse(['success' => true, 'message' => 'Updated']);
            break;

        case 'DELETE':
            if ($action === 'delete_comment') {
                $stmt = $db->prepare("DELETE FROM comments_resource WHERE id = ?");
                $stmt->execute([$comment_id]);
            } else {
                $stmt = $db->prepare("DELETE FROM resources WHERE id = ?");
                $stmt->execute([$id]);
            }
            $stmt->rowCount() > 0 ? sendResponse(['success' => true, 'message' => 'Deleted']) : sendResponse(['success' => false, 'message' => 'Not found'], 404);
            break;

        default:
            sendResponse(['success' => false, 'message' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    sendResponse(['success' => false, 'message' => 'Server Error'], 500);
}

function sendResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function sanitizeInput($s) {
    return htmlspecialchars(strip_tags(trim($s)), ENT_QUOTES, 'UTF-8');
}

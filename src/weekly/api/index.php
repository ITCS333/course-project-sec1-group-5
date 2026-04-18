<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();
$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');
$data = json_decode($rawData, true) ?? [];

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$weekId = $_GET['week_id'] ?? null;
$commentId = $_GET['comment_id'] ?? null;

function getAllWeeks(PDO $db): void
{
    $query = "SELECT id, title, start_date, description, links, created_at FROM weeks";
    $params = [];

    if (isset($_GET['search']) && trim($_GET['search']) !== '') {
        $query .= " WHERE title LIKE :search OR description LIKE :search";
        $params[':search'] = '%' . trim($_GET['search']) . '%';
    }

    $allowedSort = ['title', 'start_date'];
    $sort = $_GET['sort'] ?? 'start_date';
    if (!in_array($sort, $allowedSort, true)) {
        $sort = 'start_date';
    }

    $allowedOrder = ['asc', 'desc'];
    $order = strtolower($_GET['order'] ?? 'asc');
    if (!in_array($order, $allowedOrder, true)) {
        $order = 'asc';
    }

    $query .= " ORDER BY {$sort} {$order}";

    $stmt = $db->prepare($query);

    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }

    $stmt->execute();
    $weeks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($weeks as &$week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
    }

    sendResponse(['success' => true, 'data' => $weeks]);
}

function getWeekById(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid week id.'], 400);
    }

    $stmt = $db->prepare("SELECT id, title, start_date, description, links, created_at FROM weeks WHERE id = ?");
    $stmt->execute([$id]);
    $week = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($week) {
        $week['links'] = json_decode($week['links'], true) ?? [];
        sendResponse(['success' => true, 'data' => $week]);
    }

    sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
}

function createWeek(PDO $db, array $data): void
{
    if (empty(trim($data['title'] ?? '')) || empty(trim($data['start_date'] ?? ''))) {
        sendResponse(['success' => false, 'message' => 'Title and start_date are required.'], 400);
    }

    $title = trim($data['title']);
    $start_date = trim($data['start_date']);

    if (!validateDate($start_date)) {
        sendResponse(['success' => false, 'message' => 'Invalid start_date format.'], 400);
    }

    $description = trim($data['description'] ?? '');
    $links = (isset($data['links']) && is_array($data['links'])) ? json_encode($data['links']) : json_encode([]);

    $stmt = $db->prepare("INSERT INTO weeks (title, start_date, description, links) VALUES (?, ?, ?, ?)");
    $stmt->execute([$title, $start_date, $description, $links]);

    if ($stmt->rowCount() > 0) {
        sendResponse([
            'success' => true,
            'message' => 'Week created successfully.',
            'id' => (int)$db->lastInsertId()
        ], 201);
    }

    sendResponse(['success' => false, 'message' => 'Failed to create week.'], 500);
}

function updateWeek(PDO $db, array $data): void
{
    if (!isset($data['id'])) {
        sendResponse(['success' => false, 'message' => 'Week id is required.'], 400);
    }

    $id = $data['id'];

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $fields = [];
    $values = [];

    if (array_key_exists('title', $data)) {
        $fields[] = "title = ?";
        $values[] = trim($data['title']);
    }

    if (array_key_exists('start_date', $data)) {
        if (!validateDate(trim($data['start_date']))) {
            sendResponse(['success' => false, 'message' => 'Invalid start_date format.'], 400);
        }
        $fields[] = "start_date = ?";
        $values[] = trim($data['start_date']);
    }

    if (array_key_exists('description', $data)) {
        $fields[] = "description = ?";
        $values[] = trim($data['description']);
    }

    if (array_key_exists('links', $data)) {
        $fields[] = "links = ?";
        $values[] = is_array($data['links']) ? json_encode($data['links']) : json_encode([]);
    }

    if (empty($fields)) {
        sendResponse(['success' => false, 'message' => 'No fields to update.'], 400);
    }

    $values[] = $id;

    $query = "UPDATE weeks SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $db->prepare($query);
    $success = $stmt->execute($values);

    if ($success) {
        sendResponse(['success' => true, 'message' => 'Week updated successfully.'], 200);
    }

    sendResponse(['success' => false, 'message' => 'Failed to update week.'], 500);
}

function deleteWeek(PDO $db, $id): void
{
    if (!$id || !is_numeric($id)) {
        sendResponse(['success' => false, 'message' => 'Invalid week id.'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$id]);

    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare("DELETE FROM weeks WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Week deleted successfully.'], 200);
    }

    sendResponse(['success' => false, 'message' => 'Failed to delete week.'], 500);
}

function getCommentsByWeek(PDO $db, $weekId): void
{
    if (!$weekId || !is_numeric($weekId)) {
        sendResponse(['success' => false, 'message' => 'Invalid week id.'], 400);
    }

    $stmt = $db->prepare("
        SELECT id, week_id, author, text, created_at
        FROM comments_week
        WHERE week_id = ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$weekId]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    sendResponse(['success' => true, 'data' => $comments]);
}

function createComment(PDO $db, array $data): void
{
    $week_id = $data['week_id'] ?? null;
    $author = trim($data['author'] ?? '');
    $text = trim($data['text'] ?? '');

    if ($week_id === null || $author === '' || $text === '') {
        sendResponse(['success' => false, 'message' => 'week_id, author, and text are required.'], 400);
    }

    if (!is_numeric($week_id)) {
        sendResponse(['success' => false, 'message' => 'Invalid week_id.'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM weeks WHERE id = ?");
    $checkStmt->execute([$week_id]);

    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Week not found.'], 404);
    }

    $stmt = $db->prepare("INSERT INTO comments_week (week_id, author, text) VALUES (?, ?, ?)");
    $stmt->execute([$week_id, $author, $text]);

    if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();

        $commentStmt = $db->prepare("
            SELECT id, week_id, author, text, created_at
            FROM comments_week
            WHERE id = ?
        ");
        $commentStmt->execute([$newId]);
        $comment = $commentStmt->fetch(PDO::FETCH_ASSOC);

        sendResponse([
            'success' => true,
            'message' => 'Comment created successfully.',
            'id' => $newId,
            'data' => $comment
        ], 201);
    }

    sendResponse(['success' => false, 'message' => 'Failed to create comment.'], 500);
}

function deleteComment(PDO $db, $commentId): void
{
    if (!$commentId || !is_numeric($commentId)) {
        sendResponse(['success' => false, 'message' => 'Invalid comment id.'], 400);
    }

    $checkStmt = $db->prepare("SELECT id FROM comments_week WHERE id = ?");
    $checkStmt->execute([$commentId]);

    if (!$checkStmt->fetch()) {
        sendResponse(['success' => false, 'message' => 'Comment not found.'], 404);
    }

    $stmt = $db->prepare("DELETE FROM comments_week WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        sendResponse(['success' => true, 'message' => 'Comment deleted successfully.'], 200);
    }

    sendResponse(['success' => false, 'message' => 'Failed to delete comment.'], 500);
}

try {
    if ($method === 'GET') {
        if ($action === 'comments') {
            getCommentsByWeek($db, $weekId);
        } elseif ($id !== null) {
            getWeekById($db, $id);
        } else {
            getAllWeeks($db);
        }
    } elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        } else {
            createWeek($db, $data);
        }
    } elseif ($method === 'PUT') {
        updateWeek($db, $data);
    } elseif ($method === 'DELETE') {
        if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } else {
            deleteWeek($db, $id);
        }
    } else {
        sendResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
} catch (PDOException $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Database error occurred.'], 500);
} catch (Exception $e) {
    error_log($e->getMessage());
    sendResponse(['success' => false, 'message' => 'Server error occurred.'], 500);
}

function sendResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
}

function validateDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function sanitizeInput(string $data): string
{
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}
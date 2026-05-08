<?php

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once __DIR__ . '/../../common/db.php';

$db = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];

$rawData = file_get_contents('php://input');

$data = json_decode($rawData, true) ?? [];

$action       = $_GET['action']        ?? null;
$id           = $_GET['id']            ?? null; 
$assignmentId = $_GET['assignment_id'] ?? null;
$commentId    = $_GET['comment_id']    ?? null; 


function getAllAssignments(PDO $db): void
{
    $sql = "SELECT id, title, description, due_date, files, created_at, updated_at
        FROM assignments";

    $search = $_GET['search'] ?? null;
    
    if (!empty($search)) {
    $sql .= " WHERE title LIKE :search OR description LIKE :search";
    }
    
    $stmt = $db->prepare($sql);
    
    if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }

    $allowedSort = ['title', 'due_date', 'created_at'];
    
    $sort = $_GET['sort'] ?? 'due_date';
    
    if (!in_array($sort, $allowedSort, true)) {
    $sort = 'due_date';
    }

    $allowedOrder = ['asc', 'desc'];
    
    $order = strtolower($_GET['order'] ?? 'asc');
    
    if (!in_array($order, $allowedOrder, true)) {
    $order = 'asc';
    }

    $sql .= " ORDER BY $sort $order";

    $stmt = $db->prepare($sql);
    
    if (!empty($search)) {
    $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
    }
    
    $stmt->execute();

    $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($assignments as &$row) {
        $row['files'] = json_decode($row['files'], true) ?? [];
    }
    unset($row);

    sendResponse([
                 'success' => true,
                 'data' => $assignments
                 ]);
}


function getAssignmentById(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Invalid or missing id'
                     ]);
    }

    $sql = "SELECT id, title, description, due_date, files,
    created_at, updated_at
    FROM assignments
    WHERE id = ?";

    $stmt = $db->prepare($sql);
    $stmt->execute([$id]);

    $assignment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($assignment) {
        $assignment['files'] = json_decode($assignment['files'], true) ?? [];
    }

    if ($assignment) {
        sendResponse([
                     'success' => true,
                     'data' => $assignment
                     ]);
    } 
    else {
        http_response_code(404);
        sendResponse([
                     'success' => false,
                     'error' => 'Assignment not found'
                     ]);
    }
}


function createAssignment(PDO $db, array $data): void
{
    if (
        empty($data['title']) ||
        empty($data['description']) ||
        empty($data['due_date'])
    ) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Missing required fields: title, description, due_date'
                     ]);
        return;
    }

    $title       = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $due_date    = trim($data['due_date'] ?? '');

    $date = DateTime::createFromFormat('Y-m-d', $due_date);
    
    if (!$date || $date->format('Y-m-d') !== $due_date) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Invalid due_date format. Expected YYYY-MM-DD'
                     ]);
        return;
    }

    if (isset($data['files']) && is_array($data['files'])) {
        $files = json_encode($data['files']);
    } 
    else {
        $files = json_encode([]);
    }

    $sql = "INSERT INTO assignments (title, description, due_date, files)
        VALUES (?, ?, ?, ?)";

    $stmt = $db->prepare($sql);
    $stmt->execute([$title, $description, $due_date, $files]);

    if ($stmt->rowCount() > 0) {
        http_response_code(201);
        sendResponse([
                     'success' => true,
                     'message' => 'Assignment created successfully',
                     'id' => (int)$db->lastInsertId()
                     ]);
    } 
    else {
        http_response_code(500);
        sendResponse([
                     'success' => false,
                     'error' => 'Failed to create assignment'
                     ]);
    }
}


function updateAssignment(PDO $db, array $data): void
{
    // TODO: Validate that $data['id'] is present.
    // If not, sendResponse HTTP 400.
    if (!isset($data['id'])) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Missing required field: id'
                     ]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$data['id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        sendResponse([
                     'success' => false,
                     'error' => 'Assignment not found'
                     ]);
        return;
    }

    $fields = [];
    $params = [];
    
    if (isset($data['title'])) {
        $fields[] = "title = ?";
        $params[] = trim($data['title']);
    }
    
    if (isset($data['description'])) {
        $fields[] = "description = ?";
        $params[] = trim($data['description']);
    }
    
    if (isset($data['due_date'])) {
        $due_date = trim($data['due_date']);
        
        $date = DateTime::createFromFormat('Y-m-d', $due_date);
        if (!$date || $date->format('Y-m-d') !== $due_date) {
            http_response_code(400);
            sendResponse([
                         'success' => false,
                         'error' => 'Invalid due_date format. Expected YYYY-MM-DD'
                         ]);
            return;
        }
        
        $fields[] = "due_date = ?";
        $params[] = $due_date;
    }
    
    if (isset($data['files'])) {
        $files = is_array($data['files']) ? $data['files'] : [];
        $fields[] = "files = ?";
        $params[] = json_encode($files);
    }
    
    if (empty($fields)) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'No fields provided to update'
                     ]);
        return;
    }

    if (empty($fields)) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'No fields provided to update'
                     ]);
        return;
    }

    $sql = "UPDATE assignments SET " . implode(', ', $fields) . " WHERE id = ?";

    $sql = "UPDATE assignments SET " . implode(', ', $fields) . " WHERE id = ?";
    
    $params[] = $data['id'];
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        sendResponse([
                     'success' => true,
                     'message' => 'Assignment updated successfully'
                     ]);
    } 
    else {
        http_response_code(500);
        sendResponse([
                     'success' => false,
                     'error' => 'Failed to update assignment'
                     ]);
    }
}


function deleteAssignment(PDO $db, $id): void
{
    if ($id === null || !is_numeric($id)) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Invalid or missing id'
                     ]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$id]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        sendResponse([
                     'success' => false,
                     'error' => 'Assignment not found'
                     ]);
        return;
    }

    $stmt = $db->prepare("DELETE FROM assignments WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        sendResponse([
                     'success' => true,
                     'message' => 'Assignment deleted successfully'
                     ]);
    } 
    else {
        http_response_code(500);
        sendResponse([
                     'success' => false,
                     'error' => 'Failed to delete assignment'
                     ]);
    }
}


function getCommentsByAssignment(PDO $db, $assignmentId): void
{
    if ($assignmentId === null || !is_numeric($assignmentId)) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Invalid or missing assignment_id'
                     ]);
        return;
    }
    
    $sql = "SELECT id, assignment_id, author, text, created_at
        FROM comments_assignment
        WHERE assignment_id = ?
        ORDER BY created_at ASC";

    $stmt = $db->prepare($sql);
    $stmt->execute([$assignmentId]);

    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse([
                 'success' => true,
                 'data' => $comments
                 ]);
}


function createComment(PDO $db, array $data): void
{
    $assignment_id = trim($data['assignment_id'] ?? '');
    $author        = trim($data['author'] ?? '');
    $text          = trim($data['text'] ?? '');

    if ($assignment_id === '' || $author === '' || $text === '') {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Missing required fields: assignment_id, author, text'
                     ]);
        return;
    }

    if (!is_numeric($assignment_id)) {
        http_response_code(400);
        sendResponse([
                     'success' => false,
                     'error' => 'Invalid assignment_id'
                     ]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM assignments WHERE id = ?");
    $stmt->execute([$assignment_id]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        sendResponse([
                     'success' => false,
                     'error' => 'Assignment not found'
                     ]);
        return;
    }

    $sql = "INSERT INTO comments_assignment (assignment_id, author, text)
            VALUES (?, ?, ?)";
    $stmt = $db->prepare($sql);
    $stmt->execute([$assignment_id, $author, $text]);

     if ($stmt->rowCount() > 0) {
        $newId = (int)$db->lastInsertId();

        http_response_code(201);
        sendResponse([
                     'success' => true,
                     'message' => 'Comment created successfully',
                     'id' => $newId,
                     'data' => [
                     'id' => $newId,
                     'assignment_id' => (int)$assignment_id,
                     'author' => $author,
                     'text' => $text,
                     'created_at' => date('Y-m-d H:i:s')
                     ]
                     ]);
     } 
     else {
        http_response_code(500);
        sendResponse([
                     'success' => false,
                     'error' => 'Failed to create comment'
                     ]);
    }
}


function deleteComment(PDO $db, $commentId): void
{
     if ($commentId === null || !is_numeric($commentId)) {
        http_response_code(400);
        sendResponse([ 
                     'success' => false,
                     'error' => 'Invalid or missing comment_id'
                     ]);
        return;
    }

    $stmt = $db->prepare("SELECT id FROM comments_assignment WHERE id = ?");
    $stmt->execute([$commentId]);

    if (!$stmt->fetch()) {
        http_response_code(404);
        sendResponse([
                     'success' => false,
                     'error' => 'Comment not found'
                     ]);
        return;
    }

    $stmt = $db->prepare("DELETE FROM comments_assignment WHERE id = ?");
    $stmt->execute([$commentId]);

    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        sendResponse([
                     'success' => true,
                     'message' => 'Comment deleted successfully'
                     ]);
    } 
    else {
        http_response_code(500);
        sendResponse([
                     'success' => false,
                     'error' => 'Failed to delete comment'
                     ]);
    }
}

try {
    if ($method === 'GET') {
         if ($action === 'comments') {
            getCommentsByAssignment($db, $assignmentId);
        } 
         elseif ($id !== null) {
            getAssignmentById($db, $id);
        } 
         else {
            getAllAssignments($db);
        }
        
    } 
    elseif ($method === 'POST') {
        if ($action === 'comment') {
            createComment($db, $data);
        }
         else {
            createAssignment($db, $data);
        }
        
    } 
    elseif ($method === 'PUT') {
        updateAssignment($db, $data);
    } 
    elseif ($method === 'DELETE') {
         if ($action === 'delete_comment') {
            deleteComment($db, $commentId);
        } 
         else {
            deleteAssignment($db, $id);
        }
        
    } 
    else {
         http_response_code(405);
        sendResponse([
                     'success' => false,
                     'error' => 'Method Not Allowed'
                     ]);
    }

} 
catch (PDOException $e) {
    error_log($e->getMessage());

    http_response_code(500);
    sendResponse([
                 'success' => false,
                 'error' => 'Database error'
                 ]);

} 
catch (Exception $e) {
    error_log($e->getMessage());

    http_response_code(500);
    sendResponse([
                 'success' => false,
                 'error' => 'Server error'
                 ]);
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

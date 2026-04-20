<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once './config/Database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

$input = json_decode(file_get_contents("php://input"), true);

$action = $_GET['action'] ?? null;
$id = $_GET['id'] ?? null;
$resource_id = $_GET['resource_id'] ?? null;
$comment_id = $_GET['comment_id'] ?? null;


// ======================= HELPERS =======================

function send($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function validUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL);
}


// ======================= ROUTER =======================

try {

    if ($method === 'GET') {

        if ($action === 'comments') {

            if (!$resource_id || !is_numeric($resource_id)) {
                send(['success'=>false], 400);
            }

            $stmt = $db->prepare("SELECT id, resource_id, author, text, created_at FROM comments_resource WHERE resource_id=? ORDER BY created_at ASC");
            $stmt->execute([$resource_id]);

            send(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);

        } elseif ($id) {

            if (!is_numeric($id)) {
                send(['success'=>false], 400);
            }

            $stmt = $db->prepare("SELECT id, title, description, link, created_at FROM resources WHERE id=?");
            $stmt->execute([$id]);
            $res = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$res) send(['success'=>false], 404);

            send(['success'=>true, 'data'=>$res]);

        } else {

            $query = "SELECT id, title, description, link, created_at FROM resources";
            $params = [];

            if (!empty($_GET['search'])) {
                $query .= " WHERE title LIKE :s OR description LIKE :s";
                $params[':s'] = '%' . $_GET['search'] . '%';
            }

            $sort = $_GET['sort'] ?? 'created_at';
            if (!in_array($sort, ['title','created_at'])) $sort = 'created_at';

            $order = strtolower($_GET['order'] ?? 'desc');
            if (!in_array($order, ['asc','desc'])) $order = 'desc';

            $query .= " ORDER BY $sort $order";

            $stmt = $db->prepare($query);
            foreach ($params as $k=>$v) $stmt->bindValue($k,$v);
            $stmt->execute();

            send(['success'=>true, 'data'=>$stmt->fetchAll(PDO::FETCH_ASSOC)]);
        }

    } elseif ($method === 'POST') {

        if ($action === 'comment') {

            if (
                empty($input['resource_id']) ||
                empty($input['author']) ||
                empty($input['text'])
            ) {
                send(['success'=>false], 400);
            }

            if (!is_numeric($input['resource_id'])) {
                send(['success'=>false], 400);
            }

            $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
            $stmt->execute([$input['resource_id']]);
            if (!$stmt->fetch()) {
                send(['success'=>false], 404);
            }

            $stmt = $db->prepare("INSERT INTO comments_resource (resource_id, author, text) VALUES (?,?,?)");
            $stmt->execute([
                trim($input['resource_id']),
                trim($input['author']),
                trim($input['text'])
            ]);

            send(['success'=>true, 'id'=>$db->lastInsertId()], 201);

        } else {

            if (empty($input['title']) || empty($input['link'])) {
                send(['success'=>false], 400);
            }

            if (!validUrl($input['link'])) {
                send(['success'=>false], 400);
            }

            $stmt = $db->prepare("INSERT INTO resources (title, description, link) VALUES (?,?,?)");
            $stmt->execute([
                trim($input['title']),
                trim($input['description'] ?? ''),
                trim($input['link'])
            ]);

            send(['success'=>true, 'id'=>$db->lastInsertId()], 201);
        }

    } elseif ($method === 'PUT') {

        if (empty($input['id']) || !is_numeric($input['id'])) {
            send(['success'=>false], 400);
        }

        $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
        $stmt->execute([$input['id']]);
        if (!$stmt->fetch()) {
            send(['success'=>false], 404);
        }

        $fields = [];
        $values = [];

        if (isset($input['title'])) {
            $fields[] = "title=?";
            $values[] = trim($input['title']);
        }

        if (isset($input['description'])) {
            $fields[] = "description=?";
            $values[] = trim($input['description']);
        }

        if (isset($input['link'])) {
            if (!validUrl($input['link'])) {
                send(['success'=>false], 400);
            }
            $fields[] = "link=?";
            $values[] = trim($input['link']);
        }

        if (empty($fields)) {
            send(['success'=>false], 400);
        }

        $values[] = $input['id'];

        $stmt = $db->prepare("UPDATE resources SET " . implode(',', $fields) . " WHERE id=?");
        $stmt->execute($values);

        send(['success'=>true]);

    } elseif ($method === 'DELETE') {

        if ($action === 'delete_comment') {

            if (!$comment_id || !is_numeric($comment_id)) {
                send(['success'=>false], 400);
            }

            $stmt = $db->prepare("SELECT id FROM comments_resource WHERE id=?");
            $stmt->execute([$comment_id]);
            if (!$stmt->fetch()) {
                send(['success'=>false], 404);
            }

            $stmt = $db->prepare("DELETE FROM comments_resource WHERE id=?");
            $stmt->execute([$comment_id]);

            send(['success'=>true]);

        } else {

            if (!$id || !is_numeric($id)) {
                send(['success'=>false], 400);
            }

            $stmt = $db->prepare("SELECT id FROM resources WHERE id=?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                send(['success'=>false], 404);
            }

            $stmt = $db->prepare("DELETE FROM resources WHERE id=?");
            $stmt->execute([$id]);

            send(['success'=>true]);
        }

    } else {
        send(['success'=>false], 405);
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    send(['success'=>false], 500);
}

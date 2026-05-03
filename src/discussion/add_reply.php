<?php
require_once ("../config/db.php");
if ($_SERVER["REQUEST_METHOD"] == "POST") { 
    $topic_id = $_POST['topic_id'];
    $content = $_POST['content'];

    $stmt = $conn->prepare("INSERT INTO replies (topic_id, content) VALUES (?,?)");
    $stmt->execute();

    header("Location: topics.php?id=" . $topic_id);
}



?>
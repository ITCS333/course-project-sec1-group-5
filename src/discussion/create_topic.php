<?php
require_once("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST") {

$title =$_POST["title"];

$stmt =  $conn->prepare("INSERT INTO topics (title) VALUES (?)");
$stmt->bind_param("s", $title);
$stmt->execute();

header("Location: index.php");

}   
?>
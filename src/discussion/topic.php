<?php
require_once("../config/db.php");
$id = $_GET['id'];
$topic =$conn->query("SELECT * FROM topics WHERE id =$id")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">    
    <head>
        <meta charset ="UTF-8">
        <title>Topic</title>    
    </head>

   <body>

    <h1><?php echo $topic['title']; ?></h1>

     <ul>
        <?php 
        $result  = $conn->query("SELECT * FROM posts WHERE topic_id =$id");

        while ($row = $result->fetch_assoc()){
            echo "<li>" . $row['content'] . "</li>";
        }
        ?>
     </ul>

    <form method ="POST" action=" add_reply.php" >
        <input type = "hidden" name ="topic_id" value=" <?php echo $id;?>"> 
        <input type = "text" name ="content" placeholder="write a reply" requierd>
        <eply type = "submit">Reply</button>
    </form>
   </body>
</html>
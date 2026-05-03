<?php 

use LDAP\Result;
require_once '../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">    
    <head>
        <meta charset ="UTF-8">
        <title>Discussion Board</title>    
    </head>

   <body>

    <h1> Discution Topics</h1>

     <form method ="POST" action="creat_topic.php">
            <input type="text" name="title" placeholder="Enter topic title" required> 
            <button type="submit">Create Topic </button>   
     </form>

     <ul>
        <?php 
        $result  = $conn->query("SELECT * FROM topics");

        while ($row = $result-> fetch_assoc()){
            echo "<li>";
            echo "<a href='topic.php?id=" . $row['id'] . "'>" . $row['title'] . "</a>";
            echo "</li>";
        }

        ?>
     </ul>
</body>
</html>
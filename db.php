<?php 
// db.php
// This file should be included whenever there will be interactions with the MySQL Database

$knoxyConn = mysqli_connect('172.18.0.3', 'cmpuser', 'cmppass', 'cmp') or die(mysqli_connect_error());
?>
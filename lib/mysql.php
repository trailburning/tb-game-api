<?php
function connect_db() {
/*
  $server = 'external-db.s225016.gridserver.com';
  $user = 'db225016';
  $pass = 'Summits0fMyLif3!';
  $database = 'db225016_tb_game';
*/  
  $server = 'localhost';
  $user = 'root';
  $pass = 'root';
  $database = 'tb_game';

  $connection = new mysqli($server, $user, $pass, $database);

  return $connection;
}

function getResultsFromDB($strSQL) {
  $db = connect_db();
  $result = $db->query($strSQL);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

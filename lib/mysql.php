<?php
function connect_db() {
  $connection = new mysqli($GLOBALS['db_server'], $GLOBALS['db_user'], $GLOBALS['db_pass'], $GLOBALS['db_name']);

  return $connection;
}

function getResultsFromDB($strSQL) {
  $db = mysqliSingleton::init();
  $result = $db->query($strSQL);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}
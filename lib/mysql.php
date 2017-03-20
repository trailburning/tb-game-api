<?php
function connect_db() {
  $server = 'localhost';
  $user = 'root';
  $pass = 'root';
  $database = 'tb_game';
/*
  $server = 'external-db.s51446.gridserver.com';
  $user = 'db51446';
  $pass = 'Ars3candy!';
  $database = 'db51446_tb_game';
*/
  $connection = new mysqli($server, $user, $pass, $database);

  return $connection;
}
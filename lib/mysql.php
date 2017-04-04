<?php
function connect_db() {

  $server = 'localhost';
  $user = 'root';
  $pass = 'root';
  $database = 'tb_game';

/*  
  $server = 'external-db.s167915.gridserver.com';
  $user = 'db167915';
  $pass = 'Ars3candy!';
  $database = 'db167915_tb_game';
*/  

  $connection = new mysqli($server, $user, $pass, $database);

  return $connection;
}
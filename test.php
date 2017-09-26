<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once('vendor/autoload.php');

$hashids = new Hashids\Hashids('mountainrush', 10);

$id = $hashids->encode(11);

var_dump($id);
exit;

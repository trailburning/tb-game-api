<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

require 'vendor/autoload.php';

//$bucket = 'mountainrush';
//$region = 'eu-west-2';
$bucket = 'trailburning-media';
$region = 'eu-west-1';

// when local use dotenv
if (!getenv("CLEARDB_DATABASE_URL")) {
  $dotenv = Dotenv\Dotenv::create(__DIR__);
  $dotenv->load();
  echo 'local<br/>';
}

// this will simply read AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY from env vars
$s3 = new Aws\S3\S3Client([
  'version'  => 'latest',
  'region'   => $region
]);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['upload_file']['tmp_name'])) {

  try {
    // FIXME: do not use 'name' for upload (that's the original filename from the user's computer)
    $upload = $s3->upload($bucket, $_FILES['upload_file']['name'], fopen($_FILES['upload_file']['tmp_name'], 'rb'), 'public-read');

    echo htmlspecialchars($upload->get('ObjectURL'));

  } catch(Exception $e) {
    echo $e;
  }
}
else {
  switch ($_FILES['upload_file']['error']) {
    case UPLOAD_ERR_INI_SIZE:
      echo 'File too big';
      break;    

    default:
      echo 'unknown error';
      break;
  }
}
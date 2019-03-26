<?php
include 'vendor/autoload.php';

function uploadAsset() {
  $bucket = 'mountainrush-media';
  $region = 'eu-west-3';

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
}

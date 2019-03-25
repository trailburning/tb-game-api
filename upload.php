<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'vendor/autoload.php';

//$bucket = 'mountainrush';
//$region = 'eu-west-2';
$bucket = 'trailburning-media';
$region = 'eu-west-1';

// this will simply read AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY from env vars
$s3 = new Aws\S3\S3Client([
    'version'  => 'latest',
    'region'   => $region,
    'credentials' => [
        'key'    => "AKIAIXMLDHGL6NFDSGFQ",
        'secret' => "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"
    ]
]);

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['userfile']) && $_FILES['userfile']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['userfile']['tmp_name'])) {

  try {
    // FIXME: do not use 'name' for upload (that's the original filename from the user's computer)
    $upload = $s3->upload($bucket, $_FILES['userfile']['name'], fopen($_FILES['userfile']['tmp_name'], 'rb'), 'public-read');

    echo htmlspecialchars($upload->get('ObjectURL'));

  } catch(Exception $e) {
    echo $e;
  }
}
<?php
include 'vendor/autoload.php';

function deleteAsset($strPath) {
  $bucket = 'mountainrush-media';
  $region = 'eu-west-3';

  $s3 = new Aws\S3\S3Client([
    'version'  => 'latest',
    'region'   => $region
  ]);

  // Delete an object from the bucket.
  $s3->deleteObject([
      'Bucket' => $bucket,
      'Key'    => $strPath
  ]);

}

function uploadAsset($strPath, $strFile) {
  $bucket = 'mountainrush-media';
  $region = 'eu-west-3';

  $s3 = new Aws\S3\S3Client([
    'version'  => 'latest',
    'region'   => $region
  ]);

  if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['upload_file']['tmp_name'])) {

    try {
      // first put path
      $s3->putObject(array( 
               'Bucket' => $bucket,
               'Key'    => $strPath,
               'Body'   => '',
               'ACL'    => 'public-read'
              ));
      // put uploaded file
      $upload = $s3->upload($bucket, $strPath . $strFile, fopen($_FILES['upload_file']['tmp_name'], 'rb'), 'public-read');

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

<?php
include 'vendor/autoload.php';

function addGameAssetToDB($gameID, $name) {
  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  $strSQL = 'INSERT INTO gamemedia (created, gameID, name) VALUES ("' . $dtNow . '", ' . $gameID . ', "' . $name . '")';
  if ($db->query($strSQL) === TRUE) {
    $lastInsertID = $db->insert_id;

    $ret = $lastInsertID;
  }
  return $ret;
}

function uploadAsset($hashCampaignID, $hashGameID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $bucket = 'mountainrush-media';
  $region = 'eu-west-3';

  $campaignID = $hashids->decode($hashCampaignID)[0];
  $gameID = $hashids->decode($hashGameID)[0];

  // this will simply read AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY from env vars
  $s3 = new Aws\S3\S3Client([
    'version'  => 'latest',
    'region'   => $region
  ]);

  if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['upload_file']['tmp_name'])) {

    // add to db
    $gameAssetID = addGameAssetToDB($gameID, $_FILES['upload_file']['name']);
    if ($gameAssetID) {
      $hashGameAssetID = $hashids->encode($gameAssetID);

      $strPath = $hashCampaignID . '/' . $hashGameID . '/';

      try {
        // first put path
        $s3->putObject(array( 
                 'Bucket' => $bucket,
                 'Key'    => $strPath,
                 'Body'   => '',
                 'ACL'    => 'public-read'
                ));
        // put uploaded file
        $upload = $s3->upload($bucket, $strPath . $hashGameAssetID, fopen($_FILES['upload_file']['tmp_name'], 'rb'), 'public-read');

        echo htmlspecialchars($upload->get('ObjectURL'));

      } catch(Exception $e) {
        echo $e;
      }
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

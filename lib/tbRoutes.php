<?php
include 'vendor/autoload.php';

function addRouteToDB($name, $description) {
  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  if ($db->query('INSERT INTO routes (created, name, description) VALUES ("' . $dtNow . '", "' . $name . '", "' . $description . '")') === TRUE) {
    $lastInsertID = $db->insert_id;
    $ret = getRouteFromDB($db, $lastInsertID);
  }

  return $ret;
}

function addRoutePointToDB($routeID, $fLat, $fLng, $fAlt) {
  $db = connect_db();
  $db->query('INSERT INTO routepoints (route, lat, lon, alt) VALUES (' . $routeID . ', ' . $fLat . ', ' . $fLng . ', ' . $fAlt . ')');
}

function deleteRoutePointsFromDB($routeID) {
  $db = connect_db();
  $db->query('DELETE FROM routepoints WHERE route = ' . $routeID);
}

function getRouteFromDB($db, $routeID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $result = $db->query('SELECT id, name, description FROM routes where id = ' . $routeID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getRoutePointsFromDB($db, $routeID) {
  $result = $db->query('SELECT lat, lon, alt FROM routepoints where route = ' . $routeID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $newRow = array(); 

    $newRow['coords'][0] = floatval($row['lon']);
    $newRow['coords'][1] = floatval($row['lat']);
    $newRow['coords'][2] = floatval($row['alt']);

    $rows[$index] = $newRow;
    $index++;
  }

  return $rows;
}

function getRouteWithPointsFromDB($routeID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();

  $result = $db->query('SELECT id, name, description FROM routes where id = ' . $routeID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);
    $row['route_points'] = getRoutePointsFromDB($db, $routeID);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getRoutesFromDB() {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();

  $result = $db->query('SELECT id, name, description FROM routes');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function uploadRoute($routeID) {
  // delete old route
  deleteRoutePointsFromDB($routeID);

  // now load new route
  $gpx = simplexml_load_file($_FILES['upload_file']['tmp_name']);
  foreach ($gpx->rte as $rte) {
    foreach ($rte->rtept as $rtept) {
      $fAlt = 0;
      foreach ($rtept->ele as $ele) {
        $fAlt = $ele;
      }
      addRoutePointToDB($routeID, $rtept['lat'], $rtept['lon'], $fAlt);
    }
  }
}

function uploadRouteAsset($hashRouteID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $bucket = 'mountainrush-media';
  $region = 'eu-west-3';

  // this will simply read AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY from env vars
  $s3 = new Aws\S3\S3Client([
    'version'  => 'latest',
    'region'   => $region
  ]);

  if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['upload_file']) && $_FILES['upload_file']['error'] == UPLOAD_ERR_OK && is_uploaded_file($_FILES['upload_file']['tmp_name'])) {

    $strPath = 'routes/' . $hashRouteID . '/';

    echo 'mime:' . mime_content_type($_FILES['upload_file']['tmp_name']);

    try {
      // first put path
      $s3->putObject(array( 
               'Bucket' => $bucket,
               'Key'    => $strPath,
               'Body'   => '',
               'ACL'    => 'public-read'
              ));
      // put uploaded file
      $upload = $s3->upload($bucket, $strPath . $hashRouteID, fopen($_FILES['upload_file']['tmp_name'], 'rb'), 'public-read');

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

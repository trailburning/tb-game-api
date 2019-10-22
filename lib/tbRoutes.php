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

//  echo 't1:' . $_FILES['upload_file']['tmp_name'] . '<br/>';
//  echo 't2:' . $_FILES['upload_file']['name'] . '<br/>';

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
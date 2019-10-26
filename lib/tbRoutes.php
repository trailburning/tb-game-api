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

function getRouteEventsFromDB($routeID, $langID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();

  $result = $db->query('SELECT id, lat, lon, name, description FROM routeevents where routeID = ' . $routeID . ' AND languageID = ' . $langID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $newRow = array(); 

    $newRow['id'] = $hashids->encode($row['id']);
    $newRow['coords'] = array();
    $newRow['coords'][0] = floatval($row['lon']);
    $newRow['coords'][1] = floatval($row['lat']);
    $newRow['name'] = $row['name'];
    $newRow['description'] = $row['description'];
    $newRow['assets'] = getRouteEventAssets($db, $row['id']);

    $rows[$index] = $newRow;
    $index++;
  }

  return $rows;
}

function getRouteEventFromDB($db, $routeEventID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $result = $db->query('SELECT id, lat, lon, name, description FROM routeevents where id = ' . $routeEventID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $newRow = array(); 

    $newRow['id'] = $hashids->encode($row['id']);
    $newRow['coords'] = array();
    $newRow['coords'][0] = floatval($row['lon']);
    $newRow['coords'][1] = floatval($row['lat']);
    $newRow['name'] = $row['name'];
    $newRow['description'] = $row['description'];
    $newRow['assets'] = array();

    $rows[$index] = $newRow;
    $index++;
  }

  return $rows;
}

function addRouteEventToDB($routeID, $lat, $lng, $name, $description) {
  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  if ($db->query('INSERT INTO routeevents (created, routeID, lat, lon, name, description) VALUES ("' . $dtNow . '", ' . $routeID . ', ' . $lat. ', ' . $lng . ', "' . $name . '", "' . $description . '")') === TRUE) {
    $lastInsertID = $db->insert_id;
    $ret = getRouteEventFromDB($db, $lastInsertID);
  }

  return $ret;
}

function getRouteEventAssets($db, $eventID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $result = $db->query('SELECT id, name, description FROM routeeventassets where eventID = ' . $eventID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $ID = $row['id'];
    $row['id'] = $hashids->encode($row['id']);
    $row['media'] = getRouteEventAssetMedias($db, $ID);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getRouteEventAssetFromDB($db, $ID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $result = $db->query('SELECT id, name, description FROM routeeventassets where id = ' . $ID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function addRouteEventAssetToDB($eventID, $name, $description) {
  $ret = null;

  $db = connect_db();
  if ($db->query('INSERT INTO routeeventassets (eventID, name, description) VALUES (' . $eventID . ', "' . $name . '", "' . $description . '")') === TRUE) {
    $lastInsertID = $db->insert_id;
    $ret = getRouteEventAssetFromDB($db, $lastInsertID);
  }

  return $ret;
}
function getRouteEventAssetMedia($db, $id) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $result = $db->query('SELECT id, name, mimeType FROM routeeventassetmedia where id = ' . $id);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getRouteEventAssetMedias($db, $assetID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $result = $db->query('SELECT id, name, mimeType FROM routeeventassetmedia where assetID = ' . $assetID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function addRouteEventAssetMediaToDB($assetID, $name, $mimeType) {
  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  $strSQL = 'INSERT INTO routeeventassetmedia (created, assetID, name, mimeType) VALUES ("' . $dtNow . '", ' . $assetID . ', "' . $name . '", "' . $mimeType . '")';
  if ($db->query($strSQL) === TRUE) {
    $lastInsertID = $db->insert_id;

    $ret = $lastInsertID;
  }
  return $ret;
}

function deleteRouteEventAssetMediaFromDB($id) {
  $db = connect_db();
  $db->query('DELETE FROM routeeventassetmedia WHERE id = ' . $id);
}

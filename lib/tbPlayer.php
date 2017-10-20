<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;


function addPlayerToDB($avatar, $firstname, $lastname, $email, $city, $country, $providerID, $providerToken) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  if ($db->query('INSERT INTO players (created, avatar, firstname, lastname, email, city, country, playerProviderID, playerProviderToken) VALUES ("' . $dtNow . '", "' . $avatar . '", "' . $firstname . '", "' . $lastname . '", "' . $email. '", "' . $city . '", "' . $country. '", "' . $providerID . '", "' . $providerToken . '")') === TRUE) {
    $lastInsertID = $db->insert_id;

    $hashID = $hashids->encode($lastInsertID);

    $result = $db->query('UPDATE players SET hashid = "' . $hashID . '" WHERE id = ' . $db->insert_id);

    $ret = getPlayerFromDB($lastInsertID);
  }
  return $ret;
}

function getPlayerFromDBByToken($token) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE playerProviderToken = "' . $token . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerFromDBByProviderID($providerID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE playerProviderID = "' . $providerID . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerFromDB($playerID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, avatar, firstname, lastname, email, last_activity, last_updated, playerProviderToken FROM players where id = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function updatePlayerLastUpdatedInDB($playerID, $dtLastUpdated) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('update players set last_updated = "' . $dtLastUpdated . '" where id = ' . $playerID);
}

function updatePlayerLastActivityInDB($playerID, $dtLastActivity) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('update players set last_activity = "' . $dtLastActivity . '" where id = ' . $playerID);
}

function updatePlayerDetailsInDB($playerID, $avatar, $firstname, $lastname, $email, $city, $country) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('update players set avatar = "' . $avatar . '", firstname = "' . $firstname . '", lastname = "' . $lastname . '", email = "' . $email .'", city = "' . $city . '", country = "' . $country . '" where id = ' . $playerID);
}

function updatePlayer($token) {
  $results = getPlayerFromDBByToken($token);
  if (count($results) != 0) {
    // get from provider
    $adapter = new Pest('https://www.strava.com/api/v3');
    $service = new REST($token, $adapter);

    $client = new Client($service);
    $activities = $client->getAthlete();

    updatePlayerDetailsInDB($results[0]['id'], $activities['profile'], $activities['firstname'], $activities['lastname'], $activities['email'], $activities['city'], $activities['country']);
  }
}

function getPlayer($token) {
  $results = getPlayerFromDBByToken($token);
  if (count($results) == 0) {
    // get from provider
    $adapter = new Pest('https://www.strava.com/api/v3');
    $service = new REST($token, $adapter);

    $client = new Client($service);
    $activities = $client->getAthlete();

    addPlayerToDB($activities['profile'], $activities['firstname'], $activities['lastname'], $activities['email'], $activities['city'], $activities['country'], $activities['id'], $token);

    $results = getPlayerFromDBByToken($token);
 }

  return $results;
}
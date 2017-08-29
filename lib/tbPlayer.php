<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

function addPlayerToDB($avatar, $firstname, $lastname, $email, $city, $country, $providerID, $providerToken) {
  require_once 'lib/mysql.php';

  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  $result = $db->query('INSERT INTO players (created, avatar, firstname, lastname, email, city, country, playerProviderID, playerProviderToken) VALUES ("' . $dtNow . '", "' . $avatar . '", "' . $firstname . '", "' . $lastname . '", "' . $email. '", "' . $city . '", "' . $country. '", "' . $providerID . '", "' . $providerToken . '")');
}

function getPlayerFromDBByToken($token) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, avatar, firstname, lastname, email, last_activity, last_updated FROM players where playerProviderToken = "' . $token . '"');
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
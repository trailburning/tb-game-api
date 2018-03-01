<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

define('DEF_CAMPAIGN', 1); // Mountain Rush

function addPlayerToDB($refererCampaignID, $avatar, $firstname, $lastname, $email, $city, $country, $providerID, $providerToken) {
  require_once 'lib/mysql.php';

  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  if ($db->query('INSERT INTO players (created, referer_campaign, avatar, firstname, lastname, email, city, country, game_notifications, playerProviderID, playerProviderToken) VALUES ("' . $dtNow . '", ' . $refererCampaignID . ', "' . $avatar . '", "' . $firstname . '", "' . $lastname . '", "' . $email. '", "' . $city . '", "' . $country. '", 1, "' . $providerID . '", "' . $providerToken . '")') === TRUE) {
    $lastInsertID = $db->insert_id;

    $ret = getPlayerFromDB($lastInsertID);
  }
  return $ret;
}

function getPlayerFromDBByToken($token) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, referer_campaign, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE playerProviderToken = "' . $token . '"');
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
  $result = $db->query('SELECT id, created, referer_campaign, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE playerProviderID = "' . $providerID . '"');
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
  $result = $db->query('SELECT id, referer_campaign, avatar, firstname, lastname, email, last_activity, last_updated, playerProviderToken FROM players where id = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayersFromDBByCampaign($campaignID, $match) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT players.id, players.firstname, players.lastname, games.id as gameID, games.type as game_type, gameLevels.name as level_name FROM players JOIN gamePlayers ON players.id = gamePlayers.player JOIN games ON gamePlayers.game = games.id  JOIN gameLevels ON games.levelID = gameLevels.id WHERE games.campaignID = ' . $campaignID . ' AND (LOWER(players.firstname) LIKE "%' . $match . '%" OR LOWER(players.lastname) like "%' . $match . '%")');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);
    $row['gameID'] = $hashids->encode($row['gameID']);

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
    $athlete = $client->getAthlete();

    addPlayerToDB(DEF_CAMPAIGN, $athlete['profile'], $athlete['firstname'], $athlete['lastname'], $athlete['email'], $athlete['city'], $athlete['country'], $athlete['id'], $token);

    $results = getPlayerFromDBByToken($token);
 }

  return $results;
}
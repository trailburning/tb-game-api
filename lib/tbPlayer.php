<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

function addPlayerToDB($clientID, $avatar, $firstname, $lastname, $email, $city, $country, $providerID, $providerToken) {
  require_once 'lib/mysql.php';

  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  if (doesClientPlayerProviderIDAlreadyExistInDB($clientID, $providerID)) {
    // insert failed so the email has already been used, let's try an update
    updatePlayerDetailsInDB($avatar, $firstname, $lastname, $email, $city, $country, $providerToken);
    $ret = getClientPlayerFromDBByProviderID($clientID, $providerID);
  }
  else {
    $db = connect_db();
    if ($db->query('INSERT INTO players (created, clientID, avatar, firstname, lastname, email, city, country, game_notifications, playerProviderID, playerProviderToken) VALUES ("' . $dtNow . '", ' . $clientID . ', "' . $avatar . '", "' . $firstname . '", "' . $lastname . '", "' . $email. '", "' . $city . '", "' . $country. '", 1, "' . $providerID . '", "' . $providerToken . '")') === TRUE) {
      $lastInsertID = $db->insert_id;
      $ret = getPlayerFromDB($db, $lastInsertID);
    }
  }
  return $ret;
}

function getPlayerFromDBByEmail($clientID, $email) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE clientID = ' . $clientID . ' and email = "' . $email . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getClientPlayerFromDBByProviderID($clientID, $playerProviderID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE clientID = ' . $clientID . ' and playerProviderID = "' . $playerProviderID . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerFromDBByToken($clientID, $token) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $strSQL = 'SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, game_notifications, measurement, playerProviderID, last_activity, last_updated FROM players WHERE clientID = ' . $clientID . ' and playerProviderToken = "' . $token . '" ORDER BY id LIMIT 1';
  $result = $db->query($strSQL);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerFromDBByID($playerID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, game_notifications, measurement, playerProviderID, playerProviderToken, last_activity, last_updated FROM players WHERE id = ' . $playerID);
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
  $result = $db->query('SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE playerProviderID = "' . $providerID . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerFromDB($db, $playerID) {
  $result = $db->query('SELECT id, clientID, avatar, firstname, lastname, email, game_notifications, measurement, last_activity, last_updated, playerProviderToken FROM players where id = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerByIDFromDB($playerID) {
  $db = connect_db();

  $result = $db->query('SELECT id, clientID, avatar, firstname, lastname, email, game_notifications, measurement, last_activity, last_updated, playerProviderToken FROM players where id = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerDetailsFromDB($playerID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT id, avatar, firstname, lastname FROM players where id = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayersFromDBByCampaign($campaignID, $match) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT players.id, players.firstname, players.lastname, games.id as gameID, games.type as game_type, games.game_start, games.game_end, gameLevels.name as level_name FROM players JOIN gamePlayers ON players.id = gamePlayers.player JOIN games ON gamePlayers.game = games.id JOIN gameLevels ON games.levelID = gameLevels.id WHERE games.campaignID = ' . $campaignID . ' AND (LOWER(players.firstname) LIKE "%' . $match . '%" OR LOWER(players.lastname) like "%' . $match . '%") ORDER BY lastname, games.game_start DESC');
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

function doesClientPlayerProviderIDAlreadyExistInDB($clientID, $playerProviderID) {
  $bRet = false;

  $db = connect_db();

  // see if another player already has the email address
  $strSQL = 'SELECT * FROM players WHERE clientID = ' . $clientID . ' AND playerProviderID = "' . $playerProviderID . '"';
  $resultExistingPlayer = $db->query($strSQL);      
  if (mysqli_num_rows($resultExistingPlayer)) {
    $bRet = true;
  }
  return $bRet;
}

function doesPlayerEmailAlreadyExistInDB($playerID, $clientID, $strEmail) {
  $bRet = false;

  $db = connect_db();

  // see if another player already has the email address
  $strSQL = 'SELECT * FROM players WHERE clientID = ' . $clientID . ' AND email = "' . $strEmail . '" and id != ' . $playerID;
  $resultExistingPlayer = $db->query($strSQL);      
  if (mysqli_num_rows($resultExistingPlayer)) {
    $bRet = true;
  }
  return $bRet;
}

function updatePlayerPreferencesInDB($playerID, $strEmail, $bReceiveEmail) {
  $bRet = false;

  $db = connect_db();
  // first get player
  $resultPlayer = $db->query('SELECT * FROM players WHERE id = ' . $playerID);
  if (mysqli_num_rows($resultPlayer)) {
    while ( $row = $resultPlayer->fetch_array(MYSQLI_ASSOC) ) {
      if (doesPlayerEmailAlreadyExistInDB($playerID, $row['clientID'], $strEmail)) {
        $result = null;
      }
      else {
        $strSQL = 'update players set email = "' . $strEmail . '", game_notifications = ' . $bReceiveEmail . ' where id = ' . $playerID;
        $result = $db->query($strSQL);
        $bRet = true;
      }
    }
  }
  return $bRet;
}

function updatePlayerDetailsInDB($avatar, $firstname, $lastname, $email, $city, $country, $token) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('update players set avatar = "' . $avatar . '", firstname = "' . $firstname . '", lastname = "' . $lastname . '", email = "' . $email .'", city = "' . $city . '", country = "' . $country . '", playerProviderToken = "' . $token . '" where email = "' . $email . '"');
}

function updatePlayerDetailsWithoutEmailInDB($avatar, $firstname, $lastname, $city, $country, $token) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('update players set avatar = "' . $avatar . '", firstname = "' . $firstname . '", lastname = "' . $lastname . '", city = "' . $city . '", country = "' . $country . '", playerProviderToken = "' . $token . '" where email = "' . $email . '"');
}

function updatePlayer($playerID) {
  $results = getPlayerFromDBByID($playerID);
  if (count($results) != 0) {
    $token = $results[0]['playerProviderToken'];

    // get from provider
    $adapter = new Pest('https://www.strava.com/api/v3');
    $service = new REST($token, $adapter);

    $client = new Client($service);
    $activities = $client->getAthlete();

    var_dump($activities);

    updatePlayerDetailsWithoutEmailInDB($activities['profile'], $activities['firstname'], $activities['lastname'], $activities['city'], $activities['country'], $token);
  }
}

function updatePlayerBlankDetails($playerProviderID) {
  // used when provider requires data to be blanked - hello GDPR!
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('UPDATE players SET avatar = "", firstname = "", lastname = "", city = "", country = "" WHERE playerProviderID = "' . $playerProviderID . '"');
}

function getPlayer($clientID, $token) {
  $results = getPlayerFromDBByToken($clientID, $token);
  if (count($results) == 0) {
    // get from provider
    $adapter = new Pest('https://www.strava.com/api/v3');
    $service = new REST($token, $adapter);

    $client = new Client($service);
    $athlete = $client->getAthlete();

    // 181220 MLA - note that from 190116 Strava will no longer return email addresses so we now set blank.
    addPlayerToDB($clientID, $athlete['profile'], $athlete['firstname'], $athlete['lastname'], '', $athlete['city'], $athlete['country'], $athlete['id'], $token);

    $results = getPlayerFromDBByToken($clientID, $token);
  }
  return $results;
}
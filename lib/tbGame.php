<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

const STATE_GAME_OVER = 'complete';
const STATE_GAME_ACTIVE = 'active';
const STATE_GAME_PENDING = 'pending';

function addGameToDB($campaignID, $season, $type, $gameStart, $gameEnd, $levelID) {
  require_once 'lib/mysql.php';

  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  if ($db->query('INSERT INTO games (created, campaignID, season, type, game_start, game_end, levelID) VALUES ("' . $dtNow . '", ' . $campaignID . ', ' . $season . ', "' . $type . '", "' . $gameStart . '", "' . $gameEnd . '", ' . $levelID . ')') === TRUE) {
    $lastInsertID = $db->insert_id;

    $ret = getGameFromDB($lastInsertID);
  }
  return $ret;
}

function addPlayerGameInDB($gameID, $playerID) {
  require_once 'lib/mysql.php';

  $ret = null;

  // only set once
  $db = connect_db();

  $db->query('INSERT INTO gamePlayers (game, player) VALUES (' . $gameID . ', ' . $playerID . ')');
  
  $ret = getGamePlayerFromDB($gameID, $playerID);

  return $ret;
}

function setPlayerGameStateInDB($gameID, $playerID, $state) {
  // only set once
  $db = connect_db();
  $db->query('UPDATE gamePlayers SET state = ' . $state . ' where game = ' . $gameID . ' and player = ' . $playerID);
}

function setPlayerGameActivityInDB($gameID, $playerID, $activity) {
  // only set once
  $db = connect_db();
  $db->query('UPDATE gamePlayers SET latest_activity = ' . $activity . ' where game = ' . $gameID . ' and player = ' . $playerID);
}

function setPlayerGameFundraisingPageInDB($gameID, $playerID, $fundraisingPageID, $fundraisingPage, $fundraisingGoal) {
  $db = connect_db();
  $db->query('UPDATE gamePlayers SET fundraising_pageID = "' . $fundraisingPageID . '", fundraising_page = "' . $fundraisingPage . '", fundraising_goal = ' . $fundraisingGoal . ' where game = ' . $gameID . ' and player = ' . $playerID);
}

function setPlayerGameAscentCompleteInDB($gameID, $playerID, $ascentCompleteActivityDate) {
  // only set once
  $db = connect_db();
  $result = $db->query('SELECT ascentCompleted FROM gamePlayers where game = ' . $gameID . ' and player = ' . $playerID);
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    if (!$row['ascentCompleted']) {
      $db->query('UPDATE gamePlayers SET ascentCompleted = "' . $ascentCompleteActivityDate . '" where game = ' . $gameID . ' and player = ' . $playerID);
    }
  }
}

function setPlayerGameActivityTotalsInDB($gameID, $playerID, $ascent, $distance) {
  $db = connect_db();
  $db->query('UPDATE gamePlayers SET ascent = ' . $ascent . ', distance = ' . $distance . ' where game = ' . $gameID . ' and player = ' . $playerID);
}

function setPlayerGameFundraisingDetailsInDB($gameID, $playerID, $fundraisingGoal, $fundraisingRaised, $fundraisingCurrency) {
  require_once 'lib/mysql.php';

  // only set once
  $db = connect_db();
  $db->query('UPDATE gamePlayers SET fundraising_goal = ' . $fundraisingGoal . ', fundraising_raised = ' . $fundraisingRaised . ', fundraising_currency = "' . $fundraisingCurrency . '" where game = ' . $gameID . ' and player = ' . $playerID);
}

function setPlayerGameDistanceCompleteInDB($gameID, $playerID, $distanceCompleteActivityDate) {
  // only set once
  $db = connect_db();
  $result = $db->query('SELECT distanceCompleted FROM gamePlayers where game = ' . $gameID . ' and player = ' . $playerID);
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    if (!$row['distanceCompleted']) {
      $db->query('UPDATE gamePlayers SET distanceCompleted = "' . $distanceCompleteActivityDate . '" where game = ' . $gameID . ' and player = ' . $playerID);
    }
  }
}

function setPlayerGameMediaCaptureInDB($gameID, $playerID) {
  // only set once
  $db = connect_db();

  $result = $db->query('UPDATE gamePlayers SET bMediaCaptured = true WHERE game = ' . $gameID . ' and player = ' . $playerID);
}

function getGamePlayersFromDB($gameID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT players.id, avatar, firstname, lastname, email, city, country, game_notifications, playerProviderID, gamePlayers.fundraising_pageID, gamePlayers.fundraising_page FROM gamePlayers JOIN players ON gamePlayers.player = players.id WHERE game = ' . $gameID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $hashID = $hashids->encode($row['id']);
    $row['id'] = $hashID;

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getGamesFromDB() {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");

  $db = connect_db();
  $result = $db->query('SELECT games.id, season, type, game_start, game_end, gameLevels.name, gameLevels.region, gameLevels.ascent, gameLevels.journeyID, gameLevels.mountain3DName, campaigns.email_template FROM games JOIN gameLevels ON games.levelID = gameLevels.id JOIN campaigns ON games.campaignID = campaigns.id order by game_end desc');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $hashID = $hashids->encode($row['id']);
    $row['id'] = $hashID;

    // format dates as UTC
    $dtStartDate = new DateTime($row['game_start']);
    $row['game_start'] = $dtStartDate->format('Y-m-d\TH:i:s.000\Z');
    $dtEndDate = new DateTime($row['game_end']);
    $row['game_end'] = $dtEndDate->format('Y-m-d\TH:i:s.000\Z');

    $row['game_state'] = STATE_GAME_OVER;
    if ($dtStartDate < $dtNow && $dtEndDate > $dtNow) {
      $row['game_state'] = STATE_GAME_ACTIVE;
    }

    if ($dtStartDate > $dtNow) {
      $row['game_state'] = STATE_GAME_PENDING;
    }

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getPlayerActivtyByGameFromDB($gameID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT gamePlayers.latest_activity, gamePlayers.state, players.id, avatar, firstname, lastname, city, country, playerProviderToken FROM gamePlayers JOIN players ON gamePlayers.player = players.id WHERE game = ' . $gameID . ' and latest_activity != 0');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getGamesByPlayerFromDB($playerID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");

  $db = connect_db();
  $result = $db->query('SELECT gamePlayers.game, gamePlayers.fundraising_pageID, gamePlayers.fundraising_page, games.type, games.game_start, games.game_end, gameLevels.name, gameLevels.region, gameLevels.ascent, campaigns.name as campaign_name, campaigns.fundraising_page as campaign_fundraising_page FROM gamePlayers join games on gamePlayers.game = games.id join gameLevels on games.levelID = gameLevels.id join campaigns on games.campaignID = campaigns.id where player = ' . $playerID . ' order by games.game_end desc');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $hashID = $hashids->encode($row['game']);
    $row['game'] = $hashID;

    // format dates as UTC
    $dtStartDate = new DateTime($row['game_start']);
    $row['game_start'] = $dtStartDate->format('Y-m-d\TH:i:s.000\Z');
    $dtEndDate = new DateTime($row['game_end']);
    $row['game_end'] = $dtEndDate->format('Y-m-d\TH:i:s.000\Z');

    $row['game_state'] = STATE_GAME_OVER;
    if ($dtStartDate < $dtNow && $dtEndDate > $dtNow) {
      $row['game_state'] = STATE_GAME_ACTIVE;
    }

    if ($dtStartDate > $dtNow) {
      $row['game_state'] = STATE_GAME_PENDING;
    }

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getGameFromDB($gameID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT games.id, campaignID, season, type, game_start, game_end, gameLevels.name, gameLevels.region, gameLevels.ascent, gameLevels.journeyID, gameLevels.mountainType, gameLevels.mountain3DName FROM games JOIN gameLevels ON games.levelID = gameLevels.id where games.id = ' . $gameID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);
    $row['campaignID'] = $hashids->encode($row['campaignID']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getGamePlayerFromDB($gameID, $playerID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT fundraising_pageID, fundraising_page, fundraising_goal, fundraising_raised, fundraising_currency, bMediaCaptured, ascentCompleted, distanceCompleted FROM gamePlayers where game = ' . $gameID . ' and player = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    if ($row['ascentCompleted']) {
      // format date as UTC
      $dtAscentCompleted = new DateTime($row['ascentCompleted']);
      $row['ascentCompleted'] = $dtAscentCompleted->format('Y-m-d\TH:i:s.000\Z');
    }
    $rows[$index] = $row;
    $index++;
  }
  return $rows;
}

function getGamePlayerActivityPhotos($gameID, $playerID, $activityID) {
  $ret = [];

  // first find last update date
  $results = getPlayerFromDB($playerID);
  if (count($results) != 0) {
    $token = $results[0]['playerProviderToken'];
    try {
      $adapter = new Pest('https://www.strava.com/api/v3');
      $service = new REST($token, $adapter);

      $client = new Client($service);
      $activityPhotos = $client->getActivityPhotos($activityID, $size = 640, $photo_sources = 'true');

      $ret = $activityPhotos;
    } catch(\Exception $e) {
    }
  }  
  return $ret;
}

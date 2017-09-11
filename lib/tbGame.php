<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

function addGameToDB($name, $ascent, $type, $gameStart, $gameEnd, $journeyID, $mountain3DName) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $ret = null;

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  if ($db->query('INSERT INTO games (created, name, ascent, type, game_start, game_end, journeyID, mountain3DName) VALUES ("' . $dtNow . '", "' . $name . '", ' . $ascent . ', "' . $type . '", "' . $gameStart . '", "' . $gameEnd . '", "' . $journeyID . '", "' . $mountain3DName . '")') === TRUE) {
    $lastInsertID = $db->insert_id;

    $hashID = $hashids->encode($lastInsertID);

    $result = $db->query('UPDATE games SET hashid = "' . $hashID . '" WHERE id = ' . $db->insert_id);

    $ret = getGameFromDB($lastInsertID);
  }
  return $ret;
}

function updateaGameInDB($gameID, $name) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('update games set name = "' . $name . '" where id = ' . $gameID);
}

function getGamePlayersFromDB($gameID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT players.id, avatar, firstname, lastname, city, country FROM gamePlayers join players on gamePlayers.player = players.id where game = ' . $gameID);
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

function getGamesByPlayerFromDB($playerID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");

  $db = connect_db();
  $result = $db->query('SELECT gamePlayers.game, games.name, games.ascent, games.type, games.game_start, games.game_end FROM gamePlayers join games on gamePlayers.game = games.id where player = ' . $playerID . ' order by games.game_end desc');
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

    $row['active'] = false;
    if ($dtStartDate < $dtNow && $dtEndDate > $dtNow) {
      $row['active'] = true;
    }

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getGameFromDB($gameID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, name, ascent, type, game_start, game_end, journeyID, mountain3DName FROM games where id = ' . $gameID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

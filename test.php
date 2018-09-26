<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('MR_DOMAIN', 'http://mountainrush.co.uk/');
define('MR_SECURE_DOMAIN', 'https://mountainrush.co.uk/');

include "lib/tbLog.php";
include "lib/tbEmail.php";
include "lib/tbSocial.php";
include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";

require_once('vendor/autoload.php');
require_once 'lib/mysqliSingleton.php';

const DEBUG = false;
//const DEBUG = true;

$GLOBALS['db_server'] = 'localhost';
$GLOBALS['db_user'] = 'root';
$GLOBALS['db_pass'] = 'root';
$GLOBALS['db_name'] = 'tb_game';

if (getenv("CLEARDB_DATABASE_URL")) {
  $url = parse_url(getenv("CLEARDB_DATABASE_URL"));

  $GLOBALS['db_server'] = $url["host"];
  $GLOBALS['db_user'] = $url["user"];
  $GLOBALS['db_pass'] = $url["pass"];
  $GLOBALS['db_name'] = substr($url["path"], 1);
}

/*
$game = array(
  'id' => 'yKerNk4mwM',
  'name' => 'Monte Pelmo',
  'journeyID' => '59d4ad31a276a319404809',
//  'email_template' => 'TB Member EDM'
  'email_template' => 'MR Game - WWF'
);

$player = array(
  'id' => 'b31r7RZ7Xo',
  'firstname' => 'Matt',
  'lastname' => 'Allbeury',
  'email' => 'mallbeury@mac.com'
);

$activePlayer = array(
  'id' => '36',
  'firstname' => 'Matt',
  'lastname' => 'Allbeury',
  'email' => 'mallbeury@mac.com'
);

sendActivityEmail($game, $player, $activePlayer);
*/

$hashids = new Hashids\Hashids('mountainrush', 10);

$id = $hashids->encode(1);
var_dump($id);

$id = $hashids->decode('5pYoEpPrdW')[0];
var_dump($id);

//addLogToDB(LOG_OBJECT_GAME, LOG_ACTIVITY_CREATE, 2084);

//$strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/djJrblYlXV/challenge_ready_682x300.jpg';
//sendEmail('EDM - Mountain Rush', 'MR Test', 'mallbeury@mac.com', 'Matt', $strImage, 'Welcome', 'Player Activity', 'Your have progressed in the <a href="">challenge</a>.', '<a href="">change your preferences</a>');

// MR
//$activePlayerID = 36;
//$gameID = 2041;
//$gameID = 2114; // 2 player
//$LatestActivity = 1626880620;

// CFYW
$activePlayerID = 164;
$gameID = 2036;
$LatestActivity = 1593291827;


// custom email
function getCustomPlayersFromDB($clientID) {
  require_once 'lib/mysql.php';

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE clientID = ' . $clientID . ' and lastname = "Allbeury"');
//  $result = $db->query('SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE clientID = ' . $clientID . ' and created > "2018-09-15" order by created asc');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function sendTestEmail($strEmailTemplate, $jsonEmail, $player) {
  $jsonEmail = replacePlayerTags($jsonEmail, $player);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $arrEmail->title;

  echo $arrEmail->message;
  return;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($strEmailTemplate, $strSubject . ' DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

$clientID = '2';
$jsonPlayerResponse = getCustomPlayersFromDB($clientID);
if (count($jsonPlayerResponse)) {
  $jsonEmail = '{"title": "Sorry for the inconvenience,<br/>we had an unexpected problem", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/djJrblYlXV/challenge_activity_682x300.jpg?q=80", "message": "<p>[PLAYER_FIRSTNAME],</p><p>During the recent launch of the <a href=\"https://wwf.org.uk/climbforyourworld\">Climb For Your World</a> campaign we experienced a technical problem with the fundraising integration.</p><p>This problem has been resolved and you can now add fundraising at your leisure.</p><p>Simply visit <a href=\"https://wwf.org.uk/climbforyourworld\">Climb For Your World</a> and sign in, then proceed to your challenge and click ENABLE FUNDRAISING.</p><p>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": ""}';

  foreach ($jsonPlayerResponse as $player) {
    echo 'p:' . $player['firstname'] . ' ' . $player['lastname'] . '<br/>';
    sendTestEmail('EDM - Mountain Rush', $jsonEmail, $player);
  }
}

/*
$jsonPlayerResponse = getGamePlayersFromDB($gameID);

$jsonGamesResponse = getGameFromDB($gameID);
if (count($jsonGamesResponse)) {
  foreach ($jsonGamesResponse as $game) {
    if (count($jsonPlayerResponse)) {
      // send invite
      $jsonInvitingPlayerResponse = getPlayerDetailsFromDB(67);
      foreach ($jsonInvitingPlayerResponse as $invitingPlayer) {
        $player = array();
        $player['id'] = 0;
        $player['firstname'] = 'Matt';
        $player['lastname'] = '';
        $player['email'] = 'mallbeury@mac.com';

        // invite email
        $jsonEmail = $game['email_invite'];
//        sendInviteEmail($jsonEmail, $game, $invitingPlayer, $player);
      }

      $activePlayer = null;
      foreach ($jsonPlayerResponse as $player) {
        $playerID = $hashids->decode($player['id'])[0];

        if ($playerID == $activePlayerID) {
          $activePlayer = $player;
        }

        if ($player['game_notifications']) {
          $activity = getPlayerActivity($activePlayer['playerProviderToken'], $LatestActivity);
          if ($activity) {
            // activity email
            $jsonEmail = $game['email_activity_broadcast'];
            if ($player['id'] == $activePlayer['id']) {
              $jsonEmail = $game['email_activity'];
            }
            sendActivityEmail($jsonEmail, $game, $player, $activePlayer, $activity);
          }

          // welcome email
          $jsonEmail = $game['email_welcome'];
          sendWelcomeEmail($jsonEmail, $game, $player);

          // inactivity email
          $jsonEmail = $game['email_inactivity'];
          sendInactivityEmail($jsonEmail, $game, $activePlayer);

          // summit email
          $jsonEmail = $game['email_summit_broadcast'];
          if ($player['id'] == $activePlayer['id']) {
            $jsonEmail = $game['email_summit'];
          }
          sendSummitEmail($jsonEmail, $game, $player, $activePlayer);
        }
      }
    }
  }
}
*/
exit;

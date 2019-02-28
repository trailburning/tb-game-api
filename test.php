<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('MR_SECURE_DOMAIN', 'https://mountainrush.co.uk/');

include "lib/tbLog.php";
include "lib/tbEmail.php";
include "lib/tbSocial.php";
include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";
include "lib/tbFundraising.php";
include "lib/tbHelper.php";

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

$id = $hashids->encode(126);
var_dump($id);

$id = $hashids->decode('0oLr2AnZzQ')[0];
var_dump($id);

//addLogToDB(LOG_OBJECT_GAME, LOG_ACTIVITY_CREATE, 2084);

//$strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/djJrblYlXV/challenge_ready_682x300.jpg';
//sendEmail('EDM - Mountain Rush', 'MR Test', 'mallbeury@mac.com', 'Matt', $strImage, 'Welcome', 'Player Activity', 'Your have progressed in the <a href="">challenge</a>.', '<a href="">change your preferences</a>');

// MR
$activePlayerID = 36;
$gameID = 2041;
//$gameID = 2114; // 2 player
$LatestActivity = 1626880620;

// CFYW
$activePlayerID = 164;
$gameID = 2036;
$LatestActivity = 1593291827;

// RaiseNow Test
$activePlayerID = 281;
$gameID = 5231;
$LatestActivity = 2160862798;

// custom email
function getCustomPlayersFromDB($clientID) {
  require_once 'lib/mysql.php';

  $db = mysqliSingleton::init();
  $strSQL = 'SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated FROM players WHERE clientID = ' . $clientID . ' and lastname = "Allbeury"';  

//  $strSQL = 'SELECT id, created, clientID, avatar, firstname, lastname, email, city, country, playerProviderID, last_activity, last_updated, gameplayers.fundraising_goal FROM players JOIN gameplayers ON gameplayers.player = players.id WHERE game_notifications = 1 and clientID = ' . $clientID . ' order by created asc';

  $result = $db->query($strSQL);
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

//  echo $arrEmail->message;
//return;
  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}
/*
$clientID = '2';
$jsonPlayerResponse = getCustomPlayersFromDB($clientID);
if (count($jsonPlayerResponse)) {
  $jsonEmail = '{"title": "WWF Climb For Your World", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/djJrblYlXV/challenge_activity_682x300.jpg?q=80", "message": "<p>Hi [PLAYER_FIRSTNAME],</p><p>We notice you recently started a <strong>WWF</strong> <a href=\"https://wwf.org.uk/climbforyourworld\">Climb For Your World</a> challenge, and as the developer of this challenge we\'re always looking for ways to improve the experience.</p><p>If you have a moment please take this <a href=\"https://www.surveymonkey.co.uk/r/CLIMB4YOURWORLD\">short survey</a> about your experience, and you could win a <strong>WWF goodie bag</strong>!</p><p>The Mountain Rush Team</p>", "preferences": ""}';

  foreach ($jsonPlayerResponse as $player) {
    echo 'p:' . $player['firstname'] . ' ' . $player['lastname'] . '<br/>';
    sendTestEmail('EDM - Mountain Rush', $jsonEmail, $player);
  }
}
*/

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

        if ($player['id'] == $activePlayer['id']) {
          // fundraising donation email
          $jsonEmail = $game['email_fundraising_donation'];

          $donation = array(
            'currency' => 'eur',
            'amount' => 10,
            'donor' => 'Hello World',
          );
//          sendFundraisingDonationEmail($jsonEmail, $game, $player, $donation);
        }

        if ($player['game_notifications']) {
          $activity = getPlayerActivity($activePlayer['playerProviderToken'], $LatestActivity);
          if ($activity) {
            // activity email
            $jsonEmail = $game['email_activity_broadcast'];
            if ($player['id'] == $activePlayer['id']) {
              $jsonEmail = $game['email_activity'];
            }
//            sendActivityEmail($jsonEmail, $game, $player, $activePlayer, $activity);
          }

          // welcome email
          $jsonEmail = $game['email_welcome'];
//          sendWelcomeEmail($jsonEmail, $game, $player);

          // completed email
          $jsonEmail = $game['email_finished'];
//          sendFinishedEmail($jsonEmail, $game, $player, $activePlayer);

          // inactivity email
          $jsonEmail = $game['email_inactivity'];
//          sendInactivityEmail($jsonEmail, $game, $activePlayer);

          // summit email
          $jsonEmail = $game['email_summit_broadcast'];
          if ($player['id'] == $activePlayer['id']) {
            $jsonEmail = $game['email_summit'];
          }
//          sendSummitEmail($jsonEmail, $game, $player, $activePlayer);
        }
      }
    }
  }
}

exit;

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

const DEBUG = false;
//const DEBUG = true;

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
//            sendActivityEmail($jsonEmail, $game, $player, $activePlayer, $activity);
          }

          // welcome email
          $jsonEmail = $game['email_welcome'];
          sendWelcomeEmail($jsonEmail, $game, $player);

          // inactivity email
          $jsonEmail = $game['email_inactivity'];
//          sendInactivityEmail($jsonEmail, $game, $activePlayer);

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

exit;

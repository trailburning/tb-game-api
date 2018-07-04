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

$id = $hashids->decode('djJrbPlYlX')[0];
var_dump($id);

//addLogToDB(LOG_OBJECT_GAME, LOG_ACTIVITY_CREATE, 2084);

//$strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/djJrblYlXV/challenge_ready_682x300.jpg';
//sendEmail('EDM - Mountain Rush', 'MR Test', 'mallbeury@mac.com', 'Matt', $strImage, 'Welcome', 'Player Activity', 'Your have progressed in the <a href="">challenge</a>.', '<a href="">change your preferences</a>');

$gameID = 2041;
$LatestActivity = 1626880620;

$jsonPlayerResponse = getGamePlayersFromDB($gameID);

$jsonGamesResponse = getGameFromDB($gameID);
if (count($jsonGamesResponse)) {
  foreach ($jsonGamesResponse as $game) {
    if (count($jsonPlayerResponse)) {
      // send invite
      $jsonInvitingPlayerResponse = getPlayerDetailsFromDB(67);
      foreach ($jsonInvitingPlayerResponse as $invitingPlayer) {
//        sendInviteEmail($game, $invitingPlayer, 'Matt', 'mallbeury@mac.com');
      }

      foreach ($jsonPlayerResponse as $player) {
        if ($player['game_notifications']) {
          $activePlayer = $player;

          $activity = getPlayerActivity($activePlayer['playerProviderToken'], $LatestActivity);
          if ($activity) {
            sendActivityEmail($game, $player, $activePlayer, $activity);
          }
//          sendWelcomeEmail($game, $player);
//          sendInactivityEmail($game, $activePlayer);
//          sendSummitEmail($game, $player, $activePlayer);
        }
      }
    }
  }
}

exit;

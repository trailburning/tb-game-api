<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

//sendEmail('Mountain Rush - EDM', '5875843c37d99829635908', 'MR Test', 'mallbeury@mac.com', 'Matt', 'Welcome', 'Player Activity', 'Your have progressed in the <a href="">challenge</a>.', '<a href="">change your preferences</a>');

//$gameID = 2041;
//$gameID = 2033;
/*
$jsonPlayerResponse = getGamePlayersFromDB($gameID);

$jsonGamesResponse = getGameFromDB($gameID);
if (count($jsonGamesResponse)) {
  foreach ($jsonGamesResponse as $game) {
    if (count($jsonPlayerResponse)) {
      foreach ($jsonPlayerResponse as $player) {
        if ($player['game_notifications']) {
          $activePlayer = $player;
          sendWelcomeEmail($game, $player);
          sendActivityEmail($game, $player, $activePlayer);
          sendSummitEmail($game, $player, $activePlayer);
        }
      }
    }
  }
}
*/

exit;

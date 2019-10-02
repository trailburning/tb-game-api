<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('MR_SECURE_DOMAIN', 'https://www.mountainrush.co.uk/');

include "lib/tbLog.php";
include "lib/tbEmail.php";
include "lib/tbSocial.php";
include "lib/tbCampaign.php";
include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";
include "lib/tbFundraising.php";
include "lib/tbHelper.php";

require_once('vendor/autoload.php');
require_once 'lib/mysqliSingleton.php';
require_once 'lib/mysql.php';

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

$db = connect_db();

$id = $hashids->encode(126);
var_dump($id);

$id = $hashids->decode('l6x4weZBDV')[0];
var_dump($id);

//addLogToDB(LOG_OBJECT_GAME, LOG_ACTIVITY_CREATE, 2084);

//$strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/djJrblYlXV/challenge_ready_682x300.jpg';
//sendEmail('EDM - Mountain Rush', 'MR Test', 'mallbeury@mac.com', 'Matt', $strImage, 'Welcome', 'Player Activity', 'Your have progressed in the <a href="">challenge</a>.', '<a href="">change your preferences</a>');

// MR
$activePlayerID = 36;
$gameID = 7701;
//$gameID = 2114; // 2 player
$LatestActivity = 2749451686;

// CFYW
//$activePlayerID = 164;
//$gameID = 2036;
//$LatestActivity = 1593291827;

// RaiseNow Test
//$activePlayerID = 281;
//$gameID = 5231;
//$LatestActivity = 2160862798;

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

// use UTC date
date_default_timezone_set("UTC");

//$epoch = 1567122299; // MLA old
$epoch = 1567521750; // MLA new
echo '<br/>';

$dtExpire = new DateTime("@$epoch");
echo 'expire : ' . $dtExpire->format('Y-m-d H:i:s') . '<br/>';
$tExpire = strtotime($dtExpire->format('Y-m-d H:i:s'));

$dtNow = new DateTime();
echo 'now : ' . $dtNow->format('Y-m-d H:i:s') . '<br/>';
$tNow = strtotime($dtNow->format('Y-m-d H:i:s'));

echo '<br/>';
echo $tExpire - $tNow;

// mla stop here
//exit;

$jsonPlayerResponse = getGamePlayersFromDB($gameID);

$jsonGamesResponse = getGameFromDB($gameID);
if (count($jsonGamesResponse)) {
  foreach ($jsonGamesResponse as $game) {
    $campaignID = $hashids->decode($game['campaignID'])[0];
    $jsonCampaignResponse = getCampaignFromDB($db, $campaignID);
    if (count($jsonCampaignResponse)) {
      $campaign = $jsonCampaignResponse[0];

      $jsonCampaignEmailsResponse = getCampaignEmailsFromDB($campaignID);
      if (count($jsonCampaignEmailsResponse)) {
        $campaignEmails = $jsonCampaignEmailsResponse[0];        

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
            $jsonEmail = $campaignEmails['email_invite'];
  //          sendInviteEmail($campaignEmails['email_template'], $jsonEmail, $game, $invitingPlayer, $player);
          }

          $activePlayer = null;
          foreach ($jsonPlayerResponse as $player) {
            $playerID = $hashids->decode($player['id'])[0];

            if ($playerID == $activePlayerID) {
              $activePlayer = $player;
            }

            if ($player['id'] == $activePlayer['id']) {
              // fundraising donation email
              $jsonEmail = $campaignEmails['email_fundraising_donation'];

              $donation = array(
                'currency' => 'eur',
                'amount' => 10,
                'donor' => 'Hello World',
              );
  //            sendFundraisingDonationEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $donation);
            }

            if ($player['game_notifications']) {
              $activity = getPlayerActivity($activePlayer['providerAccessToken'], $LatestActivity);
              if ($activity) {
                // activity email
                $jsonEmail = $campaignEmails['email_activity_broadcast'];
                // distance based challenge so use distance email template
                if ($game['distance'] > 0) {
                  $jsonEmail = $campaignEmails['email_activity_broadcast_distance'];
                }

                if ($player['id'] == $activePlayer['id']) {
                  $jsonEmail = $campaignEmails['email_activity'];
                  // distance based challenge so use distance email template
                  if ($game['distance'] > 0) {
                    $jsonEmail = $campaignEmails['email_activity_distance'];
                  }
                }

                sendActivityEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $activePlayer, $activity);
              }

              // welcome email
              $jsonEmail = $campaignEmails['email_welcome'];
              sendWelcomeEmail($campaignEmails['email_template'], $jsonEmail, $game, $player);

              // completed email
              $jsonEmail = $campaignEmails['email_finished'];
  //            sendFinishedEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $activePlayer);

              // inactivity email
              $jsonEmail = $campaignEmails['email_inactivity'];
  //            sendInactivityEmail($campaignEmails['email_template'], $jsonEmail, $game, $activePlayer);

              // summit email
              $jsonEmail = $campaignEmails['email_summit_broadcast'];
              if ($player['id'] == $activePlayer['id']) {
                $jsonEmail = $campaignEmails['email_summit'];
              }
  //            sendSummitEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $activePlayer);
            }
          }
        }
      }
    }
  }
}

exit;

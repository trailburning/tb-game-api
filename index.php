<?php
error_reporting(E_ERROR);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

define('MR_DOMAIN', 'http://mountainrush.co.uk/');
define('MR_SECURE_DOMAIN', 'https://mountainrush.co.uk/');
define('GAME_API_DOMAIN', 'https://tb-game-api.herokuapp.com/');

define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include "lib/tbLog.php";
include "lib/tbEmail.php";
include "lib/tbSocial.php";
include "lib/tbCampaign.php";
include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";
include "lib/tbWorker.php";
include "lib/tbFundraising.php";
include "lib/tbKPI.php";
include "lib/tbHelper.php";

require 'vendor/autoload.php';
require_once 'lib/mysqliSingleton.php';
require_once 'lib/mysql.php';

$app = new \Slim\App;

const DEBUG = false;
//const DEBUG = true;

const GAME_PLAYER_PLAYING_STATE = 0;
const GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE = 1;
const GAME_PLAYER_SUMMITED_STATE = 100;

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

$app->get('/', function (Request $request, Response $response) {
  echo 'Trailburning® Platform GAME API<br/>';

// mla test
//    $lastInsertID = $db->insert_id();
});

$app->get('/worker', function (Request $request, Response $response) {
  // process game activity
  $jsonActivity = processActivity();

  // don't present full json as it's very large, just create something small!
  $jsonResponse = array('active_games' => count($jsonActivity));

  if (!DEBUG) {
    return $response->withJSON($jsonResponse);
  }
});

$app->get('/strava/subscribe', function (Request $request, Response $response) {
  $url = 'https://api.strava.com/api/v3/push_subscriptions';

  $fields = array(
    'client_id' => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'object_type' => 'activity',
    'aspect_type' => 'create',
    'callback_url' => GAME_API_DOMAIN . 'strava/callback',
    'verify_token' => 'STRAVA'
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch,CURLOPT_POST, count($fields));
  curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($fields));
  curl_exec($ch);
  curl_close($ch);
});

$app->get('/strava/unsubscribe/{ID}', function (Request $request, Response $response) {
  $url = 'https://api.strava.com/api/v3/push_subscriptions/' . $request->getAttribute('ID') . '?client_id=' . CLIENT_ID . '&client_secret=' . CLIENT_SECRET;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  curl_exec($ch);
  curl_close($ch);
});

$app->get('/strava/getsubscriptions', function (Request $request, Response $response) {
  $url = 'https://api.strava.com/api/v3/push_subscriptions?client_id=' . CLIENT_ID . '&client_secret=' . CLIENT_SECRET;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_exec($ch);
  curl_close($ch);
});

$app->get('/strava/callback', function (Request $request, Response $response) {
  // STRAVA GET to verify callback endpoint
  $allGetVars = $request->getQueryParams();

  if ($allGetVars['hub_verify_token'] == 'STRAVA') {
    header("HTTP/1.1 200 OK");
    $data = array('hub.challenge' => $allGetVars['hub_challenge']);

    return $response->withJSON($data);
  }
});

$app->post('/strava/callback', function (Request $request, Response $response) {
  // STRAVA POST to send player activity
  // must return 200 to STRAVA 'within 2 seconds' to prevent being called again, so don't hang about!
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $db = connect_db();

  switch ($data['object_type']) {
    case 'athlete':
      if ($data['updates']['authorized'] == 'false') {
        $jsonPlayerResponse = getPlayerFromDBByProviderID($data['owner_id']);
        if (count($jsonPlayerResponse)) {
          foreach ($jsonPlayerResponse as $player) {
            addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_ACTIVITY_DELETE, $player['id']);
          }
        }
        // now blank player (or players)
        updatePlayerBlankDetails($data['owner_id']);
      }
      break;

    case 'activity':
      // only want new activities
      if ($data['aspect_type'] == 'create') {
        // look for users (in all campaigns)
        $jsonPlayerResponse = getPlayerFromDBByProviderID($data['owner_id']);
        if (count($jsonPlayerResponse)) {
          foreach ($jsonPlayerResponse as $player) {
            $playerID = $player['id'];
            // look for games
            $jsonGamesResponse = getGamesByPlayerFromDB($playerID);
            if (count($jsonGamesResponse)) {
              // look for active game
              foreach ($jsonGamesResponse as $game) {
                if ($game['game_state'] == STATE_GAME_ACTIVE) {
                  $gameID = $hashids->decode($game['game'])[0];
                  // ensure player has not already completed the game
                  $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
                  if (count($gamePlayerResults)) {
                    if (is_null($gamePlayerResults[0]['ascentCompleted'])) {
                      // update that player has logged an activity
                      setPlayerGameActivityInDB($gameID, $playerID, $data['object_id']);
                    }
                  }
                }
              }
            }
          }
        }
      }
      break;
  }

  header("HTTP/1.1 200 OK");

  return;
});

$app->get('/game/{gameHashID}/socialimage', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');

  echo generateGameSocialImage($hashids->decode($hashGameID)[0]);
});

$app->get('/game/{gameHashID}/socialimage/progress/{progress}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');

  $strProgress = '£ ' . $request->getAttribute('progress');
  echo generateGameProgressSocialImage($hashids->decode($hashGameID)[0], $strProgress);
});

$app->get('/game/{gameHashID}/socialimage/goal/{goal}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');

  $strGoal = '£ ' . $request->getAttribute('goal');
  echo generateGameGoalSocialImage($hashids->decode($hashGameID)[0], $strGoal);
});

$app->get('/game/{gameHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = getGameFromDB($gameID);

  // use UTC date
  date_default_timezone_set("UTC");

  $dtNow = date('Y-m-d\TH:i:s.000\Z', time());
  $jsonResponse[0]['game_now'] = $dtNow;

  // format dates as UTC
  $dtStartDate = new DateTime($jsonResponse[0]['game_start']);
  $jsonResponse[0]['game_start'] = $dtStartDate->format('Y-m-d\TH:i:s.000\Z');
  $dtEndDate = new DateTime($jsonResponse[0]['game_end']);
  $jsonResponse[0]['game_end'] = $dtEndDate->format('Y-m-d\TH:i:s.000\Z');

  // add player data
  $jsonResponse[0]['players'] = getGamePlayersFromDB($gameID);

  $gameJSON = $response->withJSON($jsonResponse);

  return $gameJSON;
});

$app->get('/game/{gameHashID}/campaign', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = getCampaignByGameFromDB($gameID);

  $gameJSON = $response->withJSON($jsonResponse);

  return $gameJSON;
});

$app->post('/game', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true);

  $campaignID = $hashids->decode($data['campaignID'])[0];
  $ownerPlayerID = $hashids->decode($data['ownerPlayerID'])[0];
  $levelID = $hashids->decode($data['levelID'])[0];

  $db = connect_db();

  // do we want to calc the end date=
  if (isset($data['gameDaysDuration'])) {
    $dtStartDate = new DateTime($data['gameStart']);
    $dtEndDate = new DateTime($dtStartDate->format('Y-m-d\TH:i:s.000\Z') . '+' . $data['gameDaysDuration'] . ' day');
    $data['gameEnd'] = $dtEndDate->format('Y-m-d H:i:s');
  }

  $jsonResponse = addGameToDB($campaignID, $ownerPlayerID, $data['season'], $data['type'], $data['gameStart'], $data['gameEnd'], $levelID);
  $gameID = $hashids->decode($jsonResponse[0]['id'])[0];
  addLogToDB($db, LOG_OBJECT_GAME, LOG_ACTIVITY_CREATE, $gameID);

  return $response->withJSON($jsonResponse);
});

$app->post('/game/{gameHashID}/player/{playerHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];
  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonPlayerResponse = addPlayerGameInDB($gameID, $playerID);

  // now ping added player and say hi!
  $jsonGamesResponse = getGameFromDB($gameID);
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      if (count($jsonPlayerResponse)) {
        foreach ($jsonPlayerResponse as $player) {
          if ($player['game_notifications']) {
            $jsonEmail = $game['email_welcome'];
            sendWelcomeEmail($jsonEmail, $game, $player);
          }
        }
      }
    }
  }

  return $response->withJSON($jsonPlayerResponse);
});

$app->post('/game/{gameHashID}/player/{playerHashID}/marker', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $json = $request->getBody();
  $data = json_decode($json, true); 

  setPlayerGameLatestMarkerInDB($hashids->decode($hashGameID)[0], $hashids->decode($hashPlayerID)[0], $data['markerID']);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->post('/game/{gameHashID}/invite', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $db = connect_db();

  // get game
  $jsonGamesResponse = getGameFromDB($gameID);
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      $invitingPlayerID = $hashids->decode($game['ownerPlayerID'])[0];

      addGameInviteToDB($gameID, $data['email']);
      // get inviting player
      $jsonInvitingPlayerResponse = getPlayerDetailsFromDB($invitingPlayerID);
      foreach ($jsonInvitingPlayerResponse as $invitingPlayer) {
        // send invite
        $player = array();
        $player['id'] = 0;
        $player['firstname'] = $data['name'];
        $player['lastname'] = '';
        $player['email'] = $data['email'];

        $jsonEmail = $game['email_invite'];
        sendInviteEmail($jsonEmail, $game, $invitingPlayer, $player);        
        addLogToDB($db, LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_SENT, $invitingPlayerID);
      }
    }
  }

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->post('/game/{gameHashID}/player/{playerHashID}/invite/{inviteHashID}/accept', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];
  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];
  $hashInviteID = $request->getAttribute('inviteHashID');
  $inviteID = $hashids->decode($hashInviteID)[0];

  $db = connect_db();

  $jsonPlayerResponse = addPlayerGameInDB($gameID, $playerID);
  removePlayerGameInvitationFromDB($inviteID);
  addLogToDB($db, LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_ACCEPT, $playerID);

  // now ping added player and say hi!
  $jsonGamesResponse = getGameFromDB($gameID);
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      if (count($jsonPlayerResponse)) {
        foreach ($jsonPlayerResponse as $player) {
          if ($player['game_notifications']) {
            $jsonEmail = $game['email_welcome'];
            sendWelcomeEmail($jsonEmail, $game, $player);
          }
        }
      }
    }
  }

  return $response->withJSON($jsonPlayerResponse);
});

$app->post('/game/{gameHashID}/player/{playerHashID}/invite/{inviteHashID}/reject', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];
  $hashInviteID = $request->getAttribute('inviteHashID');
  $inviteID = $hashids->decode($hashInviteID)[0];

  $db = connect_db();

  removePlayerGameInvitationFromDB($inviteID);
  addLogToDB($db, LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_REJECT, $playerID);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->get('/client/{clientHashID}/player/{token}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashClientID = $request->getAttribute('clientHashID');
  $clientID = $hashids->decode($hashClientID)[0];

  $token = $request->getAttribute('token');
  $jsonResponse = getPlayer($clientID, $token);
  if (count($jsonResponse)) {

    // add inviation data
    $jsonResponse[0]['invitations'] = getPlayerGameInvitationsFromDB($jsonResponse[0]['id']);

    // add game data
    $jsonResponse[0]['games'] = getGamesByPlayerFromDB($jsonResponse[0]['id']);

    $jsonResponse[0]['id'] = $hashids->encode($jsonResponse[0]['id']);
    $jsonResponse[0]['clientID'] = $hashids->encode($jsonResponse[0]['clientID']);

    return $response->withJSON($jsonResponse);
  }
});

$app->get('/campaign/{campaignHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $db = connect_db();

  if ($campaignID) {
    $jsonResponse = getCampaignFromDB($db, $campaignID);
    if (count($jsonResponse)) {
      $jsonResponse[0]['fundraising_currency_symbol'] = getCurrencySymbol($jsonResponse[0]['fundraising_currency']);
    }
    return $response->withJSON($jsonResponse);
  }

  return null;
});

$app->get('/campaign/{campaignHashID}/games', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonResponse = getGamesBcCampaignFromDB($campaignID);

  return $response->withJSON($jsonResponse);
});

$app->post('/campaign/{campaignHashID}/checkinvite', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true);

  $jsonResponse = getCampaignCodeFromDB($campaignID, $data['code']);

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/players/{match}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonResponse = getPlayersFromDBByCampaign($campaignID, $request->getAttribute('match'));

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/gamelevels', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = getCampaignGameLevelsFromDB($campaignID);

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/gameoptions', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = array();

  $jsonResponse['types'] = getCampaignGameActivityTypesFromDB($campaignID);
  $jsonResponse['durations'] = getCampaignGameDurationsFromDB($campaignID);

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/summary', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = getCampaignSummaryFromDB($campaignID);
  if (count($jsonResponse)) {
    $jsonResponse[0]['fundraising_currency_symbol'] = getCurrencySymbol($jsonResponse[0]['fundraising_currency']);
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/player', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true);

  $hashClientID = $data['clientID'];
  $clientID = $hashids->decode($hashClientID)[0];

  $jsonResponse = addPlayerToDB($clientID, $data['avatar'], $data['firstname'], $data['lastname'], $data['email'], $data['city'], $data['country'], $data['providerID'], $data['providerToken']);

  $jsonResponse[0]['id'] = $hashids->encode($jsonResponse[0]['id']);
  $jsonResponse[0]['clientID'] = $hashids->encode($jsonResponse[0]['clientID']);

  return $response->withJSON($jsonResponse);
});

$app->post('/player/{playerHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashPlayerID = $request->getAttribute('playerHashID');

  $json = $request->getBody();
  $data = json_decode($json, true);

  $bReceiveEmail = 0;
  if ($data['receiveEmail']) {
    $bReceiveEmail = 1;
  }

  $jsonResponse = updatePlayerPreferencesInDB($hashids->decode($hashPlayerID)[0], $bReceiveEmail);

  return $response->withJSON($jsonResponse);
});

$app->get('/client/{clientHashID}/player/{token}/update', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashClientID = $request->getAttribute('clientHashID');
  $clientID = $hashids->decode($hashClientID)[0];
  
  $token = $request->getAttribute('token');

  $jsonResponse = updatePlayer($clientID, $token);
  $jsonResponse = getPlayer($clientID, $token);

  // add game data
  $jsonResponse[0]['games'] = getGamesByPlayerFromDB($jsonResponse[0]['id']);

  $hashID = $hashids->encode($jsonResponse[0]['id']);
  $jsonResponse[0]['id'] = $hashID;

  return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameHashID}/player/{playerHashID}/progress', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  // get latest activities
  $arrPlayerActivities = getPlayerGameProgress($playerID, $gameID);

  // get player game details
  $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
  if (count($gamePlayerResults)) {
    $gamePlayerResults[0]['fundraising_currency_symbol'] = getCurrencySymbol($gamePlayerResults[0]['fundraising_currency']);
  }

  $jsonResponse = $gamePlayerResults;

  $jsonResponse[0]['activities'] = $arrPlayerActivities;

  return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameHashID}/player/{playerHashID}/activity/{activityID}/photos', function (Request $request, Response $response) {
    $hashids = new Hashids\Hashids('mountainrush', 10);

    $hashGameID = $request->getAttribute('gameHashID');
    $gameID = $hashids->decode($hashGameID)[0];
  
    $hashPlayerID = $request->getAttribute('playerHashID');
    $playerID = $hashids->decode($hashPlayerID)[0];

    $activityID = $request->getAttribute('activityID');
    $jsonResponse = getGamePlayerActivityPhotos($gameID, $playerID, $activityID);
    // do we have media?
    if (count($jsonResponse)) {
      // store that we have media
      setPlayerGameMediaCaptureInDB($gameID, $playerID);
    }
    return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameHashID}/player/{playerHashID}/activity/{activityID}/comments', function (Request $request, Response $response) {
    $hashids = new Hashids\Hashids('mountainrush', 10);

    $hashGameID = $request->getAttribute('gameHashID');
    $gameID = $hashids->decode($hashGameID)[0];
  
    $hashPlayerID = $request->getAttribute('playerHashID');
    $playerID = $hashids->decode($hashPlayerID)[0];

    $activityID = $request->getAttribute('activityID');
    $jsonResponse = getGamePlayerActivityComments($gameID, $playerID, $activityID);

    return $response->withJSON($jsonResponse);
});

/* **************************************************************************** */
/* Start Support RaiseNow */
/* **************************************************************************** */
$app->post('/fundraiser/campaign/{campaignHashID}/game/{gameHashID}/player/{playerHashID}/details', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $campaignID = $hashids->decode($hashCampaignID)[0];
  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $db = connect_db();

  // get campaign
  $jsonCampaign = getCampaignFromDB($db, $campaignID);
  if (count($jsonCampaign)) {
    // get player game details
    $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
    if (count($gamePlayerResults)) {
      // store fundraising details
      setPlayerGameFundraisingDetailsInDB($gameID, $playerID, $data['targetAmount'], 0, $data['currencyCode'], $data['charityOptIn']);

      $jsonResponse = null;

      return $response->withJSON($jsonResponse);
    }
    else {
      $jsonResponse = array('error' => array('id' => 'UserDoesNotExist'));

      return $response->withJSON($jsonResponse);
    }
  }
});

$app->get('/game/{gameHashID}/player/{playerHashID}/fundraiser/details', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = getFundraisingDetails($hashGameID, $hashPlayerID);

  // update fundraising info in DB
  if ($jsonResponse) {
    setPlayerGameFundraisingTotalsInDB($gameID, $playerID, $jsonResponse->totalRaisedOnline);

    $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
    if (count($gamePlayerResults)) {
      $jsonResponse->fundraisingTarget = $gamePlayerResults[0]['fundraising_goal'];
      $jsonResponse->currencyCode = $gamePlayerResults[0]['fundraising_currency'];
      $jsonResponse->currencySymbol = getCurrencySymbol($gamePlayerResults[0]['fundraising_currency']);
    }
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameHashID}/player/{playerHashID}/fundraiser/donations', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = getFundraisingDonations($hashGameID, $hashPlayerID);

  return $response->withJSON($jsonResponse);
});
/* **************************************************************************** */
/* End Support RaiseNow */
/* **************************************************************************** */

/* **************************************************************************** */
/* Start Support JustGiving */
/* **************************************************************************** */
$app->get('/fundraiser/player/{playerHashID}/user/{email}/{password}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashPlayerID = $request->getAttribute('playerHashID');

  $playerID = $hashids->decode($hashPlayerID)[0];

  $bExists = false;

  $jsonResponse = array();

  $db = connect_db();

  $jsonPlayerResponse = getFundraisingPlayer($request->getAttribute('email'), $request->getAttribute('password'));
  if ($jsonPlayerResponse) {
    $jsonResponse = array('exists' => $jsonPlayerResponse->isValid);
    addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_FUNDRAISING_USER_QUERY_SUCCESS, $playerID);
  }
  else {
    addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_FUNDRAISING_USER_QUERY_FAIL, $playerID);
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/fundraiser/player/{playerHashID}/user', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashPlayerID = $request->getAttribute('playerHashID');

  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $paramaObj = (object) [
    'email' => $data['email'],
    'password' => $data['password'],
    'firstname' => $data['firstname'],
    'lastname' => $data['lastname'],
    'title' => $data['title'],
    'addressline1' => $data['addressline1'],
    'addressline2' => $data['addressline2'],
    'town' => $data['town'],
    'state' => $data['state'],
    'postcode' => $data['postcode'],
    'country' => $data['country'],
    'acceptTerms' => $data['acceptTerms']
  ];
  $jsonResponse = createFundraisingPlayer($paramaObj);

  return $response->withJSON($jsonResponse);
});

$app->post('/fundraiser/player/{playerHashID}/user/lite', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashPlayerID = $request->getAttribute('playerHashID');

  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $db = connect_db();

  $paramaObj = (object) [
    'email' => $data['email'],
    'password' => $data['password'],
    'firstname' => $data['firstname'],
    'lastname' => $data['lastname'],
    'acceptTerms' => $data['acceptTerms']
  ];
  $jsonResponse = createFundraisingPlayerLite($paramaObj);
  if ($jsonResponse) {
    addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_FUNDRAISING_USER_CREATE_SUCCESS, $playerID);
  }
  else {
    addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_FUNDRAISING_USER_CREATE_FAIL, $playerID);
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/fundraiser/campaign/{campaignHashID}/game/{gameHashID}/player/{playerHashID}/page', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $campaignID = $hashids->decode($hashCampaignID)[0];
  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $db = connect_db();

  // get campaign
  $jsonCampaign = getCampaignFromDB($db, $campaignID);
  if (count($jsonCampaign)) {
    // get player game details
    $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
    if (count($gamePlayerResults)) {
      $fundraisingPage = $jsonCampaign[0]['shortname'] . '-' . $hashPlayerID . $hashGameID;
      $fundraisingPageTitle = $jsonCampaign[0]['name'];

      // now create on JG
      $paramaObj = (object) [
        'email' => $data['email'],
        'password' => $data['password'],
        'pageShortName' => $fundraisingPage,
        'pageTitle' => $fundraisingPageTitle,
        'eventName' => '',
        'charityID' => $jsonCampaign[0]['fundraising_charity'],
        'eventID' => $jsonCampaign[0]['fundraising_event'],
        'targetAmount' => $data['targetAmount'],
        'justGivingOptIn' => $data['justGivingOptIn'],
        'charityOptIn' => $data['charityOptIn'],
        'imageURL' => "http://tbassets2.imgix.net/images/brands/mountainrush/social/wwf/CFYW_Gorilla_JustGiving2.png"
      ];
      $jsonResponse = createFundraisingPlayerPage($paramaObj);

      if ($jsonResponse) {
        if ($jsonResponse->pageId) {
          $jsonResponse->fundraising_page = $fundraisingPage;
          // store fundraising page
          setPlayerGameFundraisingPageInDB($gameID, $playerID, $jsonResponse->pageId, $fundraisingPage, $data['targetAmount'], 'GBP');
        }
      }

      return $response->withJSON($jsonResponse);
    }
    else {
      $jsonResponse = array('error' => array('id' => 'UserDoesNotExist'));

      return $response->withJSON($jsonResponse);
    }
  }
});

$app->get('/game/{gameHashID}/player/{playerHashID}/fundraiser/page/{pageShortName}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = getFundraisingPage($request->getAttribute('pageShortName'));

  // update fundraising info in DB
  if ($jsonResponse) {
    // always set CharityOptIn to false as JustGiving maintain this internally
    setPlayerGameFundraisingDetailsInDB($gameID, $playerID, $jsonResponse->fundraisingTarget, $jsonResponse->totalRaisedOnline, $jsonResponse->currencyCode, 0);
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/fundraiser/page/{pageShortName}/donations', function (Request $request, Response $response) {
  $jsonResponse = getFundraisingPageDonations($request->getAttribute('pageShortName'));

  return $response->withJSON($jsonResponse);
});
/* **************************************************************************** */
/* End Support JustGiving */
/* **************************************************************************** */

$app->get('/fundraiser/campaign/{campaignHashID}/leaderboard/{numPlayers}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = getFundraisingCampaignLeaderboard($campaignID, $request->getAttribute('numPlayers'));

  return $response->withJSON($jsonResponse);
});

$app->get('/fundraiser/leaderboard/event/{eventID}', function (Request $request, Response $response) {
  $jsonResponse = getFundraisingEventLeaderboard($request->getAttribute('eventID'));

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/kpi/climbers', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = array();

  $jsonClimbersResponse = getCampaignKPITotalClimbersFromDB($campaignID);
  $jsonResponse[0]['key'] = 'Climbers';
  $jsonResponse[0]['value'] = $jsonClimbersResponse[0]['total'];

  $jsonFundraisingClimbersResponse = getCampaignKPITotalFundraisingClimbersFromDB($campaignID);
  $jsonResponse[1]['key'] = 'Fundraising Climbers';
  $jsonResponse[1]['value'] = $jsonFundraisingClimbersResponse[0]['total'];

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/kpi/fundraising', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = array();

  $jsonGoalResponse = getCampaignKPITotalFundraisingGoalFromDB($campaignID);
  if (count($jsonGoalResponse)) {
    $jsonResponse[0]['key'] = 'Goal';
    $jsonResponse[0]['value'] = ($jsonGoalResponse[0]['total'] == null) ? 0 : $jsonGoalResponse[0]['total'];
  }

  $jsonFundraisingRaisedResponse = getCampaignKPITotalFundraisingRaisedFromDB($campaignID);
  if (count($jsonFundraisingRaisedResponse)) {
    $jsonResponse[1]['key'] = 'Raised';
    $jsonResponse[1]['value'] = ($jsonFundraisingRaisedResponse[0]['total'] == null) ? 0 : $jsonFundraisingRaisedResponse[0]['total'];
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/kpi/games', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = array();  

  $jsonGameResponse = getCampaignKPITotalActiveGamesFromDB($campaignID);
  $jsonResponse[0]['key'] = 'Active';
  $jsonResponse[0]['value'] = $jsonGameResponse[0]['total'];

  $jsonGameResponse = getCampaignKPITotalPendingGamesFromDB($campaignID);
  $jsonResponse[1]['key'] = 'Pending';
  $jsonResponse[1]['value'] = $jsonGameResponse[0]['total'];

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/kpi/activities', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = array();  

  $jsonActiviesResponse = getCampaignKPITotalActivitiesFromDB($campaignID);

  foreach ($jsonActiviesResponse as $activity) {
    $index = sizeof($jsonResponse);

    $jsonResponse[$index]['key'] = $activity['type'];
    $jsonResponse[$index]['value'] = $activity['total'];
  }

  return $response->withJSON($jsonResponse);
});

$app->run();

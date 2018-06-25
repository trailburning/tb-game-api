<?php
error_reporting(E_ERROR);
//error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

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
include "lib/tbHelper.php";

require 'vendor/autoload.php';

$app = new \Slim\App;

define('MR_DOMAIN', 'http://mountainrush.co.uk/');
//define('MR_DOMAIN', 'http://mountainrush.trailburning.com/');
define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

const DEBUG = false;
//const DEBUG = true;

const GAME_PLAYER_PLAYING_STATE = 0;
const GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE = 1;
const GAME_PLAYER_SUMMITED_STATE = 100;

$app->get('/worker', function (Request $request, Response $response) {
  // process game activity
  processActivity();
});

$app->get('/strava/subscribe', function (Request $request, Response $response) {
  $url = 'https://api.strava.com/api/v3/push_subscriptions';

  $fields = array(
    'client_id' => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'object_type' => 'activity',
    'aspect_type' => 'create',
    'callback_url' => MR_DOMAIN . 'tb-game-api/strava/callback',
    'verify_token' => 'STRAVA'
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch,CURLOPT_POST, count($fields));
  curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($fields));
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

  switch ($data['object_type']) {
    case 'athlete':
      if ($data['updates']['authorized'] == 'false') {
        $jsonPlayerResponse = getPlayerFromDBByProviderID($data['owner_id']);
        if (count($jsonPlayerResponse)) {
          foreach ($jsonPlayerResponse as $player) {
            addLogToDB(LOG_OBJECT_PLAYER_PROVIDER, LOG_ACTIVITY_DELETE, $player['id']);
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

  // do we want to calc the end date=
  if (isset($data['gameDaysDuration'])) {
    $dtStartDate = new DateTime($data['gameStart']);
    $dtEndDate = new DateTime($dtStartDate->format('Y-m-d\TH:i:s.000\Z') . '+' . $data['gameDaysDuration'] . ' day');
    $data['gameEnd'] = $dtEndDate->format('Y-m-d\TH:i:s.000\Z');
  }

  $jsonResponse = addGameToDB($campaignID, $ownerPlayerID, $data['season'], $data['type'], $data['gameStart'], $data['gameEnd'], $levelID);
  $gameID = $hashids->decode($jsonResponse[0]['id'])[0];
  addLogToDB(LOG_OBJECT_GAME, LOG_ACTIVITY_CREATE, $gameID);

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
            sendWelcomeEmail($game, $player);
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

  return $response->withJSON();
});

$app->post('/game/{gameHashID}/invite', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

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
        sendInviteEmail($game, $invitingPlayer, $data['name'], $data['email']);
        addLogToDB(LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_SENT, $invitingPlayerID);
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

  $jsonPlayerResponse = addPlayerGameInDB($gameID, $playerID);
  removePlayerGameInvitationFromDB($inviteID);
  addLogToDB(LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_ACCEPT, $playerID);

  // now ping added player and say hi!
  $jsonGamesResponse = getGameFromDB($gameID);
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      if (count($jsonPlayerResponse)) {
        foreach ($jsonPlayerResponse as $player) {
          if ($player['game_notifications']) {
            sendWelcomeEmail($game, $player);
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

  removePlayerGameInvitationFromDB($inviteID);
  addLogToDB(LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_REJECT, $playerID);

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

  $jsonResponse = getCampaignFromDB($campaignID);

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/games', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonResponse = getGamesBcCampaignFromDB($campaignID);

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

$app->get('/campaign/{campaignHashID}/summary', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = getCampaignSummaryFromDB($campaignID);

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

$app->get('/fundraiser/user/{email}/{password}', function (Request $request, Response $response) {
  $bExists = false;

  if (getFundraisingPlayer($request->getAttribute('email'), $request->getAttribute('password'))) {
    $bExists = true;
  }

  $jsonResponse = array('exists' => $bExists);

  return $response->withJSON($jsonResponse);
});

$app->post('/fundraiser/user', function (Request $request, Response $response) {
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

$app->post('/fundraiser/user/lite', function (Request $request, Response $response) {
  $json = $request->getBody();
  $data = json_decode($json, true); 

  $paramaObj = (object) [
    'email' => $data['email'],
    'password' => $data['password'],
    'firstname' => $data['firstname'],
    'lastname' => $data['lastname'],
    'acceptTerms' => $data['acceptTerms']
  ];
  $jsonResponse = createFundraisingPlayerLite($paramaObj);

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

  // get campaign
  $jsonCampaign = getCampaignFromDB($campaignID);
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
        'imageURL' => "http://tbassets2.imgix.net/images/brands/mountainrush/social/wwf/TB_Gorilla_justgiving_image.png"
      ];
      $jsonResponse = createFundraisingPlayerPage($paramaObj);

      if ($jsonResponse) {
        if ($jsonResponse->pageId) {
          $jsonResponse->fundraising_page = $fundraisingPage;
          // store fundraising page
          setPlayerGameFundraisingPageInDB($gameID, $playerID, $jsonResponse->pageId, $fundraisingPage, $data['targetAmount']);
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
    setPlayerGameFundraisingDetailsInDB($gameID, $playerID, $jsonResponse->fundraisingTarget, $jsonResponse->totalRaisedOnline, $jsonResponse->currencyCode);
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/fundraiser/page/{pageShortName}/donations', function (Request $request, Response $response) {
  $jsonResponse = getFundraisingPageDonations($request->getAttribute('pageShortName'));

  return $response->withJSON($jsonResponse);
});

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

$app->run();

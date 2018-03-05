<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

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

define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

const GAME_PLAYER_PLAYING_STATE = 0;
const GAME_PLAYER_SUMMITED_STATE = 1;

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
    'callback_url' => 'http://mountainrush.trailburning.com/tb-game-api/strava/callback',
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

  // only want new activities
  if ($data['object_type'] == 'activity' && $data['aspect_type'] == 'create') {
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

  header("HTTP/1.1 200 OK");

  return;
});

$app->get('/game/{gameHashID}/socialimage', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');

  echo generateGameSocialImage($hashids->decode($hashGameID)[0]);
});

$app->get('/game/{gameHashID}/socialimage/progress/{progressHashPercent}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $progressHashPercent = $request->getAttribute('progressHashPercent');

  echo generateGameProgressSocialImage($hashids->decode($hashGameID)[0], $hashids->decode($progressHashPercent)[0]);
});

$app->get('/game/{gameHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = getGameFromDB($gameID);
  $jsonResponse[0]['id'] = $hashGameID;

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

$app->post('/game', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true);

  $hashCampaignID = $data['campaignID'];
  $campaignID = $hashids->decode($hashCampaignID)[0];

  // do we want to calc the end date=
  if (isset($data['gameDaysDuration'])) {
    $dtStartDate = new DateTime($data['gameStart']);
    $dtEndDate = new DateTime($dtStartDate->format('Y-m-d\TH:i:s.000\Z') . '+' . $data['gameDaysDuration'] . ' day');
    $data['gameEnd'] = $dtEndDate->format('Y-m-d\TH:i:s.000\Z');
  }

  $jsonResponse = addGameToDB($campaignID, $data['season'], $data['type'], $data['gameStart'], $data['gameEnd'], $data['levelID']);

  return $response->withJSON($jsonResponse);
});

$app->post('/game/{gameHashID}/player/{playerHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];
  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = addPlayerGameInDB($gameID, $playerID);

  return $response->withJSON($jsonResponse);
});

$app->get('/client/{clientHashID}/player/{token}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashClientID = $request->getAttribute('clientHashID');
  $clientID = $hashids->decode($hashClientID)[0];

  $token = $request->getAttribute('token');
  $jsonResponse = getPlayer($clientID, $token);
  if (count($jsonResponse)) {
    // add game data
    $jsonResponse[0]['games'] = getGamesByPlayerFromDB($jsonResponse[0]['id']);
    $jsonResponse[0]['id'] = $hashids->encode($jsonResponse[0]['id']);
    $jsonResponse[0]['clientID'] = $hashids->encode($jsonResponse[0]['clientID']);

    return $response->withJSON($jsonResponse);
  }
});

$app->get('/campaign/{campaignHashID}/players/{match}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonResponse = getPlayersFromDBByCampaign($campaignID, $request->getAttribute('match'));

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
        'imageURL' => "http://tbassets2.imgix.net/images/brands/mountainrush/edm/5875843c37d99829635908_682x274.jpg"
      ];
      $jsonResponse = createFundraisingPlayerPage($paramaObj);

      if ($jsonResponse) {
        if ($jsonResponse->pageId) {
          // store fundraising page
          setPlayerGameFundraisingPageInDB($gameID, $playerID, $jsonResponse->pageId, $fundraisingPage);
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

$app->get('/fundraiser/page/{pageShortName}', function (Request $request, Response $response) {
  $jsonResponse = getFundraisingPage($request->getAttribute('pageShortName'));

  return $response->withJSON($jsonResponse);
});

$app->get('/fundraiser/page/{pageShortName}/donations', function (Request $request, Response $response) {
  $jsonResponse = getFundraisingPageDonations($request->getAttribute('pageShortName'));

  return $response->withJSON($jsonResponse);
});

$app->get('/fundraiser/leaderboard/event/{eventID}', function (Request $request, Response $response) {
  $jsonResponse = getFundraisingEventLeaderboard($request->getAttribute('eventID'));

  return $response->withJSON($jsonResponse);
});

$app->run();

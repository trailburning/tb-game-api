<?php
error_reporting(E_ERROR);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Credentials: true");
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Origin, Content-Type, X-Auth-Token , Authorization');
header('Content-Type: application/json');

define('MR_SECURE_DOMAIN', 'https://www.mountainrush.co.uk/');
define('GAME_API_DOMAIN', 'https://tb-game-api.herokuapp.com/');

//define('PROVIDER_SERVER_CAUSE_CODE', 'amp-v6a6sz'); // test
define('PROVIDER_SERVER_CAUSE_CODE', 'world-1ba4'); // LIVE WBR

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require 'vendor/autoload.php';
require_once 'lib/mysqliSingleton.php';
require_once 'lib/mysql.php';

include "lib/tbLog.php";
include "lib/tbStrava.php";
include "lib/tbMedia.php";
include "lib/tbRoutes.php";
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

$settings =  [
  'settings' => [
    'displayErrorDetails' => true
  ],
];

$app = new \Slim\App($settings);
//$app = new \Slim\App;

unset($app->getContainer()['errorHandler']);
unset($app->getContainer()['phpErrorHandler']);

const DEBUG = false;
//const DEBUG = true;

const GAME_PLAYER_PLAYING_STATE = 0;
const GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE = 1;
const GAME_PLAYER_COMPLETED_CHALLENGE_STATE = 100;

$GLOBALS['db_server'] = 'localhost';
$GLOBALS['db_user'] = 'root';
$GLOBALS['db_pass'] = 'root';
$GLOBALS['db_name'] = 'tb_game';

if (getenv('CLEARDB_DATABASE_URL')) {
  $url = parse_url(getenv('CLEARDB_DATABASE_URL'));

  $GLOBALS['db_server'] = $url["host"];
  $GLOBALS['db_user'] = $url["user"];
  $GLOBALS['db_pass'] = $url["pass"];
  $GLOBALS['db_name'] = substr($url["path"], 1);
}
else {
  $dotenv = Dotenv\Dotenv::create(__DIR__);
  $dotenv->load();  
}

$app->get('/', function (Request $request, Response $response) {
  echo 'Trailburning® Platform GAME API<br/>';
});

$app->get('/worker', function (Request $request, Response $response) {
  $log = new Logger('tracker');
  $log->pushHandler(new StreamHandler('php://stderr', Logger::WARNING));

  // process game activity
  $jsonActivity = processActivity($log);

  // don't present full json as it's very large, just create something small!
  $jsonResponse = array('active_games' => count($jsonActivity));

  if (!DEBUG) {
    return $response->withJSON($jsonResponse);
  }
});

$app->get('/events', function (Request $request, Response $response) {
  $authorization = "Authorization: Bearer " . getenv('EVENTBRITE_API_KEY');

  $url = 'https://www.eventbriteapi.com/v3/organizations/318563770275/events/';

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization ));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $result = curl_exec($ch);

  curl_close($ch);

  return $result;
});

$app->get('/strava/subscribe', function (Request $request, Response $response) {
  StravaSubscribe(GAME_API_DOMAIN);
});

$app->get('/strava/unsubscribe/{ID}', function (Request $request, Response $response) {
  StraveUnsubscribe($request->getAttribute('ID'));
});

$app->get('/strava/getsubscriptions', function (Request $request, Response $response) {
  StravaGetSubscriptions();
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
                    if (is_null($gamePlayerResults[0]['challengeCompleted'])) {
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

// 190911 MLA only use to generate shrt lived tokens and then remove!
/*
$app->get('/strava/updatetokens', function (Request $request, Response $response) {
  // generate short lived tokens from forever tokens
  StravaUpdateTokens();
});
*/
$app->post('/player/{playerHashID}/activity', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true);

  $playerHashID = $hashids->decode($data['playerHashID'])[0];

  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = array();

  // use UTC date
  date_default_timezone_set("UTC");

  $dtNow = date('Y-m-d\TH:i:s.000\Z', time());
  $data['start_date'] = $dtNow;

  addPlayerManualActivityToDB($playerID, $data);

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/strava/oauth', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');

  $jsonResponse = StravaGetOAuth(MR_SECURE_DOMAIN, $hashCampaignID);

  return $response->withJSON($jsonResponse);  
});

$app->get('/campaign/{campaignHashID}/strava/code/{stravaCode}/token', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $stravaCode = $request->getAttribute('stravaCode');

  $jsonResponse = StravaGetOAuthToken(MR_SECURE_DOMAIN, $hashCampaignID, $stravaCode);

  return $response->withJSON($jsonResponse);  
});

$app->get('/routes', function (Request $request, Response $response) {
  $jsonResponse = getRoutesFromDB();

  return $response->withJSON($jsonResponse);
});

$app->get('/route/{routeHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashRouteID = $request->getAttribute('routeHashID');
  $routeID = $hashids->decode($hashRouteID)[0];

  $jsonResponse = getRouteWithPointsFromDB($routeID);

  return $response->withJSON($jsonResponse);
});

$app->post('/route', function (Request $request, Response $response) {
  $json = $request->getBody();
  $data = json_decode($json, true); 

  $jsonResponse = addRouteToDB($data['name'], $data['description']);

  return $response->withJSON($jsonResponse);
});

$app->post('/route/{routeHashID}/upload', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashRouteID = $request->getAttribute('routeHashID');
  $routeID = $hashids->decode($hashRouteID)[0];

  uploadRoute($routeID);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->post('/route/{routeHashID}/event', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $hashRouteID = $request->getAttribute('routeHashID');
  $routeID = $hashids->decode($hashRouteID)[0];

  $jsonResponse = array();

  // lookup language
  $jsonLangResponse = getLanguageFromDBByName($data['lang']);
  if (count($jsonLangResponse)) {
    $jsonResponse = addRouteEventToDB($routeID, $jsonLangResponse[0]['id'], $data['coords'][1], $data['coords'][0], $data['name'], $data['description']);
  }

  return $response->withJSON($jsonResponse);
});

$app->delete('/route/event/{eventHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashEventID = $request->getAttribute('eventHashID');
  $eventID = $hashids->decode($hashEventID)[0];

  // delete from db
  deleteRouteEventFromDB($eventID);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->get('/route/{routeHashID}/events/lang/{langName}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashRouteID = $request->getAttribute('routeHashID');
  $routeID = $hashids->decode($hashRouteID)[0];

  $jsonResponse = array();

  $jsonLangResponse = getLanguageFromDBByName($request->getAttribute('langName'));
  if (count($jsonLangResponse)) {
    $jsonResponse['events'] = getRouteEventsFromDB($routeID, $jsonLangResponse[0]['id']);
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/route/{routeHashID}/events', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashRouteID = $request->getAttribute('routeHashID');
  $routeID = $hashids->decode($hashRouteID)[0];

  $jsonResponse = array();

  $jsonLangResponse = getLanguageFromDBByName('en');
  if (count($jsonLangResponse)) {
    $jsonResponse['events'] = getRouteEventsFromDB($routeID, $jsonLangResponse[0]['id']);
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/route/event/{eventHashID}/asset', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $hashEventID = $request->getAttribute('eventHashID');

  $eventID = $hashids->decode($hashEventID)[0];

  $jsonResponse = addRouteEventAssetToDB($eventID, $data['name'], $data['description']);

  return $response->withJSON($jsonResponse);
});

$app->post('/route/event/asset/{assetHashID}/media/upload', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashAssetID = $request->getAttribute('assetHashID');

  $assetID = $hashids->decode($hashAssetID)[0];

  // add to db
  $strMimeType = mime_content_type($_FILES['upload_file']['tmp_name']);
  $routeAssetMediaID = addRouteEventAssetMediaToDB($assetID, $_FILES['upload_file']['name'], $strMimeType); 
  if ($routeAssetMediaID) {
    $hashRouteAssetMediaID = $hashids->encode($routeAssetMediaID);

    $strPath = 'routes/' . $hashAssetID . '/' . $hashRouteAssetMediaID . '/';
    $strFile =  $_FILES['upload_file']['name'];
    uploadAsset($strPath, $strFile);
  }
});

$app->put('/route/event/asset/{assetHashID}/media/{mediaHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $hashAssetID = $request->getAttribute('assetHashID');
  $hashMediaID = $request->getAttribute('mediaHashID');

  $assetID = $hashids->decode($hashAssetID)[0];
  $mediaID = $hashids->decode($hashMediaID)[0];

  // update in db
  updateRouteEventAssetMediaInDB($mediaID, $data['category']); 

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->delete('/route/event/asset/{assetHashID}/media/{mediaHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashAssetID = $request->getAttribute('assetHashID');
  $hashMediaID = $request->getAttribute('mediaHashID');

  $assetID = $hashids->decode($hashAssetID)[0];
  $mediaID = $hashids->decode($hashMediaID)[0];

  $db = connect_db();

  $jsonMediaResponse = getRouteEventAssetMedia($db, $mediaID);
  if (count($jsonMediaResponse)) {
    $strPath = 'routes/' . $hashAssetID . '/' . $hashMediaID . '/' . $jsonMediaResponse[0]['name'];
    deleteAsset($strPath);

    // delete from db
    deleteRouteEventAssetMediaFromDB($mediaID); 
  }

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->post('/campaign/{campaignHashID}/game/{gameHashID}/update', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $hashGameID = $request->getAttribute('gameHashID');

  $gameID = $hashids->decode($hashGameID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true);

  setGameDetailsInDB($gameID, $data['name'], $data['description']);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

$app->post('/campaign/{campaignHashID}/game/{gameHashID}/upload', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $hashGameID = $request->getAttribute('gameHashID');

  $gameID = $hashids->decode($hashGameID)[0];

  // add to db
  $strMimeType = mime_content_type($_FILES['upload_file']['tmp_name']);
  $gameAssetID = addGameAssetToDB($gameID, $_FILES['upload_file']['name'], $strMimeType);
  if ($gameAssetID) {
    $hashGameAssetID = $hashids->encode($gameAssetID);

    $strPath = 'games/' . $hashCampaignID . '/' . $hashGameID . '/';
    $strFile =  $hashGameAssetID;
    uploadAsset($strPath, $strFile);
  }
});

$app->delete('/campaign/{campaignHashID}/game/{gameHashID}/media/{mediaID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $hashGameID = $request->getAttribute('gameHashID');
  $hashMediaID = $request->getAttribute('mediaID');

  $medaID = $hashids->decode($hashMediaID)[0];

  removeAsset($medaID);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);  
});

$app->get('/game/{gameHashID}/socialimage', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');

  echo generateGameSocialImage($hashids->decode($hashGameID)[0]);
});

$app->get('/game/{gameHashID}/socialimage/progress/{progress}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonCampaignResponse = getCampaignByGameFromDB($gameID);
  if (count($jsonCampaignResponse)) {
    $strProgress = getCurrencySymbol($jsonCampaignResponse[0]['fundraising_currency']) . ' ' . $request->getAttribute('progress');
    echo generateGameProgressSocialImage($gameID, $strProgress);
  }
});

$app->get('/game/{gameHashID}/socialimage/goal/{goal}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonCampaignResponse = getCampaignByGameFromDB($gameID);
  if (count($jsonCampaignResponse)) {
    $strGoal = getCurrencySymbol($jsonCampaignResponse[0]['fundraising_currency']) . ' ' . $request->getAttribute('goal');
    echo generateGameGoalSocialImage($gameID, $strGoal, false);
  }
});

$app->get('/game/{gameHashID}/socialimage/groupgoal/{goal}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonCampaignResponse = getCampaignByGameFromDB($gameID);
  if (count($jsonCampaignResponse)) {
    $strGoal = getCurrencySymbol($jsonCampaignResponse[0]['fundraising_currency']) . ' ' . $request->getAttribute('goal');
    echo generateGameGoalSocialImage($gameID, $strGoal, true);
  }
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

  // add media data
  $jsonResponse[0]['media'] = getGameAssetsFromDB($gameID);

  // add player data
  $jsonResponse[0]['players'] = getGamePlayersFromDB($gameID);

  $gameJSON = $response->withJSON($jsonResponse);

  return $gameJSON;
});

$app->get('/game/{gameHashID}/campaign', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = null;
  if ($gameID) {
    $jsonCampaignResponse = getCampaignByGameFromDB($gameID);
    if (count($jsonCampaignResponse)) {
      $campaignID = $hashids->decode($jsonCampaignResponse[0]['id'])[0];
      // add language data
      $jsonCampaignResponse[0]['languages'] = getCampaignLanguagesFromDB($campaignID);    
      
      $jsonResponse = $jsonCampaignResponse;
    }
  }

  return $response->withJSON($jsonResponse);
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

$app->delete('/game/{gameHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  removeGameFromDB($gameID);

  return null;
});

$app->delete('/game/{gameHashID}/player/{playerHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];
  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  removeGamePlayerFromDB($gameID, $playerID);

  return null;
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
      $campaignID = $hashids->decode($game['campaignID'])[0];
      $jsonCampaignEmailsResponse = getCampaignEmailsFromDB($campaignID);
      if (count($jsonCampaignEmailsResponse)) {
        $campaignEmails = $jsonCampaignEmailsResponse[0];        

        if (count($jsonPlayerResponse)) {
          foreach ($jsonPlayerResponse as $player) {
            if ($player['game_notifications']) {
              $jsonEmail = $campaignEmails['email_welcome'];

              // distance based challenge so use distance email template
              if ($game['distance'] > 0) {
                $jsonEmail = $campaignEmails['email_welcome_distance'];
              }

              sendWelcomeEmail($campaignEmails['email_template'], $jsonEmail, $game, $player);
            }
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

      $campaignID = $hashids->decode($game['campaignID'])[0];
      $jsonCampaignEmailsResponse = getCampaignEmailsFromDB($campaignID);
      if (count($jsonCampaignEmailsResponse)) {
        $campaignEmails = $jsonCampaignEmailsResponse[0];        

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

          $jsonEmail = $campaignEmails['email_invite'];
          sendInviteEmail($campaignEmails['email_template'], $jsonEmail, $game, $invitingPlayer, $player);        
          addLogToDB($db, LOG_OBJECT_PLAYER, LOG_ACTIVITY_INVITATION_SENT, $invitingPlayerID);
        }        
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
      $campaignID = $hashids->decode($game['campaignID'])[0];
      $jsonCampaignEmailsResponse = getCampaignEmailsFromDB($campaignID);
      if (count($jsonCampaignEmailsResponse)) {
        $campaignEmails = $jsonCampaignEmailsResponse[0];        
        if (count($jsonPlayerResponse)) {
          foreach ($jsonPlayerResponse as $player) {
            if ($player['game_notifications']) {
              $jsonEmail = $campaignEmails['email_welcome'];
              sendWelcomeEmail($campaignEmails['email_template'], $jsonEmail, $game, $player);
            }
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

$app->get('/player/{playerHashID}/details', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashClientID = $request->getAttribute('clientHashID');
  $clientID = $hashids->decode($hashClientID)[0];

  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = getPlayerFromDBByID($playerID);
  if (count($jsonResponse)) {
    // add invition data
    $jsonResponse[0]['invitations'] = getPlayerGameInvitationsFromDB($jsonResponse[0]['id']);

    // add game data
    $jsonResponse[0]['games'] = getGamesByPlayerFromDB($jsonResponse[0]['id']);

    $jsonResponse[0]['id'] = $hashids->encode($jsonResponse[0]['id']);
    $jsonResponse[0]['clientID'] = $hashids->encode($jsonResponse[0]['clientID']);

    return $response->withJSON($jsonResponse);
  }
});

$app->get('/game/{gameHashID}/player/{playerHashID}/lastseen', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  setPlayerGameLastSeenInDB($gameID, $playerID);

  $jsonResponse = array();  

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/player/{playerHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = getPlayerByIDFromDB($playerID);
  if (count($jsonResponse)) {
    // add campaign paywall data
    $jsonResponse[0]['campaign_paywall'] = getCampaignPlayersPaywallFromDB($campaignID, $jsonResponse[0]['id']);

    // add invitation data
    $jsonResponse[0]['invitations'] = getPlayerGameInvitationsFromDB($jsonResponse[0]['id']);

    // add campaign game data
    $jsonResponse[0]['games'] = getGamesByCampaignPlayerFromDB($campaignID, $jsonResponse[0]['id']);

    $jsonResponse[0]['id'] = $hashids->encode($jsonResponse[0]['id']);
    $jsonResponse[0]['clientID'] = $hashids->encode($jsonResponse[0]['clientID']);

    return $response->withJSON($jsonResponse);
  }
});

$app->get('/campaign/{campaignHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  // use UTC date
  date_default_timezone_set("UTC");

  $db = connect_db();

  if ($campaignID) {
    $jsonResponse = getCampaignFromDB($db, $campaignID);
    if (count($jsonResponse)) {
      // format dates as UTC
      if ($jsonResponse[0]['start_date']) {
        $dtStartDate = new DateTime($jsonResponse[0]['start_date']);
        $jsonResponse[0]['start_date'] = $dtStartDate->format('Y-m-d\TH:i:s.000\Z');
      }
      if ($jsonResponse[0]['end_date']) {
        $dtEndDate = new DateTime($jsonResponse[0]['end_date']);
        $jsonResponse[0]['end_date'] = $dtEndDate->format('Y-m-d\TH:i:s.000\Z');
      }

      $jsonResponse[0]['paywall_currency_symbol'] = getCurrencySymbol($jsonResponse[0]['paywall_currency']);
      $jsonResponse[0]['fundraising_currency_symbol'] = getCurrencySymbol($jsonResponse[0]['fundraising_currency']);
      // add language data
      $jsonResponse[0]['languages'] = getCampaignLanguagesFromDB($campaignID);

      $jsonResponse[0]['emails'] = getCampaignEmailsFromDB($campaignID);
    }
    return $response->withJSON($jsonResponse);
  }

  return null;
});

$app->get('/campaign/{campaignHashID}/games', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonResponse = getGamesByCampaignFromDB($campaignID);

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/monitorgames/{numGames}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonGamesResponse = getGamesAndPlayersByCampaignFromDB($campaignID, $request->getAttribute('numGames'));

  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as &$game) {
      $gameID = $hashids->decode($game['id'])[0];

      $game['players'] = getGamePlayersFromDB($gameID);
    }
  }

  return $response->withJSON($jsonGamesResponse);
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

$app->get('/client/{clientHashID}/players/{match}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashClientID = $request->getAttribute('clientHashID');
  $clientID = $hashids->decode($hashClientID)[0];

  $jsonResponse = getPlayersFromDBByClient($clientID, $request->getAttribute('match'));

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/playergames/{match}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $campaignID = $hashids->decode($hashCampaignID)[0];

  $jsonResponse = getPlayerGamesFromDBByCampaign($campaignID, $request->getAttribute('match'));

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

  $jsonResponse = array();

  $jsonResponse['campaign_players_paywall'] = [];
  $jsonCampaignPlayersPaywallResponse = getCampaignPlayersPaywallSummaryFromDB($campaignID);
  if (count($jsonCampaignPlayersPaywallResponse)) {
    $jsonCampaignPlayersPaywallResponse[0]['fundraising_currency_symbol'] = getCurrencySymbol($jsonCampaignPlayersPaywallResponse[0]['fundraising_currency']);
    $jsonCampaignPlayersPaywallResponse[0]['total_paywall_amount'] = $jsonCampaignPlayersPaywallResponse[0]['total_paywall_amount'] / 100;
    $jsonResponse['campaign_players_paywall'] = $jsonCampaignPlayersPaywallResponse;
  }

  $jsonResponse['campaign_donations'] = [];
  $jsonCampaignDonationsResponse = getCampaignDonationsSummaryFromDB($campaignID);
  if (count($jsonCampaignDonationsResponse)) {
    $jsonCampaignDonationsResponse[0]['fundraising_currency_symbol'] = getCurrencySymbol($jsonCampaignDonationsResponse[0]['fundraising_currency']);
    $jsonCampaignDonationsResponse[0]['total_donations'] = $jsonCampaignDonationsResponse[0]['total_donations'] / 100;
    $jsonResponse['campaign_donations'] = $jsonCampaignDonationsResponse;
  }

  $jsonResponse['campaign_challenges'] = [];
  $jsonCampaignChallengesResponse = getCampaignSummaryFromDB($campaignID);
  if (count($jsonCampaignChallengesResponse)) {
    $jsonCampaignChallengesResponse[0]['fundraising_currency_symbol'] = getCurrencySymbol($jsonCampaignChallengesResponse[0]['fundraising_currency']);

    $jsonResponse['campaign_challenges'] = $jsonCampaignChallengesResponse;
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/campaign/{campaignHashID}/fundraising/causes', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $jsonResponse = getFundraisingCampaignCauses($campaignID);
  foreach ($jsonResponse as &$cause) {
    $causeID = $hashids->decode($cause['id'])[0];
    $cause['items'] = getFundraisingCauseShoppingList($causeID);
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameHashID}/player/{playerHashID}/cause', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $gameHashID = $hashids->decode($request->getAttribute('gameHashID'))[0];
  $playerHashID = $hashids->decode($request->getAttribute('playerHashID'))[0];

  $jsonResponse = getFundraisingGamePlayerCause($gameHashID, $playerHashID);
  foreach ($jsonResponse as &$cause) {
    $causeID = $hashids->decode($cause['id'])[0];
    $cause['items'] = getFundraisingCauseShoppingList($causeID);
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/player/{playerHashID}', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashPlayerID = $request->getAttribute('playerHashID');

  $json = $request->getBody();
  $data = json_decode($json, true);

  $jsonResponse = array();

  // try and update
  if (!updatePlayerPreferencesInDB($hashids->decode($hashPlayerID)[0], $data['email'], $data['receiveEmail'])) {
    $jsonResponse = array('error' => array('id' => 'UserEmailAlreadyExists'));
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/campaign/{campaignHashID}/player/{playerHashID}/paywall/payment', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];
  $playerID = $hashids->decode($request->getAttribute('playerHashID'))[0];

  $json = $request->getBody();
  $data = json_decode($json, true);

  $jsonResponse = array();

  $db = connect_db();

  // get campaign
  $jsonCampaignResponse = getCampaignFromDB($db, $campaignID);
  if (count($jsonCampaignResponse)) {
    // get player
    $jsonPlayerResponse = getPlayerFromDBByID($playerID);
    if (count($jsonPlayerResponse)) {
      $fAmount = $jsonCampaignResponse[0]['paywall_amount'] * 100;

      $strStripeAPIKey = STRIPE_API_KEY_TEST;
      if ($jsonCampaignResponse[0]['live_payments'] == 1) {
        $strStripeAPIKey = STRIPE_API_KEY_LIVE;
      }
      \Stripe\Stripe::setApiKey(getenv($strStripeAPIKey));

      try {
        $charge = \Stripe\Charge::create([
          "amount" => $fAmount,
          "currency" => $jsonCampaignResponse[0]['paywall_currency'],
          "source" => $data['token'],
          "description" => $jsonCampaignResponse[0]['name'] . " payment for " . $jsonPlayerResponse[0]['firstname'] . ' ' . $jsonPlayerResponse[0]['lastname'],
          "receipt_email"=> $jsonPlayerResponse[0]['email'],
          'metadata' => ['campaign_id' => $request->getAttribute('campaignHashID'), 'player_id' => $request->getAttribute('playerHashID')]
        ]);
        $jsonResponse = $charge;

        // insert paywall data
        setCampaignPlayerPaywallInDB($campaignID, $playerID, $fAmount, $charge['id']);
      }
      catch(Exception $e) {
        $jsonResponse['error'] = $e;
        error_log("unable to create charge, error:" . $e->getMessage());
      }
    }
  }

  return $response->withJSON($jsonResponse);
});

$app->post('/campaign/{campaignHashID}/payment', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $campaignID = $hashids->decode($request->getAttribute('campaignHashID'))[0];

  $json = $request->getBody();
  $data = json_decode($json, true);

  $jsonResponse = array();

  $db = connect_db();

  // get campaign
  $jsonCampaignResponse = getCampaignFromDB($db, $campaignID);
  if (count($jsonCampaignResponse)) {
    $fAmount = $data['amount'] * 100;
    $strEmail = $data['email'];

    $strStripeAPIKey = STRIPE_API_KEY_TEST;
    if ($jsonCampaignResponse[0]['live_payments'] == 1) {
      $strStripeAPIKey = STRIPE_API_KEY_LIVE;
    }
    \Stripe\Stripe::setApiKey(getenv($strStripeAPIKey));

    try {
        $charge = \Stripe\Charge::create([
          "amount" => $fAmount,
          "currency" => $jsonCampaignResponse[0]['paywall_currency'],
          "source" => $data['token'],
          "description" => $jsonCampaignResponse[0]['name'] . " donation",
          "receipt_email"=> $strEmail,
          'metadata' => ['campaign_id' => $request->getAttribute('campaignHashID')]
        ]);
        $jsonResponse = $charge;

        // insert donation data
        setCampaignDonationInDB($campaignID, $fAmount, $charge['id']);
      }
      catch(Exception $e) {
        $jsonResponse['error'] = $e;
        error_log("unable to create charge, error:" . $e->getMessage());
      }
  }

  return $response->withJSON($jsonResponse);
});

$app->get('/player/{playerHashID}/update', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);
  
  $hashPlayerID = $request->getAttribute('playerHashID');
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = updatePlayer($playerID);
  $jsonResponse = getPlayerFromDBByID($playerID);

  // add game data
  $jsonResponse[0]['games'] = getGamesByPlayerFromDB($jsonResponse[0]['id']);

  $hashID = $hashids->encode($jsonResponse[0]['id']);
  $jsonResponse[0]['id'] = $hashID;

  $hashClientID = $hashids->encode($jsonResponse[0]['clientID']);
  $jsonResponse[0]['clientID'] = $hashClientID;

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

$app->get('/game/{gameHashID}/fundraising/shoppinglist', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = array('items' => getFundraisingGameShoppingList($gameID));

  return $response->withJSON($jsonResponse);
});

$app->post('/vote', function (Request $request, Response $response) {

  $json = $request->getBody();
  $data = json_decode($json, true);

  addGameLevelVoteToDB($data['name'], $data['vote']);

  $jsonResponse = array();

  return $response->withJSON($jsonResponse);
});

/* **************************************************************************** */
/* Start Support RaiseNow */
/* **************************************************************************** */

$app->post('/fundraiser/game/{gameHashID}/player/{playerHashID}/cause', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $causeID = $hashids->decode($data['causeID'])[0];

  setPlayerGameCauseInDB($gameID, $playerID, $causeID);

  $jsonResponse = array();  

  return $response->withJSON($jsonResponse);
});

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
      setPlayerGameFundraisingDetailsInDB($gameID, $playerID, $data['supporterMsg'], $data['targetAmount'], 0, $data['currencyCode'], $data['charityOptIn']);

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

  $jsonResponse = getFundraisingDetails(PROVIDER_SERVER_CAUSE_CODE, $hashGameID, $hashPlayerID);

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

$app->get('/game/{gameHashID}/fundraiser/donations', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');

  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = getGameFundraisingDonations(PROVIDER_SERVER_CAUSE_CODE, $hashGameID);

  return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameHashID}/player/{playerHashID}/fundraiser/donations', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $jsonResponse = getGamePlayerFundraisingDonations(PROVIDER_SERVER_CAUSE_CODE, $hashGameID, $hashPlayerID);

  return $response->withJSON($jsonResponse);
});

/* 190228 MLA - ideally this should be called by RaiseNow callback and not posted from the client */
$app->post('/fundraiser/campaign/{campaignHashID}/game/{gameHashID}/player/{playerHashID}/donation', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashCampaignID = $request->getAttribute('campaignHashID');
  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $campaignID = $hashids->decode($hashCampaignID)[0];
  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $donation = array(
    'currency' => $data['currency'],
    'amount' => round($data['amount'] / 100),
    'donor' => $data['donor'],
  );

  $db = connect_db();
  // get campaign
  $jsonCampaign = getCampaignFromDB($db, $campaignID);
  if (count($jsonCampaign)) {
    $campaign = $jsonCampaign[0];

    // get campaign emails
    $jsonCampaignEmailsResponse = getCampaignEmailsFromDB($campaignID);
    if (count($jsonCampaignEmailsResponse)) {
      // 190307 mla - current uses 1st emails but should use lang to pick correct ones.
      $campaignEmails = $jsonCampaignEmailsResponse[0];
      $jsonGamesResponse = getGameFromDB($gameID);
      if (count($jsonGamesResponse)) {
        $game = $jsonGamesResponse[0];
        $jsonPlayersResponse = getPlayerFromDBByID($playerID);
        if (count($jsonPlayersResponse)) {
          $player = $jsonPlayersResponse[0];
          if ($player['game_notifications']) {
            $player['id'] = $hashPlayerID;

            $jsonEmail = $campaignEmails['email_fundraising_donation'];        
            sendFundraisingDonationEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $donation);
          }
        }
      }
    }
  }

  $jsonResponse = array();  

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
    if ($jsonPlayerResponse->isValid) {
      addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_FUNDRAISING_USER_QUERY_SUCCESS, $playerID);
    }
    else {
      addLogToDB($db, LOG_OBJECT_PLAYER_PROVIDER, LOG_FUNDRAISING_USER_QUERY_FAIL, $playerID);
    }
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
        'supporterMsg' => $data['supporterMsg'],
        'justGivingOptIn' => $data['justGivingOptIn'],
        'charityOptIn' => $data['charityOptIn'],
        'imageURL' => "http://tbassets2.imgix.net/images/brands/mountainrush/social/wwf/CFYW_Gorilla_JustGiving2.png"
      ];
      $jsonResponse = createFundraisingPlayerPage($paramaObj);

      if ($jsonResponse) {
        if ($jsonResponse->pageId) {
          $jsonResponse->fundraising_page = $fundraisingPage;
          // store fundraising page
          setPlayerGameFundraisingPageInDB($gameID, $playerID, $jsonResponse->pageId, $fundraisingPage, $data['supporterMsg'], $data['targetAmount'], 'GBP');
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

$app->post('/fundraiser/game/{gameHashID}/player/{playerHashID}/goal', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $hashGameID = $request->getAttribute('gameHashID');
  $hashPlayerID = $request->getAttribute('playerHashID');

  $gameID = $hashids->decode($hashGameID)[0];
  $playerID = $hashids->decode($hashPlayerID)[0];

  $json = $request->getBody();
  $data = json_decode($json, true); 

  $jsonResponse = array();  

  // store fundraising goal
  setPlayerGameFundraisingGoalInDB($gameID, $playerID, $data['targetAmount']);

  return $response->withJSON($jsonResponse);
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
    setPlayerGameFundraisingDetailsInDB($gameID, $playerID, $jsonResponse->story, $jsonResponse->fundraisingTarget, $jsonResponse->totalRaisedOnline, $jsonResponse->currencyCode, 0);
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

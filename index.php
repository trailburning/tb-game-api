<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include "lib/tbEmail.php";
include "lib/tbImgix.php";
include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";

require 'vendor/autoload.php';

$app = new \Slim\App;

define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

const GAME_PLAYER_PLAYING_STATE = 0;
const GAME_PLAYER_SUMMITED_STATE = 1;

function getPlayerGameProgress($playerID, $gameID) {
  $gameResults = getGameFromDB($gameID);

  $dtActivityStartDate = $gameResults[0]['game_start'];
  $dtActivityEndDate = $gameResults[0]['game_end'];

  // get player game details
  $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
  if (count($gamePlayerResults)) {
    if (!is_null($gamePlayerResults[0]['ascentCompleted'])) {
      // use ascent date rather game end date
      $dtActivityEndDate = $gamePlayerResults[0]['ascentCompleted'];
    }
  }

  $arrPlayerActivities = getPlayerActivities($playerID, $dtActivityStartDate, $dtActivityEndDate, $gameResults[0]['type']);

  $nElevationGain = 0;
  foreach ($arrPlayerActivities as $activity) {
    $nElevationGain += $activity['total_elevation_gain'];
  }
  // 1st activity is the most recent
  if (count($arrPlayerActivities)) {
    $dtLastActivity = $arrPlayerActivities[0]['start_date'];
  }

  // has player reached or exceeded the ascent goal?
  if ($nElevationGain >= $gameResults[0]['ascent']) {
    setPlayerGameAscentCompleteInDB($gameID, $playerID, $dtLastActivity);
  }
  return $arrPlayerActivities;
}

$app->get('/worker', function (Request $request, Response $response) {
  // process game activity
  $hashids = new Hashids\Hashids('mountainrush', 10);

  // get all games
  $jsonGamesResponse = getGamesFromDB();
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      // look for active games
      if ($game['game_state'] == STATE_GAME_ACTIVE) {
        $gameID = $hashids->decode($game['id'])[0];
        // look for player activity in game
        $jsonPlayerActivityResponse = getPlayerActivtyByGameFromDB($gameID);
        if (count($jsonPlayerActivityResponse)) {
          // go through all active game players
          foreach ($jsonPlayerActivityResponse as $activePlayer) {
            // check the activity exists
            $activity = getPlayerActivity($activePlayer['playerProviderToken'], $activePlayer['latest_activity']);
            if ($activity) {
              // reset activity
              setPlayerGameActivityInDB($gameID, $activePlayer['id'], 0);
              // check activity type matches game type
              if ($activity['type'] == $game['type']) {
                // get all game players
                $jsonPlayersResponse = getGamePlayersFromDB($gameID);
                if (count($jsonPlayersResponse)) {
                  $bActivePlayerSummited = false;
                  // get latest activities to update player progress
                  getPlayerGameProgress($activePlayer['id'], $gameID);
                  // get player game details
                  $gamePlayerResults = getGamePlayerFromDB($gameID, $activePlayer['id']);
                  if (count($gamePlayerResults)) {
                    if (!is_null($gamePlayerResults[0]['ascentCompleted'])) {
                      $bActivePlayerSummited = true;
                    }
                  }

                  foreach ($jsonPlayersResponse as $player) {
                    if ($player['game_notifications']) {
                      sendActivityEmail($game, $player, $activePlayer);
                      // has player summited and not already been processed?
                      if ($bActivePlayerSummited && $activePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
                        setPlayerGameStateInDB($gameID, $activePlayer['id'], GAME_PLAYER_SUMMITED_STATE);
                        sendSummitEmail($game, $player, $activePlayer);
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
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
    // look for user
    $jsonUserResponse = getPlayerFromDBByProviderID($data['owner_id']);
    if (count($jsonUserResponse)) {
      $playerID = $jsonUserResponse[0]['id'];
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

  header("HTTP/1.1 200 OK");

  return;
});

$app->get('/game/{gameHashID}/socialimage', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);
  $hashGameID = $request->getAttribute('gameHashID');
  
  $arrGameID = $hashids->decode($hashGameID);
  if (count($arrGameID)) {
    $arrResponse = getGameFromDB($arrGameID[0]);
    if (count($arrResponse)) {
      $paramaObj = (object) [
        'journeyID' => $arrResponse[0]['journeyID'],
        'mountain' => $arrResponse[0]['name'],
        'region' => strtolower($arrResponse[0]['region']),
        'ascent' => $arrResponse[0]['ascent'] . 'm',
        'challenge' => strtolower($arrResponse[0]['type']) . ' challenge'
      ];
      echo buildSocialGameImage($paramaObj);
    }
  }
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
  $json = $request->getBody();
  $data = json_decode($json, true);

  $jsonResponse = addGameToDB($data['name'], $data['ascent'], $data['type'], $data['gameStart'], $data['gameEnd'], $data['journeyID'], $data['mountain3DName']);

  return $response->withJSON($jsonResponse);
});

$app->get('/player/{token}', function (Request $request, Response $response) {
    $hashids = new Hashids\Hashids('mountainrush', 10);
  
    $token = $request->getAttribute('token');
    $jsonResponse = getPlayer($token);

    // add game data
    $jsonResponse[0]['games'] = getGamesByPlayerFromDB($jsonResponse[0]['id']);

    $hashID = $hashids->encode($jsonResponse[0]['id']);
    $jsonResponse[0]['id'] = $hashID;

    return $response->withJSON($jsonResponse);
});

$app->post('/player', function (Request $request, Response $response) {
  $json = $request->getBody();
  $data = json_decode($json, true);

  $jsonResponse = addPlayerToDB($data['avatar'], $data['firstname'], $data['lastname'], $data['email'], $data['city'], $data['country'], '', '');

  return $response->withJSON($jsonResponse);
});

$app->get('/player/{token}/update', function (Request $request, Response $response) {
    $hashids = new Hashids\Hashids('mountainrush', 10);
  
    $token = $request->getAttribute('token');
    $jsonResponse = updatePlayer($token);
    $jsonResponse = getPlayer($token);

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

$app->run();

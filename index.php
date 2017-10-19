<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use Imgix\UrlBuilder;

include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";

require 'vendor/autoload.php';

$app = new \Slim\App;

define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

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
  // STRAVA GET to veriy  callback endpoint
  $allGetVars = $request->getQueryParams();

  if ($allGetVars['hub_verify_token'] == 'STRAVA') {
    header("HTTP/1.1 200 OK");
    $data = array('hub.challenge' => $allGetVars['hub_challenge']);

    return $response->withJSON($data);
  }
});

$app->post('/strava/callback', function (Request $request, Response $response) {
  // STRAVA POST to send update
  $json = $request->getBody();
  $data = json_decode($json, true); 

//  echo $data['subscription_id'];
  $body = $json;
  mail('mallbeury@mac.com', 'Strava Test', $body);

  header("HTTP/1.1 200 OK");

  return;
});

$app->get('/game/{gameHashID}/socialimage', function (Request $request, Response $response) {
  $hashids = new Hashids\Hashids('mountainrush', 10);
  $hashGameID = $request->getAttribute('gameHashID');
  $gameID = $hashids->decode($hashGameID)[0];

  $jsonResponse = getGameFromDB($gameID);

  $strJourneyID = $jsonResponse[0]['journeyID'];
  $strMountain = $jsonResponse[0]['name'];
  $strRegion = strtolower($jsonResponse[0]['region']);
  $strAscent = $jsonResponse[0]['ascent'] . 'm';
  $strChallenge = strtolower($jsonResponse[0]['type']) . ' challenge';

  $builder = new UrlBuilder("tbassets2.imgix.net");

  // bottom left data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 55, "txt64" => $strMountain);
  $txtMountain = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Regular", "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 27, "txt64" => $strRegion);
  $txtCountry = $builder->createURL("~text", $params);

  $params = array("w" => 600, "h" => 168, "markx" => 46, "marky" => 24, "mark64" => $txtMountain,
  "bx" => 46, "by" => 90, "bm" => 'normal', "blend64" => $txtCountry);
  $leftData = $builder->createURL("images/brands/mountainrush/social/bg_text2.png", $params);

  // bottom right data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 55, "txt64" => $strAscent);
  $txtAscent = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Regular", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 27, "txt64" => $strChallenge);
  $txtDetail = $builder->createURL("~text", $params);

  $params = array("w" => 600, "h" => 168, "markx" => -46, "marky" => 24, "mark64" => $txtAscent,
  "bx" => -46, "by" => 90, "bm" => 'normal', "blend64" => $txtDetail);
  $rightData = $builder->createURL("images/brands/mountainrush/social/bg_text2.png", $params);

  // bottom data
  $params = array("w" => 1200, "h" => 150, "markx" => 0, "marky" => 0, "mark64" => $leftData,
  "bx" => 600, "by" => 0, "bm" => 'normal', "blend64" => $rightData);
  $bottomImg = $builder->createURL("images/brands/mountainrush/social/bg_blank2.png", $params);

  // overlay
  $params = array("w" => 1200, "h" => 630);
  $overlayImg = $builder->createURL("images/brands/mountainrush/social/overlay.png", $params);

  // final image
  $params = array("w" => 1200, "h" => 630, "q" => 80, "markx" => 0, "marky" => 480, "mark64" => $bottomImg,
  "bw" => 1200, "bh" => 630, "bm" => 'normal', "blend64" => $overlayImg);
  $finalImg = $builder->createURL("images/brands/mountainrush/social/games/" . $strJourneyID . ".jpg", $params);

  echo $finalImg;
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

    $gameResults = getGameFromDB($gameID);

    $hashPlayerID = $request->getAttribute('playerHashID');
    $playerID = $hashids->decode($hashPlayerID)[0];
    $arrPlayerActivities = getPlayerActivities($playerID, $gameResults[0]['game_start'], $gameResults[0]['game_end'], $gameResults[0]['type']);

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

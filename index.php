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
include "lib/tbPlayerActivityPhotos.php";

require 'vendor/autoload.php';

$app = new \Slim\App;

$app->get('/socialimage', function (Request $request, Response $response) {
  $builder = new UrlBuilder("tbassets2.imgix.net");

  // bottom left data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 64, "txt64" => 'Matterhorn');
  $txtMountain = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Regular", "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 32, "txt64" => 'switzerland');
  $txtCountry = $builder->createURL("~text", $params);

  $params = array("w" => 600, "h" => 168, "markx" => 46, "marky" => 38, "mark64" => $txtMountain,
  "bx" => 46, "by" => 104, "bm" => 'normal', "blend64" => $txtCountry);
  $leftData = $builder->createURL("images/brands/mountainrush/bg_text.png", $params);

  // bottom right data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 64, "txt64" => '2459m');
  $txtAscent = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Regular", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 32, "txt64" => 'run challenge');
  $txtDetail = $builder->createURL("~text", $params);

  $params = array("w" => 600, "h" => 168, "markx" => -46, "marky" => 38, "mark64" => $txtAscent,
  "bx" => -46, "by" => 104, "bm" => 'normal', "blend64" => $txtDetail);
  $rightData = $builder->createURL("images/brands/mountainrush/bg_text.png", $params);

  // bottom data
  $params = array("w" => 1200, "h" => 168, "markx" => 0, "marky" => 0, "mark64" => $leftData,
  "bx" => 600, "by" => 0, "bm" => 'normal', "blend64" => $rightData);
  $bottomImg = $builder->createURL("images/brands/mountainrush/bg_blank.png", $params);

  // top data
  $params = array("w" => 1200, "h" => 168);
  $topImg = $builder->createURL("images/brands/mountainrush/bg_blank.png", $params);

  // final image
  $params = array("w" => 1200, "h" => 630, "q" => 80, "markx" => 0, "marky" => 0, "mark64" => $topImg,
  "bx" => 0, "by" => 462, "bm" => 'normal', "blend64" => $bottomImg);
  $finalImg = $builder->createURL("images/brands/mountainrush/test2.jpg", $params);

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

$app->get('/player/{playerHashID}/activity/{activityID}/photos', function (Request $request, Response $response) {
    $hashids = new Hashids\Hashids('mountainrush', 10);
  
    $hashPlayerID = $request->getAttribute('playerHashID');
    $playerID = $hashids->decode($hashPlayerID)[0];

    $activityID = $request->getAttribute('activityID');
    $jsonResponse = getPlayerActivityPhotos($playerID, $activityID);
    
    return $response->withJSON($jsonResponse);
});

$app->run();

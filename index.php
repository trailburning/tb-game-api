<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include "lib/tbGame.php";
include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";
include "lib/tbPlayerActivityPhotos.php";

require 'vendor/autoload.php';

$app = new \Slim\App;

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

$app->get('/game/{gameHashID}/player/{playerHashID}/activities', function (Request $request, Response $response) {
    $hashids = new Hashids\Hashids('mountainrush', 10);

    $hashGameID = $request->getAttribute('gameHashID');
    $gameID = $hashids->decode($hashGameID)[0];

    $gameResults = getGameFromDB($gameID);

    $hashPlayerID = $request->getAttribute('playerHashID');
    $playerID = $hashids->decode($hashPlayerID)[0];
    $jsonResponse = getPlayerActivities($playerID, $gameResults[0]['game_start'], $gameResults[0]['game_end'], $gameResults[0]['type']);
    
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

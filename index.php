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

$app->get('/game/{gameID}', function (Request $request, Response $response) {
    $gameID = $request->getAttribute('gameID');
    $jsonResponse = getGameFromDB($gameID);

    // use UTC date
    date_default_timezone_set("UTC");
    $dtNow = date('Y-m-d H:i:s', time());
    $jsonResponse[0]['game_now'] = $dtNow;
    // add player data
    $jsonResponse[0]['players'] = getGamePlayersFromDB($gameID);

    $gameJSON = $response->withJSON($jsonResponse);

    return $gameJSON;
});

$app->get('/player/{token}', function (Request $request, Response $response) {
    $token = $request->getAttribute('token');
    $jsonResponse = getPlayer($token);
    
    return $response->withJSON($jsonResponse);
});

$app->get('/game/{gameID}/player/{playerID}/activities', function (Request $request, Response $response) {
    $gameID = $request->getAttribute('gameID');
    $gameResults = getGameFromDB($gameID);

    $playerID = $request->getAttribute('playerID');
    $jsonResponse = getPlayerActivities($playerID, $gameResults[0]['game_start'], $gameResults[0]['game_end'], $gameResults[0]['type']);
    
    return $response->withJSON($jsonResponse);
});

$app->get('/player/{playerID}/activity/{activityID}/photos', function (Request $request, Response $response) {
    $playerID = $request->getAttribute('playerID');
    $activityID = $request->getAttribute('activityID');
    $jsonResponse = getPlayerActivityPhotos($playerID, $activityID);
    
    return $response->withJSON($jsonResponse);
});

$app->run();

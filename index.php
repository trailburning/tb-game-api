<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

include "lib/tbPlayer.php";
include "lib/tbPlayerActivities.php";
include "lib/tbPlayerActivityPhotos.php";

require 'vendor/autoload.php';

$app = new \Slim\App;
$app->get('/player/{token}', function (Request $request, Response $response) {
    $token = $request->getAttribute('token');
    $jsonResponse = getPlayer($token);
    
    return $response->withJSON($jsonResponse);
});

$app->get('/player/{playerID}/activities', function (Request $request, Response $response) {
    $playerID = $request->getAttribute('playerID');
    $jsonResponse = getPlayerActivities($playerID);
    
    return $response->withJSON($jsonResponse);
});

$app->get('/player/{playerID}/activity/{activityID}/photos', function (Request $request, Response $response) {
    $playerID = $request->getAttribute('playerID');
    $activityID = $request->getAttribute('activityID');
    $jsonResponse = getPlayerActivityPhotos($playerID, $activityID);
    
    return $response->withJSON($jsonResponse);
});

$app->run();

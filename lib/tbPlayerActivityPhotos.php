<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

function getPlayerActivityPhotos($playerID, $activityID) {
  // first find last update date
  $results = getPlayerFromDB($playerID);
  if (count($results) != 0) {
    $token = $results[0]['playerProviderToken'];

    try {
        $adapter = new Pest('https://www.strava.com/api/v3');
        $service = new REST($token, $adapter);

        $client = new Client($service);
        $activityPhotos = $client->getActivityPhotos($activityID, $size = 640, $photo_sources = 'true');

        $results = $activityPhotos;
    } catch(Exception $e) {
        echo json_encode($e->getMessage());
    }
  }  
  return $results;
}
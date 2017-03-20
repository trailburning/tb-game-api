<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

const UPDATE_SECS = 60;

function getPlayerActivitiesFromDB($playerID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT activity, distance, total_elevation_gain, start_date FROM playerActivities where player = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function addPlayerActivitiesToDB($playerID, $jsonActivities) {
  require_once 'lib/mysql.php';

  $db = connect_db();

  foreach($jsonActivities as $activity) {
    $result = $db->query('INSERT INTO playerActivities (player, activity, distance, total_elevation_gain, start_date) VALUES (' . $playerID . ', ' . $activity['id'] . ', ' . $activity['distance'] . ', ' . $activity['total_elevation_gain'] . ', "' . $activity['start_date'] . '")');
  }
}

function getPlayerActivities($playerID) {
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  // first find last update date
  $results = getPlayerFromDB($playerID);
  if (count($results) != 0) {
    if ($results[0]['last_updated']) {
      $token = $results[0]['playerProviderToken'];
      $dtLastUpdated = $results[0]['last_updated'];
      $dtLastActivity = $results[0]['last_activity'];
      if (!$dtLastActivity) {
        $dtLastActivity = $dtLastUpdated;
      }

      // time to get new activities
      $tNow = strtotime($dtNow);
      $tLastUpdated = strtotime($dtLastUpdated);
      $nUpdatedSecondsAgo = abs($tNow - $tLastUpdated);

      if ($nUpdatedSecondsAgo > UPDATE_SECS) {
        $tLastActivity = strtotime($dtLastActivity);

        // get from provider
        $adapter = new Pest('https://www.strava.com/api/v3');
        $service = new REST($token, $adapter);

        $client = new Client($service);
        $activities = $client->getAthleteActivities(null, $tLastActivity);

        if (sizeof($activities)) {
          // last entry is most recent when calling with 'after' date
          $dtLastActivity = $activities[sizeof($activities)-1]['start_date'];
          updatePlayerLastActivityInDB($playerID, $dtLastActivity);
        }
        addPlayerActivitiesToDB($playerID, $activities);
      }
    }
    $dtLastUpdated = $dtNow;
    // store last updated
    updatePlayerLastUpdatedInDB($playerID, $dtLastUpdated);

    $results = getPlayerActivitiesFromDB($playerID);
  }  

  return $results;
}
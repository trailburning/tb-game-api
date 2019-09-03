<?php
include 'vendor/autoload.php';

//use Pest;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

const UPDATE_SECS = 60;

function getPlayerActivitiesFromDB($playerID, $dtFirstActivityAllowed, $dtLastActivityAllowed, $activityType) {
  require_once 'lib/mysql.php';

  // use UTC date
  date_default_timezone_set("UTC");

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT activity, type, distance, total_elevation_gain, start_date FROM playerActivities where player = ' . $playerID . ' and start_date >= "' . $dtFirstActivityAllowed . '" and start_date <= "' . $dtLastActivityAllowed . '" order by start_date desc ');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    // only get activities of the correct type unless the game is 'all'
    // see if type is part of recorded type, so 'Ride' will also work with 'VirtualRide'
    $pos = strpos($row['type'], $activityType);
    if ($pos !== false || ($activityType == 'All')) {
      // format as UTC
      $dtStartDate = new DateTime($row['start_date']);
      $row['start_date'] = $dtStartDate->format('Y-m-d\TH:i:s.000\Z');

      $rows[$index] = $row;
      $index++;
    }
  }

  return $rows;
}

function addPlayerManualActivityToDB($playerID, $activity) {
    $db = mysqliSingleton::init();

    $strSQL = 'INSERT INTO playerActivities (player, activity, type, distance, total_elevation_gain, start_date) VALUES (' . $playerID . ', ' . $activity['id'] . ', "' . $activity['type'] . '", ' . $activity['distance'] . ', ' . $activity['total_elevation_gain'] . ', "' . $activity['start_date'] . '")';
    $result = $db->query($strSQL);
}

function addPlayerActivitiesToDB($playerID, $jsonActivities) {
  require_once 'lib/mysql.php';

  if ($jsonActivities) {
    $db = mysqliSingleton::init();
    foreach($jsonActivities as $activity) {
      // first check we don't already have the activity
      $result = $db->query('SELECT player, activity FROM playerActivities where player = ' . $playerID . ' and activity = ' . $activity['id']);
      if (!$result->num_rows) {
        $result = $db->query('INSERT INTO playerActivities (player, activity, type, distance, total_elevation_gain, start_date) VALUES (' . $playerID . ', ' . $activity['id'] . ', "' . $activity['type'] . '", ' . $activity['distance'] . ', ' . $activity['total_elevation_gain'] . ', "' . $activity['start_date'] . '")');
      }
    }
  }
}

function getPlayerActivity($token, $activityID) {
  $ret = [];

  try {
    // get from provider
    $adapter = new Pest('https://www.strava.com/api/v3');
    $service = new REST($token, $adapter);

    $client = new Client($service);

    $ret = $client->getActivity($activityID);
  } catch(\Exception $e) {
  }
  return $ret;
}

function getPlayerActivities($playerID, $startDate, $endDate, $activityType) {
  $ret = [];

  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();

  // first find last update date
  $results = getPlayerFromDB($db, $playerID);
  if (count($results) != 0) {
    // ensure we have the latest token
//    $accessToken = getStravaToken($playerID, $results[0]['providerAccessToken'], $results[0]['providerRefreshToken'], $results[0]['providerTokenExpires']);
//    echo $accessToken;

    $token = $results[0]['playerProviderToken'];

    $dtFirstActivityAllowed = $startDate;
    $dtLastActivityAllowed = $endDate;

    $dtLastUpdated = $results[0]['last_updated'];
    $dtLastActivity = $results[0]['last_activity'];

    // time to get new activities
    $tNow = strtotime($dtNow);
    $tLastUpdated = strtotime($dtLastUpdated);
    $nUpdatedSecondsAgo = abs($tNow - $tLastUpdated);

    $tLastActivity = strtotime($dtLastActivity);
    $tFirstActivityAllowed = strtotime($dtFirstActivityAllowed);
    $tLastActivityAllowed = strtotime($dtLastActivityAllowed);

    // check if we're now past when first activity is allowed and we haven't updated recently
    if ($tNow > $tFirstActivityAllowed && $nUpdatedSecondsAgo > UPDATE_SECS) {
      // only if the last recorded activity is before the last allowed
      if ($tLastActivity < $tLastActivityAllowed) {
        $activities = null;
        try {
          // get from provider
          $adapter = new Pest('https://www.strava.com/api/v3');
          $service = new REST($token, $adapter);

          $client = new Client($service);
          // before, after
          $activities = $client->getAthleteActivities(null, $tFirstActivityAllowed, 1, 100);
        } catch(\Exception $e) {
        }

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

    $ret = getPlayerActivitiesFromDB($playerID, $dtFirstActivityAllowed, $dtLastActivityAllowed, $activityType);
  }  
  return $ret;
}
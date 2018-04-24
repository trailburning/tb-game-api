<?php
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

  $fElevationGain = 0;
  $fDistance = 0;

  foreach ($arrPlayerActivities as $activity) {
    $fElevationGain += $activity['total_elevation_gain'];
    $fDistance += $activity['distance'];
  }
  // 1st activity is the most recent
  if (count($arrPlayerActivities)) {
    $dtLastActivity = $arrPlayerActivities[0]['start_date'];
  }

  // update totals
  setPlayerGameActivityTotalsInDB($gameID, $playerID, $fElevationGain, $fDistance);

  // has player reached or exceeded the ascent goal?
  if ($fElevationGain >= $gameResults[0]['ascent']) {
    setPlayerGameAscentCompleteInDB($gameID, $playerID, $dtLastActivity);
  }
  return $arrPlayerActivities;
}

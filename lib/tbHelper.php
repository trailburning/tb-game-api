<?php
function getPlayerGameProgress($playerID, $gameID) {
  $gameResults = getGameFromDB($gameID);

  $dtActivityStartDate = $gameResults[0]['game_start'];
  $dtActivityEndDate = $gameResults[0]['game_end'];

  // get player game details
  $gamePlayerResults = getGamePlayerFromDB($gameID, $playerID);
  if (count($gamePlayerResults)) {
    if (!is_null($gamePlayerResults[0]['challengeCompleted'])) {
      // use challenge date rather game end date
      $dtActivityEndDate = $gamePlayerResults[0]['challengeCompleted'];
    }
  }

  $arrPlayerActivities = getPlayerActivities($playerID, $dtActivityStartDate, $dtActivityEndDate, $gameResults[0]['type']);
/*
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

  // ascent or distance challenge?
  if ($gameResults[0]['distance'] > 0) {
    // has player reached or exceeded the distance goal?
    if ($fDistance >= $gameResults[0]['distance']) {
      setPlayerGameChallengeCompleteInDB($gameID, $playerID, $dtLastActivity);
    }
  }
  else {
    // has player reached or exceeded the ascent goal?
    if ($fElevationGain >= $gameResults[0]['ascent']) {
      setPlayerGameChallengeCompleteInDB($gameID, $playerID, $dtLastActivity);
    }
  }
*/
  return $arrPlayerActivities;
}

function getCurrencySymbol($currencyCode) {
  $currencySymbol = '$';

  switch (strtoupper($currencyCode)) {
    case 'GBP':
    case 'gbp':
      $currencySymbol = '£';
      break;

    case 'EUR':
    case 'eur':
      $currencySymbol = '€';
      break;

    case 'CHF':
    case 'chf':
      $currencySymbol = 'CHF';
      break;
  }
  return $currencySymbol;
}

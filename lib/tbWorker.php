<?php
const DAYS_INACTIVE = 7;

function processGamePlayer($game, $gamePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $dtNow = date('Y-m-d\TH:i:s.000\Z', time());
  $dtNowDate = new DateTime($dtNow);

  $gameID = $hashids->decode($game['id'])[0];
  $gamePlayerID = $hashids->decode($gamePlayer['id'])[0]; 
  if (DEBUG) echo 'Player:'. $gamePlayerID . ' : ' .  $gamePlayer['firstname'] . ' ' . $gamePlayer['lastname'] . '<br/>';

  // does player have a new activity?
  if ($gamePlayer['latest_activity']) {
    // check the activity exists
    $activity = getPlayerActivity($gamePlayer['playerProviderToken'], $gamePlayer['latest_activity']);
    if ($activity) {
      // reset activity
      setPlayerGameActivityInDB($gameID, $gamePlayerID, 0);
      // check activity type matches game type
      if ($activity['type'] == $game['type']) {
        // activiy puts player back to active if they were inactive
        if ($gamePlayer['state'] == GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE) {
          setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_PLAYING_STATE);
        }

        if (DEBUG) echo $activity['type'] . '<br/>';
        // get all game players
        $jsonPlayersResponse = getGamePlayersFromDB($gameID);
        if (count($jsonPlayersResponse)) {
          $bGamePlayerSummited = false;
          // get latest activities to update player progress
          getPlayerGameProgress($gamePlayerID, $gameID);
          // get player game details
          $gamePlayerResults = getGamePlayerFromDB($gameID, $gamePlayerID);
          if (count($gamePlayerResults)) {
            if (!is_null($gamePlayerResults[0]['ascentCompleted'])) {
              $bGamePlayerSummited = true;
            }
          }

          foreach ($jsonPlayersResponse as $player) {
            if ($player['game_notifications']) {
              sendActivityEmail($game, $player, $gamePlayer, $activity);
            }
              
            // has player summited and not already been processed?
            if ($bGamePlayerSummited && $gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
              setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_SUMMITED_STATE);
              if ($player['game_notifications']) {
                sendSummitEmail($game, $player, $gamePlayer);
              }
            }
          }
        }
      }
    }
  }
  else {
    // is player still playing?
    if ($gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
      if (DEBUG) echo 'check last activty<br/>';
      $dtLastActivityDate = new DateTime($gamePlayer['last_activity']);
      if (DEBUG) echo 'now:' . $dtNowDate->format('Y-m-d\TH:i:s.000\Z') . '<br/>';
      if (DEBUG) echo 'last activity:' . $dtLastActivityDate->format('Y-m-d\TH:i:s.000\Z') . '<br/>';

      $nDaysSinceLastActive = $dtNowDate->diff($dtLastActivityDate, true)->format('%R%a');
      if (DEBUG) echo 'days since last active:' . $nDaysSinceLastActive . '<br/>';

      // game start date
      $dtGameStartDate = new DateTime($game['game_start']);
      $nDaysSinceGameStart = $dtNowDate->diff($dtGameStartDate, true)->format('%R%a');
      if (DEBUG) echo 'days since game start:' . $nDaysSinceGameStart . '<br/>';

      // no activity for a few days?
      if ($nDaysSinceLastActive >= DAYS_INACTIVE && $nDaysSinceGameStart >= DAYS_INACTIVE) {
        // set as not active and prompt player
        setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE);        
        if (DEBUG) echo 'MOTIVATE EMAIL<br/>';      
        if ($gamePlayer['game_notifications']) {
          sendInactivityEmail($game, $gamePlayer);
        }
      }
    }
  }
}

function processActivity() {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  // use UTC date
  date_default_timezone_set("UTC");

  // get all games
  $jsonGamesResponse = getGamesFromDB();
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      // look for active games
      if ($game['game_state'] == STATE_GAME_ACTIVE) {
        if (DEBUG) echo '<br/>Game:' . $game['id'] . '<br/>';
        $gameID = $hashids->decode($game['id'])[0];
        // get game players
        $jsonGamePlayersResponse = getGamePlayersFromDB($gameID);
        if (count($jsonGamePlayersResponse)) {
          // go through all active game players
          foreach ($jsonGamePlayersResponse as $gamePlayer) {
            processGamePlayer($game, $gamePlayer);
          }
        }
      }
    }
  }
}
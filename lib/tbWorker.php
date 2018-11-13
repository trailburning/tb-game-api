<?php
const DAYS_INACTIVE = 7;

function processGamePlayer($log, $gameID, $game, $gamePlayerID, $gamePlayer) {
  $dtNow = date('Y-m-d\TH:i:s.000\Z', time());
  $dtNowDate = new DateTime($dtNow);

  $db = connect_db();  

  if (DEBUG) echo 'Player:'. $gamePlayerID . ' : ' .  $gamePlayer['firstname'] . ' ' . $gamePlayer['lastname'] . '<br/>';

  // does player have a new activity?
  if ($gamePlayer['latest_activity']) {
    if (DEBUG) echo 'Player:' . $gamePlayer['latest_activity'] . '<br/>';
    $log->info('Player Activity');
    // check the activity exists
    $activity = getPlayerActivity($gamePlayer['playerProviderToken'], $gamePlayer['latest_activity']);
    if ($activity) {
      if (DEBUG) echo 'Found Player Activity<br/>';
      $log->info('Player Activity - Found');
      // reset activity
      setPlayerGameActivityInDB($gameID, $gamePlayerID, 0);
      // check activity type matches game type
      if ($activity['type'] == $game['type']) {
        addLogToDB($db, LOG_OBJECT_PLAYER, LOG_ACTIVITY_GAME_ACTIVITY, $gamePlayerID);

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
            if (DEBUG) echo 'PLAYER ACTIVITY EMAIL<br/>';
            $log->info('Activity Email');
            if ($player['game_notifications']) {
              $jsonEmail = $game['email_activity_broadcast'];
              if ($player['id'] == $gamePlayer['id']) {
                $jsonEmail = $game['email_activity'];
              }              
              sendActivityEmail($jsonEmail, $game, $player, $gamePlayer, $activity);
            }
              
            // has player summited and not already been processed?
            if ($bGamePlayerSummited && $gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
              if (DEBUG) echo 'PLAYER SUMMIT EMAIL<br/>';
              setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_SUMMITED_STATE);
              if ($gamePlayer['game_notifications']) {
                $jsonEmail = $game['email_summit_broadcast'];
                if ($player['id'] == $gamePlayer['id']) {
                  $jsonEmail = $game['email_summit'];
                }
                sendSummitEmail($jsonEmail, $game, $player, $gamePlayer);
              }
            }
          }
        }
      }
    }
  }
  else {
    $log->info('Player No Activity');
    // is player still playing?
    if ($gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
      if (DEBUG) echo 'check last activity<br/>';
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
          $jsonEmail = $game['email_inactivity'];          
          sendInactivityEmail($jsonEmail, $game, $gamePlayer);
        }
      }
      else { // has player summited?
        $jsonPlayersResponse = getGamePlayersFromDB($gameID);
        if (count($jsonPlayersResponse)) {
          $bGamePlayerSummited = false;

          // get player game details
          $gamePlayerResults = getGamePlayerFromDB($gameID, $gamePlayerID);
          if (count($gamePlayerResults)) {
            if (!is_null($gamePlayerResults[0]['ascentCompleted'])) {
              $bGamePlayerSummited = true;
            }
          }

          foreach ($jsonPlayersResponse as $player) {
            // has player summited and not already been processed?
            if ($bGamePlayerSummited && $gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
              if (DEBUG) echo 'PLAYER SUMMIT EMAIL<br/>';
              setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_SUMMITED_STATE);
              if ($gamePlayer['game_notifications']) {
                $jsonEmail = $game['email_summit_broadcast'];
                if ($player['id'] == $gamePlayer['id']) {
                  $jsonEmail = $game['email_summit'];
                }
                sendSummitEmail($jsonEmail, $game, $player, $gamePlayer);
              }
            }
          }
        }
      }
    }
  }
}

function processActivity($log) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  // use UTC date
  date_default_timezone_set("UTC");

  $log->info('Start');

  // get all games
  $jsonGamesResponse = getActiveGamesFromDB();
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {      
      if (DEBUG) echo '<br/>Game:' . $game['id'] . '<br/>';
      $gameID = $hashids->decode($game['id'])[0];
      // get game players
      $jsonGamePlayersResponse = getGamePlayersFromDB($gameID);
      if (count($jsonGamePlayersResponse)) {
        // go through all active game players
        foreach ($jsonGamePlayersResponse as $gamePlayer) {
          $gamePlayerID = $hashids->decode($gamePlayer['id'])[0]; 
          processGamePlayer($log, $gameID, $game, $gamePlayerID, $gamePlayer);
        }
      }
    }
  }

  $log->info('End');

  return $jsonGamesResponse;  
}
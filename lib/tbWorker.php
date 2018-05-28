<?php
function processGamePlayer($game, $gamePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $dtNow = date('Y-m-d\TH:i:s.000\Z', time());
  $dtNowDate = new DateTime($dtNow);

  $gameID = $hashids->decode($game['id'])[0];
  $gamePlayerID = $hashids->decode($gamePlayer['id'])[0]; 
//  echo 'Player:'. $gamePlayerID . ' : ' .  $gamePlayer['firstname'] . ' ' . $gamePlayer['lastname'] . '<br/>';

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

//        echo $activity['type'] . '<br/>';
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
              sendActivityEmail($game, $player, $gamePlayer);
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
//    echo 'last activty:' . $gamePlayer['last_activity'] . '<br/>';
    // is player still playing?
    if ($gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
//      echo 'check last activty<br/>';
      $dtLastActivityDate = new DateTime($gamePlayer['last_activity']);
//      echo $dtNowDate->format('Y-m-d\TH:i:s.000\Z') . ' : ' . $dtLastActivityDate->format('Y-m-d\TH:i:s.000\Z') . '<br/>';

      $interval = $dtNowDate->diff($dtLastActivityDate);
      $nDays = $interval->format('%R%a');
//      echo $nDays . '<br/>';
      // no activity for a few days?
      if ($nDays < -5) {
        // set as not active and prompt player
        setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE);        
//        echo 'MOTIVATE EMAIL<br/>';      
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
//        echo '<br/>Game:' . $game['id'] . '<br/>';
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
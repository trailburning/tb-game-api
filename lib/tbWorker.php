<?php
function processActivity() {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  // get all games
  $jsonGamesResponse = getGamesFromDB();
  if (count($jsonGamesResponse)) {
    foreach ($jsonGamesResponse as $game) {
      // look for active games
      if ($game['game_state'] == STATE_GAME_ACTIVE) {
        $gameID = $hashids->decode($game['id'])[0];
        // look for player activity in game
        $jsonPlayerActivityResponse = getPlayerActivtyByGameFromDB($gameID);
        if (count($jsonPlayerActivityResponse)) {
          // go through all active game players
          foreach ($jsonPlayerActivityResponse as $activePlayer) {
            // check the activity exists
            $activity = getPlayerActivity($activePlayer['playerProviderToken'], $activePlayer['latest_activity']);
            if ($activity) {
              // reset activity
              setPlayerGameActivityInDB($gameID, $activePlayer['id'], 0);
              // check activity type matches game type
              if ($activity['type'] == $game['type']) {
                // get all game players
                $jsonPlayersResponse = getGamePlayersFromDB($gameID);
                if (count($jsonPlayersResponse)) {
                  $bActivePlayerSummited = false;
                  // get latest activities to update player progress
                  getPlayerGameProgress($activePlayer['id'], $gameID);
                  // get player game details
                  $gamePlayerResults = getGamePlayerFromDB($gameID, $activePlayer['id']);
                  if (count($gamePlayerResults)) {
                    if (!is_null($gamePlayerResults[0]['ascentCompleted'])) {
                      $bActivePlayerSummited = true;
                    }
                  }

                  foreach ($jsonPlayersResponse as $player) {
                    if ($player['game_notifications']) {
                      sendActivityEmail($game, $player, $activePlayer);
                      // has player summited and not already been processed?
                      if ($bActivePlayerSummited && $activePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
                        setPlayerGameStateInDB($gameID, $activePlayer['id'], GAME_PLAYER_SUMMITED_STATE);
                        sendSummitEmail($game, $player, $activePlayer);
                      }
                    }
                  }
                }
              }
            }
          }
        }
      }
    }
  }
}
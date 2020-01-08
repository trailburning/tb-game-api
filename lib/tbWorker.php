<?php
const DAYS_INACTIVE = 7;

function processGamePlayer($log, $campaign, $campaignEmails, $gameID, $game, $gamePlayerID, $gamePlayer, $bGameCompleted) {
  $dtNow = date('Y-m-d\TH:i:s.000\Z', time());
  $dtNowDate = new DateTime($dtNow);

  $db = connect_db();  

  if (DEBUG) echo 'Player:'. $gamePlayerID . ' : ' .  $gamePlayer['firstname'] . ' ' . $gamePlayer['lastname'] . '<br/>';

  // does player have a new activity?
  if ($gamePlayer['latest_activity']) {
    if (DEBUG) echo 'Player:' . $gamePlayer['latest_activity'] . '<br/>';
//    $log->warning('Player Activity');

    // ensure we have the latest token
    $token = StravaGetToken($gamePlayerID, $gamePlayer['providerAccessToken'], $gamePlayer['providerRefreshToken'], $gamePlayer['providerTokenExpires']);

    if (DEBUG) echo 'Strava Token:' . $token . '<br/>';

    // check the activity exists
    $activity = getPlayerActivity($token, $gamePlayer['latest_activity']);

    if ($activity) {
      if (DEBUG) echo 'Found Player Activity<br/>';
//      $log->warning('Player Activity - Found');
      // reset activity
      setPlayerGameActivityInDB($gameID, $gamePlayerID, 0);

      // check activity type matches game type unless the game is 'all'
      // see if type is part of recorded type, so 'Ride' will also work with 'VirtualRide'
      $pos = strpos($activity['type'], $game['type']);
      if ($pos !== false || ($game['type'] == 'All')) {
        addLogToDB($db, LOG_OBJECT_PLAYER, LOG_ACTIVITY_GAME_ACTIVITY, $gamePlayerID);

        // activiy puts player back to active if they were inactive
        if ($gamePlayer['state'] == GAME_PLAYER_PLAYING_NOT_ACTIVE_STATE) {
          setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_PLAYING_STATE);
        }

        if (DEBUG) echo $activity['type'] . '<br/>';
        // get all game players
        $jsonPlayersResponse = getGamePlayersFromDB($gameID);
        if (count($jsonPlayersResponse)) {
          $bGamePlayerChallengeCompleted = false;
          // get latest activities to update player progress
          getPlayerGameProgress($gamePlayerID, $gameID);
          // get player game details
          $gamePlayerResults = getGamePlayerFromDB($gameID, $gamePlayerID);
          if (count($gamePlayerResults)) {
            if (!is_null($gamePlayerResults[0]['challengeCompleted'])) {
              $bGamePlayerChallengeCompleted = true;
            }
          }

          foreach ($jsonPlayersResponse as $player) {
            if (DEBUG) echo 'PLAYER ACTIVITY EMAIL<br/>';
//            $log->warning('Activity Email');
            if ($player['game_notifications']) {
              $jsonEmail = $campaignEmails['email_activity_broadcast'];
              // distance based challenge so use distance email template
              if ($game['distance'] > 0) {
                $jsonEmail = $campaignEmails['email_activity_broadcast_distance'];
              }

              if ($player['id'] == $gamePlayer['id']) {
                $jsonEmail = $campaignEmails['email_activity'];
                // distance based challenge so use distance email template
                if ($game['distance'] > 0) {
                  $jsonEmail = $campaignEmails['email_activity_distance'];
                }                
              }              
              sendActivityEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $gamePlayer, $activity);
            }
              
            // has player completed the challenge and not already been processed?
            if ($bGamePlayerChallengeCompleted && $gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
              if (DEBUG) echo 'PLAYER CHALLENGE COMPLETE EMAIL<br/>';
              setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_COMPLETED_CHALLENGE_STATE);
              if ($gamePlayer['game_notifications']) {
                $jsonEmail = $campaignEmails['email_summit_broadcast'];
                // distance based challenge so use distance email template
                if ($game['distance'] > 0) {
                  $jsonEmail = $campaignEmails['email_distance_complete_broadcast'];
                }

                if ($player['id'] == $gamePlayer['id']) {
                  $jsonEmail = $campaignEmails['email_summit'];

                  // distance based challenge so use distance email template
                  if ($game['distance'] > 0) {
                    $jsonEmail = $campaignEmails['email_challenge_complete'];
                  }
                }
                sendSummitEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $gamePlayer);
              }
            }
          }
        }
      }
    }
  }
  else {
//    $log->warning('Player No Activity');
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
          $jsonEmail = $campaignEmails['email_inactivity'];          
          sendInactivityEmail($campaignEmails['email_template'], $jsonEmail, $game, $gamePlayer);
        }
      }
      else { // has player completed the challenge?
        $jsonPlayersResponse = getGamePlayersFromDB($gameID);
        if (count($jsonPlayersResponse)) {
          $bGamePlayerChallengeCompleted = false;

          // get player game details
          $gamePlayerResults = getGamePlayerFromDB($gameID, $gamePlayerID);
          if (count($gamePlayerResults)) {
            if (!is_null($gamePlayerResults[0]['challengeCompleted'])) {
              $bGamePlayerChallengeCompleted = true;
            }
          }

          foreach ($jsonPlayersResponse as $player) {
            // has player completed the challenge and not already been processed?
            if ($bGamePlayerChallengeCompleted && $gamePlayer['state'] == GAME_PLAYER_PLAYING_STATE) {
              if (DEBUG) echo 'PLAYER COMPLETED CHALLENGE EMAIL<br/>';
              setPlayerGameStateInDB($gameID, $gamePlayerID, GAME_PLAYER_COMPLETED_CHALLENGE_STATE);
              if ($gamePlayer['game_notifications']) {
                $jsonEmail = $campaignEmails['email_summit_broadcast'];
                if ($player['id'] == $gamePlayer['id']) {
                  $jsonEmail = $campaignEmails['email_summit'];
                }
                sendSummitEmail($campaignEmails['email_template'], $jsonEmail, $game, $player, $gamePlayer);
              }
            }
          }
        }
      }
    }
  }

  // process completed game
  if ($bGameCompleted) {
    if (DEBUG) echo 'GAME FINISHED<br/>';
    $jsonEmail = $campaignEmails['email_finished'];
    sendFinishedEmail($campaignEmails['email_template'], $jsonEmail, $game, $gamePlayer);
  }
}

function processActivity($log) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");

//  $log->warning('Start');

  // get all campaigns
  $jsonCampaignsResponse = getCampaignsFromDB();
  if (count($jsonCampaignsResponse)) {
    foreach ($jsonCampaignsResponse as $campaign) {      
      $campaignID = $hashids->decode($campaign['id'])[0];

      // get campaign emails
      $jsonCampaignEmailsResponse = getCampaignEmailsFromDB($campaignID);
      if (count($jsonCampaignEmailsResponse)) {
        // 190307 mla - currentl uses 1st emails but should use lang to pick correct ones.
        $campaignEmails = $jsonCampaignEmailsResponse[0];

        // get all campaign games
        $jsonGamesResponse = getActiveGamesByCampaignIDFromDB($campaignID);
        if (count($jsonGamesResponse)) {
          foreach ($jsonGamesResponse as $game) {      
            $gameID = $hashids->decode($game['id'])[0];
            $bGameCompleted = false;
            $nPlayersCompletedChallenge = 0;

            // process game state
            $dtGameEndDate = new DateTime($game['game_end']);
            if (($game['state'] == GAME_READY_STATE) && ($dtNow > $dtGameEndDate)) {
              $bGameCompleted = true;

              setGameStateInDB($gameID, GAME_COMPLETE_STATE);
            }

            // get game players
            $jsonGamePlayersResponse = getGamePlayersFromDB($gameID);
            if (DEBUG) echo '<br/>Game: ' . $game['id'] . ' Players: ' . count($jsonGamePlayersResponse) . '<br/>';
            if (count($jsonGamePlayersResponse)) {
              // go through all active game players
              foreach ($jsonGamePlayersResponse as $gamePlayer) {
                $gamePlayerID = $hashids->decode($gamePlayer['id'])[0]; 
                processGamePlayer($log, $campaign, $campaignEmails, $gameID, $game, $gamePlayerID, $gamePlayer, $bGameCompleted);

                if ($gamePlayer['state'] == GAME_PLAYER_COMPLETED_CHALLENGE_STATE) {
                  $nPlayersCompletedChallenge++;
                }
              }
              // have all players completed the challenge?
              if (count($jsonGamePlayersResponse) == $nPlayersCompletedChallenge) {
                // close game
                setGameToCloseInDB($gameID);
              }
            }
          }
        }
      }
    }
  }

//  $log->warning('End');

  return $jsonCampaignsResponse;  
}
<?php
function getCampaignKPITotalClimbersFromDB($campaignID) {
  $strSQL = 'SELECT count(*) as total FROM gamePlayers JOIN games ON gamePlayers.game = games.id WHERE campaignID = ' . $campaignID;

  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalFundraisingClimbersFromDB($campaignID) {
  $strSQL = 'SELECT count(fundraising_goal) as total FROM gamePlayers JOIN games ON gamePlayers.game = games.id WHERE campaignID = ' . $campaignID . ' and fundraising_goal > 0';

  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalFundraisingGoalFromDB($campaignID) {
  $strSQL = 'SELECT sum(fundraising_goal) as total FROM gamePlayers JOIN games ON gamePlayers.game = games.id WHERE campaignID = ' . $campaignID;

  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalFundraisingRaisedFromDB($campaignID) {
  $strSQL = 'SELECT sum(fundraising_raised) as total FROM gamePlayers JOIN games ON gamePlayers.game = games.id WHERE campaignID = ' . $campaignID;

  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalActiveGamesFromDB($campaignID) {
  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");
  $formattedNow = $dtNow->format('Y-m-d\TH:i:s.000\Z');

  $strSQL = 'select count(id) as total from games where campaignID = ' . $campaignID . ' and game_start < "' . $formattedNow . '" and game_end > "' . $formattedNow . '"';

  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalPendingGamesFromDB($campaignID) {
  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");
  $formattedNow = $dtNow->format('Y-m-d\TH:i:s.000\Z');

  $strSQL = 'select count(id) as total from games where campaignID = ' . $campaignID . ' and game_start > "' . $formattedNow . '"';
  
  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalActivitiesFromDB($campaignID) {
  $strSQL = 'select type, count(type) as total from games where campaignID = ' . $campaignID . ' group by type order by total desc';

  return getResultsFromDB($strSQL);
}

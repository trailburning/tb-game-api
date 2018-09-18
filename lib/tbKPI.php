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

function getCampaignKPITotalActiveCampaignsFromDB($campaignID) {
  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");
  $formattedNow = $dtNow->format('Y-m-d\TH:i:s.000\Z');

  $db = connect_db();
  $strSQL = 'select count(id) as total from games where campaignID = ' . $campaignID . '  and game_start < "' . $formattedNow . '" and game_end > "' . $formattedNow . '"';
  $result = $db->query($strSQL);

  return getResultsFromDB($strSQL);
}

function getCampaignKPITotalPendingCampaignsFromDB($campaignID) {
  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = new DateTime("now");
  $formattedNow = $dtNow->format('Y-m-d\TH:i:s.000\Z');

  $db = connect_db();
  $strSQL = 'select count(id) as total from games where campaignID = ' . $campaignID . '  and game_start > "' . $formattedNow . '"';
  $result = $db->query($strSQL);

  return getResultsFromDB($strSQL);
}

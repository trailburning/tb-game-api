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

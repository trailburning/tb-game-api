<?php
function getCampaignFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT clients.id as clientID, clients.name as client_name, clients.shortname as client_shortname, campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, campaigns.fundraising_provider, campaigns.fundraising_donation, campaigns.fundraising_page, campaigns.fundraising_charity, campaigns.fundraising_event FROM campaigns JOIN clients ON campaigns.clientID = clients.id WHERE campaigns.id = ' . $campaignID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);
    $row['clientID'] = $hashids->encode($row['clientID']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignSummaryFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, sum(ascent) as total_ascent, sum(distance) as total_distance, sum(fundraising_raised) as total_fundraising_raised FROM campaigns JOIN games ON campaigns.id = games.campaignID JOIN gamePlayers on games.id = gamePlayers.game WHERE campaigns.id = ' . $campaignID . ' GROUP BY campaigns.id'); 
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignByGameFromDB($gameID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT clients.id as clientID, clients.name as client_name, clients.shortname as client_shortname, campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, campaigns.fundraising_provider, campaigns.fundraising_donation, campaigns.fundraising_page, campaigns.fundraising_charity, campaigns.fundraising_event FROM games JOIN campaigns ON games.campaignID = campaigns.id JOIN clients ON campaigns.clientID = clients.id WHERE games.id = ' . $gameID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);
    $row['clientID'] = $hashids->encode($row['clientID']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignGameLevelsFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT gameLevels.id, name, region, ascent, seasonDefault FROM gameLevels JOIN campaignGameLevels ON gameLevels.id = campaignGameLevels.gameLevelID WHERE campaignGameLevels.campaignID = ' . $campaignID . ' order by ascent');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}
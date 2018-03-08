<?php
function getCampaignFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT clients.id as clientID, clients.name as client_name, campaigns.id, campaigns.name, campaigns.shortname, campaigns.template, campaigns.fundraising_provider, campaigns.fundraising_charity, campaigns.fundraising_event FROM campaigns JOIN clients ON campaigns.clientID = clients.id WHERE campaigns.id = ' . $campaignID);
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

function getCampaignByGameFromDB($gameID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = connect_db();
  $result = $db->query('SELECT clients.id as clientID, clients.name as client_name, campaigns.id, campaigns.name, campaigns.shortname, campaigns.template, campaigns.fundraising_provider, campaigns.fundraising_charity, campaigns.fundraising_event FROM games JOIN campaigns ON games.campaignID = campaigns.id JOIN clients ON campaigns.clientID = clients.id WHERE games.id = ' . $gameID);
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

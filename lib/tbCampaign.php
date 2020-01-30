<?php
function getCampaignsFromDB() {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $strSQL = 'SELECT clients.id as clientID, clients.name as client_name, clients.shortname as client_shortname, clients.description as client_description, campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, campaigns.juicer_feed, campaigns.fundraising_currency, campaigns.fundraising_minimum, campaigns.fundraising_provider, campaigns.fundraising_donation, campaigns.fundraising_page, campaigns.fundraising_charity, campaigns.fundraising_event, campaigns.invitation_code FROM campaigns JOIN clients ON campaigns.clientID = clients.id';
  $result = $db->query($strSQL);
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

function getCampaignFromDB($db, $campaignID) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $strSQL = 'SELECT clients.id as clientID, clients.name as client_name, clients.shortname as client_shortname, clients.description as client_description, campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, campaigns.juicer_feed, campaigns.start_date, campaigns.end_date, campaigns.fundraising_currency, campaigns.fundraising_minimum, campaigns.fundraising_provider, campaigns.fundraising_donation, campaigns.fundraising_page, campaigns.fundraising_charity, campaigns.fundraising_event, campaigns.paywall_amount, campaigns.paywall_currency, campaigns.invitation_code FROM campaigns JOIN clients ON campaigns.clientID = clients.id WHERE campaigns.id = ' . $campaignID;
  $result = $db->query($strSQL);
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

function getCampaignPlayersPaywallSummaryFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $strSQL = 'SELECT campaigns.fundraising_currency, sum(campaignplayerspaywall.paywall_amount) as total_paywall_amount from campaignplayerspaywall JOIN campaigns on campaignplayerspaywall.campaign = campaigns.id where campaign = ' . $campaignID;
  $result = $db->query($strSQL);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignDonationsSummaryFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $strSQL = 'SELECT campaigns.fundraising_currency, sum(donation_amount) as total_donations from campaigndonation JOIN campaigns on campaigndonation.campaign = campaigns.id where campaign = ' . $campaignID;
  $result = $db->query($strSQL);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignSummaryFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $strSQL = 'SELECT campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, campaigns.fundraising_currency, sum(ascent) as total_ascent, sum(distance) as total_distance, sum(fundraising_raised) as total_fundraising_raised FROM campaigns JOIN games ON campaigns.id = games.campaignID JOIN gamePlayers on games.id = gamePlayers.game WHERE campaigns.id = ' . $campaignID . ' GROUP BY campaigns.id';
  $result = $db->query($strSQL);
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

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT clients.id as clientID, clients.name as client_name, clients.shortname as client_shortname, clients.description as client_description, campaigns.id, campaigns.name, campaigns.shortname, campaigns.description, campaigns.template, campaigns.juicer_feed, campaigns.fundraising_currency, campaigns.fundraising_minimum, campaigns.fundraising_provider, campaigns.fundraising_donation, campaigns.fundraising_page, campaigns.fundraising_charity, campaigns.fundraising_event, campaigns.invitation_code FROM games JOIN campaigns ON games.campaignID = campaigns.id JOIN clients ON campaigns.clientID = clients.id WHERE games.id = ' . $gameID);
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

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT gameLevels.id, name, region, ascent, distance, image, seasonDefault, mountainType, multiplayer, sponsored FROM gameLevels JOIN campaignGameLevels ON gameLevels.id = campaignGameLevels.gameLevelID WHERE campaignGameLevels.campaignID = ' . $campaignID . ' order by ascent');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignGameActivityTypesFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT gameActivityTypes.id, name, description FROM gameActivityTypes JOIN campaignGameActivityTypes ON gameActivityTypes.id = campaignGameActivityTypes.gameActivityTypeID WHERE campaignGameActivityTypes.campaignID = ' . $campaignID . ' order by preferredOrder');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignGameDurationsFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT gameDurations.id, days, description FROM gameDurations JOIN campaignGameDurations ON gameDurations.id = campaignGameDurations.gameDurationID WHERE campaignGameDurations.campaignID = ' . $campaignID . ' order by preferredOrder');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignCodeFromDB($campaignID, $strCode) {
  require_once 'lib/mysql.php';

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT invitation_code FROM campaignCodes WHERE campaignID = ' . $campaignID . ' and invitation_code = "' . $strCode . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getLanguageFromDBByName($langName) {
  require_once 'lib/mysql.php';

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT id, name, description FROM languages WHERE name = "' . $langName . '"');
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignLanguagesFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT languages.name, languages.description FROM campaignlanguages JOIN languages ON campaignlanguages.languageID = languages.id WHERE campaignlanguages.campaignID = ' . $campaignID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignEmailsFromDB($campaignID) {
  $db = mysqliSingleton::init();
  $result = $db->query('SELECT languages.name as language_name, campaignemails.email_template, campaignemails.email_welcome, campaignemails.email_welcome_distance, campaignemails.email_finished, campaignemails.email_activity, campaignemails.email_activity_distance, campaignemails.email_activity_broadcast, campaignemails.email_activity_broadcast_distance, campaignemails.email_inactivity, campaignemails.email_summit, campaignemails.email_distance_complete, campaignemails.email_summit_broadcast, campaignemails.email_distance_complete_broadcast, campaignemails.email_invite, campaignemails.email_fundraising_donation FROM campaignemails JOIN languages ON campaignemails.languageID = languages.id WHERE campaignID = ' . $campaignID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getCampaignPlayersPaywallFromDB($campaignID, $playerID) {
  $db = connect_db();

  $result = $db->query('SELECT created, paywall_amount FROM campaignplayerspaywall where campaign = ' . $campaignID . ' and player = ' . $playerID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function setCampaignPlayerPaywallInDB($campaignID, $playerID, $fPaywallAmount, $paywallPaymentID) {
  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();

  $strSQL = 'INSERT INTO campaignplayerspaywall (campaign, player, created, paywall_amount, paywall_payment_id) VALUES (' . $campaignID . ', ' . $playerID . ', "' . $dtNow . '", ' . $fPaywallAmount . ', "' . $paywallPaymentID . '")';
  $db->query($strSQL);
}

function setCampaignDonationInDB($campaignID, $fDonationAmount, $donationPaymentID) {
  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();

  $strSQL = 'INSERT INTO campaigndonation (campaign, created, donation_amount, donation_payment_id) VALUES (' . $campaignID . ', "' . $dtNow . '", ' . $fDonationAmount . ', "' . $donationPaymentID . '")';
  $db->query($strSQL);
}

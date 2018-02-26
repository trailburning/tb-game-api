<?php
function getCampaignFromDB($campaignID) {
  require_once 'lib/mysql.php';

  $db = connect_db();
  $result = $db->query('SELECT id, name, shortname, fundraising_provider, fundraising_charity, fundraising_event FROM campaigns WHERE id = ' . $campaignID);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

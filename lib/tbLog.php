<?php
include 'vendor/autoload.php';

define('LOG_OBJECT_CLIENT', 0);
define('LOG_OBJECT_CAMPAIGN', 1);
define('LOG_OBJECT_GAME', 2);
define('LOG_OBJECT_PLAYER', 3);
define('LOG_OBJECT_PLAYER_PROVIDER', 4);
define('LOG_OBJECT_FUNDRAISING', 5);

define('LOG_ACTIVITY_CREATE', 0);
define('LOG_ACTIVITY_UPDATE', 1);
define('LOG_ACTIVITY_DELETE', 2);

define('LOG_ACTIVITY_INVITATION_ACCEPT', 100);
define('LOG_ACTIVITY_INVITATION_REJECT', 101);

function addLogToDB($nObject, $nActivity, $objectID) {
  require_once 'lib/mysql.php';

  // use UTC date
  date_default_timezone_set("UTC");
  $dtNow = date('Y-m-d H:i:s', time());

  $db = connect_db();
  $db->query('INSERT INTO log (created, object, activity, object_id) VALUES ("' . $dtNow . '", ' . $nObject . ', ' . $nActivity . ', ' . $objectID . ')');
}

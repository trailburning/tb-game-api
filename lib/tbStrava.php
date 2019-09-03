<?php
include 'vendor/autoload.php';

use Strava\API\OAuth;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

define('EXPIRY_THRESHOLD_SECONDS', 3600);

function getStravaToken($playerID, $providerAccessToken, $providerRefreshToken, $providerTokenExpires) {
  // use UTC date
  date_default_timezone_set("UTC");

  $dtExpire = new DateTime("@$providerTokenExpires");
  $tExpire = strtotime($dtExpire->format('Y-m-d H:i:s'));

  $dtNow = new DateTime();
  $tNow = strtotime($dtNow->format('Y-m-d H:i:s'));

//  echo $tExpire - $tNow . ' : ' . EXPIRY_THRESHOLD_SECONDS;

  if (($tExpire - $tNow) < EXPIRY_THRESHOLD_SECONDS) {
    try {
      $options = array(
        'clientId'     => CLIENT_ID,
        'clientSecret' => CLIENT_SECRET
      );

      $oauth = new OAuth($options);
      // grab refresh token with forever token
      $tokenData = $oauth->getAccessToken('refresh_token', array('refresh_token' => $providerRefreshToken));

      // update tokens
      updatePlayerProviderTokensInDB($playerID, $tokenData->getToken(), $tokenData->getRefreshToken(), $tokenData->getExpires());
    } catch(Exception $e) {
      print $e->getMessage();
    }

    return $providerAccessToken;
  }
}
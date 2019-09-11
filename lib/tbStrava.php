<?php
include 'vendor/autoload.php';

define('CLIENT_ID', 15175);
define('CLIENT_SECRET', 'f3d284154c0b25200f074bc1a46ccc06920f9ed6');

define('EXPIRY_THRESHOLD_SECONDS', 3600);

use Strava\API\OAuth;
use Strava\API\Client;
use Strava\API\Exception;
use Strava\API\Service\REST;

function StravaSubscribe($strGameAPIDomain) {
  $url = 'https://api.strava.com/api/v3/push_subscriptions';

  $fields = array(
    'client_id' => CLIENT_ID,
    'client_secret' => CLIENT_SECRET,
    'object_type' => 'activity',
    'aspect_type' => 'create',
    'callback_url' => $strGameAPIDomain . 'strava/callback',
    'verify_token' => 'STRAVA'
  );

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch,CURLOPT_POST, count($fields));
  curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($fields));
  curl_exec($ch);
  curl_close($ch);
}

function StraveUnsubscribe($ID) {
  $url = 'https://api.strava.com/api/v3/push_subscriptions/' . $ID . '?client_id=' . CLIENT_ID . '&client_secret=' . CLIENT_SECRET;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
  curl_exec($ch);
  curl_close($ch);
}

function StravaGetSubscriptions() {
  $url = 'https://api.strava.com/api/v3/push_subscriptions?client_id=' . CLIENT_ID . '&client_secret=' . CLIENT_SECRET;

  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_exec($ch);
  curl_close($ch);
}

function StravaUpdateTokens() {
  $jsonPlayersResponse = getPlayersFromDB();
  if (count($jsonPlayersResponse)) {
    foreach ($jsonPlayersResponse as $player) {

      if (!$player['providerRefreshToken']) {
        $playerID = $player['id'];

        echo 'player: ' . $player['id'] . ' : ' . $player['lastname'] . '<br/>';

        $token = StravaGetToken($playerID, $player['providerAccessToken'], $player['providerRefreshToken'], $player['providerTokenExpires'], 99);
      }

    }
  }
}

function StravaGetOAuth($strSiteDomain, $hashCampaignID) {
  $jsonResponse = array();

  try {
    $options = array(
      'clientId'     => CLIENT_ID,
      'clientSecret' => CLIENT_SECRET,
      'redirectUri'  => $strSiteDomain . 'campaign/' . $hashCampaignID . '/register'
    );

    $oauth = new OAuth($options);
//    $oauth_connect = $oauth->getAuthorizationUrl(array('scope' => 'public'));      
    $oauth_connect = $oauth->getAuthorizationUrl(array('scope' => 'read,activity:read'));      

    $jsonResponse['oauthConnectURL'] = $oauth_connect;
  } catch(Exception $e) {
    print $e->getMessage();
  }

  return $jsonResponse;
}

function StravaGetOAuthToken($strSiteDomain, $hashCampaignID, $stravaCode) {
  $jsonResponse = array();

  try {
    $options = array(
      'clientId'     => CLIENT_ID,
      'clientSecret' => CLIENT_SECRET,
      'redirectUri'  => $strSiteDomain . 'campaign/' . $hashCampaignID . '/register'
    );

    $oauth = new OAuth($options);
//    $oauth_connect = $oauth->getAuthorizationUrl(array('scope' => 'public'));      
    $oauth_connect = $oauth->getAuthorizationUrl(array('scope' => 'read,activity:read'));      
    $jsonResponse['oauthConnectURL'] = $oauth_connect;

    $tokenData = $oauth->getAccessToken('authorization_code', array('code' => $stravaCode));
    $token = $tokenData->getToken();

    $athlete = $tokenData->getValues()['athlete'];
    $jsonResponse['athlete'] = $athlete;

    // no refresh token means we're using a forever token
    if (!$tokenData->getRefreshToken()) {
      // grab refresh token with forever token
      $tokenData = $oauth->getAccessToken('refresh_token', array('refresh_token' => $token));
    }

    $db = connect_db();
    $jsonCampaignResponse = getCampaignFromDB($db, $campaignID);
    if (count($jsonCampaignResponse)) {
      $clientID = $hashids->decode($jsonCampaignResponse[0]['clientID'])[0];

      $jsonPlayer = addPlayerToDB($clientID, $athlete['profile'], $athlete['firstname'], $athlete['lastname'], '', $athlete['city'], $athlete['country'], $athlete['id'], $token);
      $jsonResponse['playerID'] = $hashids->encode($jsonPlayer[0]['id']);

      // update tokens
      updatePlayerProviderTokensInDB($jsonPlayer[0]['id'], $tokenData->getToken(), $tokenData->getRefreshToken(), $tokenData->getExpires());
    }
  } catch(Exception $e) {
    print $e->getMessage();
  }

  return $jsonResponse;
}

function StravaGetToken($playerID, $providerAccessToken, $providerRefreshToken, $providerTokenExpires, $test) {
  echo 'StravaGetToken:' . $playerID . ':' . $providerAccessToken . ':' . $providerRefreshToken . ':' . $providerTokenExpires;

  // use UTC date
  date_default_timezone_set("UTC");

  $dtExpire = new DateTime();
  // not yet using short term token, so use forever token to get short term
  if (!$providerTokenExpires) {
    $results = getPlayerFromDBByID($playerID);
    if (count($results) != 0) {
      $providerRefreshToken = $results[0]['playerProviderToken'];
    }
  }
  else {
    $dtExpire = new DateTime("@$providerTokenExpires");
  }

  $tExpire = strtotime($dtExpire->format('Y-m-d H:i:s'));

  $dtNow = new DateTime();
  $tNow = strtotime($dtNow->format('Y-m-d H:i:s'));

  echo 'token:' . $providerRefreshToken . '<br/>';

  // if expired or about to expire then we need a new token
  if (($tExpire - $tNow) < EXPIRY_THRESHOLD_SECONDS) {
    try {
      $options = array(
        'clientId'     => CLIENT_ID,
        'clientSecret' => CLIENT_SECRET
      );
      $oauth = new OAuth($options);

      // grab refresh token with forever token
      try {

        echo 'token to use:' . $providerRefreshToken . '<br/>';

        $tokenData = $oauth->getAccessToken('refresh_token', array('refresh_token' => $providerRefreshToken));

/*        

          // update tokens
          updatePlayerProviderTokensInDB($playerID, $tokenData->getToken(), $tokenData->getRefreshToken(), $tokenData->getExpires());
*/        

      } catch(GuzzleHttp\Exception\ConnectException $e) {
//        print $e->getMessage();
      }
    } catch(Exception $e) {
      print $e->getMessage();
    }
  }

  return $providerAccessToken;
}
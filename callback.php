<?php
include 'vendor/autoload.php';

use Strava\API\OAuth;
use Strava\API\Exception;

try {
  $options = array(
      'clientId'     => 15175,
      'clientSecret' => 'f3d284154c0b25200f074bc1a46ccc06920f9ed6',
      'redirectUri'  => 'http://s211373.gridserver.com/demo/callback.php'
  );
  $oauth = new OAuth($options);

  if (!isset($_GET['code'])) {
      print '<a href="'.$oauth->getAuthorizationUrl(array('scope' => 'view_private')).'">connect</a>';
  } else {
      $token = $oauth->getAccessToken('authorization_code', array(
          'code' => $_GET['code']
      ));
      print $token;
  }
} catch(Exception $e) {
  print $e->getMessage();
}

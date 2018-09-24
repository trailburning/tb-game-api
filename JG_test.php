<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include_once 'lib/JustGiving/JustGivingClient.php';
include_once 'lib/JustGiving/ApiClients/Model/CreateAccountRequest.php';

define('FUNDRAISING_API_URL', 'https://api.justgiving.com/');
define('FUNDRAISING_API_KEY', 'aca65145');

$fundraisingPlayerEmail = 'mallbeury@mac.com';
$fundraisingPlayerPassword = 'groover';

$client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1, $fundraisingPlayerEmail, $fundraisingPlayerPassword);
$response = $client->Account->AccountDetails();

var_dump($response);


$url = FUNDRAISING_API_URL . 'v1/account/validate';
 
$jsonData = array(
    'email' => $fundraisingPlayerEmail,
    'Password' => $fundraisingPlayerPassword
);

$ch = curl_init();  
curl_setopt($ch, CURLOPT_URL, $url);
$jsonDataEncoded = json_encode($jsonData);
 
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$contentType="application/json";
$stringForEnc = $fundraisingPlayerEmail.":".$fundraisingPlayerPassword;
$base64Credentials = base64_encode($stringForEnc);

curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: '.$contentType, 'Accept: '.$contentType, 'Authorize: Basic '.$base64Credentials, 'Authorization: Basic '.$base64Credentials, 'x-api-key: '. FUNDRAISING_API_KEY));
 
$result = curl_exec($ch);

echo $result;
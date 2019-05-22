<?php
include_once 'JustGiving/JustGivingClient.php';
include_once 'JustGiving/ApiClients/Model/CreateAccountRequest.php';

//define('FUNDRAISING_API_URL', 'https://api.staging.justgiving.com/');
define('FUNDRAISING_API_URL', 'https://api.justgiving.com/');
define('FUNDRAISING_API_KEY', 'aca65145');
define('FUNDRAISING_EMAIL', 'support@trailburning.com');
define('FUNDRAISING_PASSWORD', 'helloworld');
define('FUNDRAISING_RAISENOW_PASSWORD', 'matt@trailburning.com:M0r3I5B3tt3r!');

/* **************************************************************************** */
/* Start Support RaiseNow */
/* **************************************************************************** */
function setPlayerGameFundraisingTotalsInDB($gameID, $playerID, $fundraisingRaised) {
  require_once 'lib/mysql.php';

  // only set once
  $db = mysqliSingleton::init();
  $db->query('UPDATE gamePlayers SET fundraising_raised = ' . $fundraisingRaised . ' where game = ' . $gameID . ' and player = ' . $playerID);
}

function getFundraisingDetails($hashGameID, $hashPlayerID) {
  $strStatus = 'final_success';

  $url = 'https://api.raisenow.com/epayment/api/amp-v6a6sz/transactions/search?sort[0][field_name]=created&sort[0][order]=desc&displayed_fields=stored_anonymous_donation,stored_customer_firstname,stored_customer_lastname,stored_customer_nickname,stored_customer_additional_message,amount,currency_identifier,last_status&filters[0][field_name]=stored_TBPlayerID&filters[0][type]=fulltext&filters[0][value]=' . $hashPlayerID . '&filters[1][field_name]=stored_TBGameID&filters[1][type]=fulltext&filters[1][value]='. $hashGameID . '&filters[2][field_name]=last_status&filters[2][type]=fulltext&filters[2][value]=' . $strStatus;

  $ch = curl_init();  
  curl_setopt($ch, CURLOPT_URL, $url);  
  curl_setopt($ch, CURLOPT_SSLVERSION, 1); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, FUNDRAISING_RAISENOW_PASSWORD);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

  $result = curl_exec($ch);

  $jsonResponse = json_decode($result);  

  curl_close($ch);

  // get total raised
  $totalRaisedOnline = 0;
  foreach ($jsonResponse->result->transactions as $transaction) {
    $totalRaisedOnline += $transaction->amount;
  }
  $totalRaisedOnline = $totalRaisedOnline / 100;

  $jsonResponse->totalRaisedOnline = "$totalRaisedOnline";
  
  return $jsonResponse;
}

function getGameFundraisingDonations($hashGameID) {
  $url = 'https://api.raisenow.com/epayment/api/amp-v6a6sz/transactions/search?sort[0][field_name]=created&sort[0][order]=desc&displayed_fields=stored_anonymous_donation,stored_customer_firstname,stored_customer_lastname,stored_customer_nickname,stored_customer_additional_message,amount,currency_identifier,last_status&filters[0][field_name]=stored_TBGameID&filters[0][type]=fulltext&filters[0][value]='. $hashGameID;

  $ch = curl_init();  
  curl_setopt($ch, CURLOPT_URL, $url);  
  curl_setopt($ch, CURLOPT_SSLVERSION, 1); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, FUNDRAISING_RAISENOW_PASSWORD);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

  $result = curl_exec($ch);

  $jsonResponse = json_decode($result);  

  curl_close($ch);

  // add currency symbols
  foreach($jsonResponse->result->transactions as $transaction) {
    $transaction->currency_symbol = getCurrencySymbol($transaction->currency_identifier);
  }
  
  return $jsonResponse;
}

function getGamePlayerFundraisingDonations($hashGameID, $hashPlayerID) {
  $url = 'https://api.raisenow.com/epayment/api/amp-v6a6sz/transactions/search?sort[0][field_name]=created&sort[0][order]=desc&displayed_fields=stored_anonymous_donation,stored_customer_firstname,stored_customer_lastname,stored_customer_nickname,stored_customer_additional_message,amount,currency_identifier,last_status&filters[0][field_name]=stored_TBPlayerID&filters[0][type]=fulltext&filters[0][value]=' . $hashPlayerID . '&filters[1][field_name]=stored_TBGameID&filters[1][type]=fulltext&filters[1][value]='. $hashGameID;

  $ch = curl_init();  
  curl_setopt($ch, CURLOPT_URL, $url);  
  curl_setopt($ch, CURLOPT_SSLVERSION, 1); 
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_USERPWD, FUNDRAISING_RAISENOW_PASSWORD);
  curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);

  $result = curl_exec($ch);

  $jsonResponse = json_decode($result);  

  curl_close($ch);

  // add currency symbols
  foreach($jsonResponse->result->transactions as $transaction) {
    $transaction->currency_symbol = getCurrencySymbol($transaction->currency_identifier);
  }
  
  return $jsonResponse;
}
/* **************************************************************************** */
/* End Support RaiseNow */
/* **************************************************************************** */

/* **************************************************************************** */
/* Start Support JustGiving */
/* **************************************************************************** */
function getFundraisingPlayer($fundraisingPlayerEmail, $fundraisingPlayerPassword) {
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

  return json_decode($result);
}

function createFundraisingPlayerLite($paramaObj) {
  $url = FUNDRAISING_API_URL . 'v1/account/lite';
   
  $jsonData = array(
      'email' => $paramaObj->email,
      'FirstName' => $paramaObj->firstname,
      'LastName' => $paramaObj->lastname,
      'Password' => $paramaObj->password,
      'AcceptTermsAndConditions' => $paramaObj->acceptTerms
  );

  $ch = curl_init();  
  curl_setopt($ch, CURLOPT_URL, $url);
  $jsonDataEncoded = json_encode($jsonData);
   
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonDataEncoded);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $contentType="application/json";
  $stringForEnc = FUNDRAISING_EMAIL.":".FUNDRAISING_PASSWORD;
  $base64Credentials = base64_encode($stringForEnc);

  curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: '.$contentType, 'Accept: '.$contentType, 'Authorize: Basic '.$base64Credentials, 'Authorization: Basic '.$base64Credentials, 'x-api-key: '. FUNDRAISING_API_KEY));
   
  $result = curl_exec($ch);

  return json_decode($result);
}

function createFundraisingPlayer($paramaObj) {
  $client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1);

  $request = new CreateAccountRequest();
  $request->email = $paramaObj->email;
  $request->firstName = $paramaObj->firstname;
  $request->lastName = $paramaObj->lastname;
  $request->password = $paramaObj->password;
  $request->title = $paramaObj->title;
  $request->address->line1 = $paramaObj->addressline1;
  $request->address->line2 = $paramaObj->addressline2;
  $request->address->townOrCity = $paramaObj->town;
  $request->address->countyOrState = $paramaObj->state;
  $request->address->postcodeOrZipcode = $paramaObj->postcode;
  $request->address->country = $paramaObj->country;
  $request->acceptTermsAndConditions = $paramaObj->acceptTerms;
  $response = $client->Account->Create($request);

  return $response;
}

function createFundraisingPlayerPage($paramaObj) {
  $client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1, $paramaObj->email, $paramaObj->password);

  $registerPageRequest = new RegisterPageRequest();
  $registerPageRequest->pageShortName = $paramaObj->pageShortName;
  $registerPageRequest->pageTitle = $paramaObj->pageTitle;
  $registerPageRequest->eventName = $paramaObj->eventName;
  $registerPageRequest->charityId = $paramaObj->charityID;
  $registerPageRequest->eventId = $paramaObj->eventID;
  // do we have a supporter msg to use?
  if ($paramaObj->supporterMsg != '') {
    $registerPageRequest->pageStory = $paramaObj->supporterMsg;
  }
  $registerPageRequest->targetAmount = $paramaObj->targetAmount;
  $registerPageRequest->justGivingOptIn = $paramaObj->justGivingOptIn;
  $registerPageRequest->charityOptIn = $paramaObj->charityOptIn;
  $registerPageRequest->charityFunded = false;
  $registerPageRequest->images[0]->url = $paramaObj->imageURL;
  $registerPageRequest->images[0]->caption = "";
  $registerPageRequest->images[0]->isDefault = true;
  $response = $client->Page->Create($registerPageRequest);

  return $response;
}

function getFundraisingPage($pageShortName) {
  $client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1);
  
  $response = $client->Page->Retrieve($pageShortName);

  return $response;
}

function getFundraisingPageDonations($pageShortName) {
  $client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1);

  $response = $client->Page->RetrieveDonationsForPage($pageShortName);

  return $response;
}

function getFundraisingEventLeaderboard($eventId) {
  $client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1);

  $response = $client->Leaderboard->GetEventLeaderboard($eventId);

  return $response;
}
/* **************************************************************************** */
/* End Support JustGiving */
/* **************************************************************************** */

function getFundraisingGameShoppingList($gameID) {
  $db = mysqliSingleton::init();
  $strSQL = 'SELECT fundraisingshoppinglist.amount, fundraisingshoppinglist.buy, fundraisingshoppinglist.title, fundraisingshoppinglist.description, fundraisingshoppinglist.image FROM fundraisingshoppinglist JOIN fundraisingshoppinglistgamelevels ON fundraisingshoppinglistgamelevels.fundraisingShoppingListID = fundraisingshoppinglist.id JOIN games ON games.levelID = fundraisingshoppinglistgamelevels.gameLevelID WHERE games.ID = ' . $gameID;
  $result = $db->query($strSQL);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

function getFundraisingCampaignLeaderboard($campaignID, $numPlayers) {
  require_once 'lib/mysql.php';

  $hashids = new Hashids\Hashids('mountainrush', 10);

  $db = mysqliSingleton::init();
  $result = $db->query('SELECT players.id, players.firstname, players.lastname, players.avatar, games.id as gameID, games.type as game_type, games.game_start, games.game_end, gameLevels.name as level_name, gamePlayers.ascent, gamePlayers.distance, fundraising_currency, fundraising_raised FROM players JOIN gamePlayers ON players.id = gamePlayers.player JOIN games ON gamePlayers.game = games.id JOIN gameLevels ON games.levelID = gameLevels.id WHERE games.campaignID = ' . $campaignID . ' ORDER BY fundraising_raised DESC, ascent DESC LIMIT ' . $numPlayers);
  $rows = array();
  $index = 0;
  while ( $row = $result->fetch_array(MYSQLI_ASSOC) ) {
    $row['id'] = $hashids->encode($row['id']);
    $row['gameID'] = $hashids->encode($row['gameID']);
    $row['fundraising_currency_symbol'] = getCurrencySymbol($row['fundraising_currency']);

    $rows[$index] = $row;
    $index++;
  }

  return $rows;
}

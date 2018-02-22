<?php
include_once 'JustGiving/JustGivingClient.php';
include_once 'JustGiving/ApiClients/Model/CreateAccountRequest.php';

define('FUNDRAISING_API_URL', 'https://api.staging.justgiving.com/');
define('FUNDRAISING_API_KEY', 'aca65145');
define('FUNDRAISING_EMAIL', 'support@trailburning.com');
define('FUNDRAISING_PASSWORD', 'helloworld');

function getFundraisingPlayer($fundraisingPlayerEmail) {
  $ret = false;

  $client = new JustGivingClient(FUNDRAISING_API_URL, FUNDRAISING_API_KEY, 1);
  if($client->Account->IsEmailRegistered($fundraisingPlayerEmail)) {
    $ret = true;
  }
  return $ret;
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
  $request->acceptTermsAndConditions = true;
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
  $registerPageRequest->targetAmount = $paramaObj->targetAmount;
  $registerPageRequest->eventDate = "/Date(1235764800000)/";
  $registerPageRequest->justGivingOptIn = false;
  $registerPageRequest->charityOptIn = false;
  $registerPageRequest->charityFunded = false;

  $registerPageRequest->images[0]->url = "http://tbassets2.imgix.net/images/brands/mountainrush/edm/5875843c37d99829635908_682x274.jpg";
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
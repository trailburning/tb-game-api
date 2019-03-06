<?php
function sendInviteEmail($strEmailTemplate, $jsonEmail, $game, $player, $activePlayer) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $activePlayer['email'], $activePlayer['firstname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendWelcomeEmail($strEmailTemplate, $jsonEmail, $game, $player) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $player, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendFinishedEmail($strEmailTemplate, $jsonEmail, $game, $player) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $player, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendInactivityEmail($strEmailTemplate, $jsonEmail, $game, $activePlayer) {
  $jsonEmail = replaceTags($jsonEmail, $game, $activePlayer, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $activePlayer['email'], $activePlayer['firstname'] . ' ' . $activePlayer['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendActivityEmail($strEmailTemplate, $jsonEmail, $game, $player, $activePlayer, $activity) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, $activity);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendSummitEmail($strEmailTemplate, $jsonEmail, $game, $player, $activePlayer) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendFundraisingDonationEmail($strEmailTemplate, $jsonEmail, $game, $activePlayer, $donation) {
  $jsonEmail = replaceDonationTags($jsonEmail, $donation);
  $jsonEmail = replaceTags($jsonEmail, $game, $activePlayer, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($strEmailTemplate, $strSubject, $activePlayer['email'], $activePlayer['firstname'] . ' ' . $activePlayer['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function replaceTags($strText, $game, $player, $activePlayer, $activity) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $gameType = '';
  if ($game['type'] != 'All') {
    $gameType = strtolower($game['type']);
  }

  $tags = array('[CAMPAIGN_ID]', '[INVITE_LINK]', '[GAME_LINK]', '[PLAYER_LINK]', '[PLAYER_FIRSTNAME]', '[PLAYER_LASTNAME]', '[ACTIVE_PLAYER_LINK]', '[ACTIVE_PLAYER_FIRSTNAME]', '[ACTIVE_PLAYER_LASTNAME]', '[GAME_TYPE]', '[GAME_NAME]', '[ACTIVITY_ELEVATION]', '[PROFILE_LINK]', '[PREFERENCES_LINK]', '[FUNDRAISING_LINK]');
  $replaceTags = array($game['campaignID'], '<a href=\"' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/invite\">here</a>', '<a href=\"' . MR_SECURE_DOMAIN . 'game/' . $game['id'] . '\">here</a>', '<a href=\"' . MR_SECURE_DOMAIN . 'game/' . $game['id'] . '/player/' . $player['id'] . '\">here</a>', $player['firstname'], $player['lastname'], '<a href=\"' . MR_SECURE_DOMAIN . 'game/' . $game['id'] . '/player/' . $activePlayer['id'] . '\">here</a>', $activePlayer['firstname'], $activePlayer['lastname'], $gameType, $game['name'], floor($activity['total_elevation_gain']), '<a href=\"' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/profile\">profile</a>', '<a href=\"' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences\">update your preferences</a>', '<a href=\"' . $game['fundraising_page'] . $activePlayer['fundraising_page'] . '\">' . $game['fundraising_provider'] . '</a>');

  return str_replace($tags, $replaceTags, $strText);
}

function replacePlayerTags($strText, $player) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $tags = array('[PLAYER_FIRSTNAME]', '[PLAYER_LASTNAME]');
  $replaceTags = array($player['firstname'], $player['lastname']);

  return str_replace($tags, $replaceTags, $strText);
}

function replaceDonationTags($strText, $donation) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $tags = array('[DONATION_CURRENCY]', '[DONATION_AMOUNT]', '[DONATION_DONOR]');
  $replaceTags = array(getCurrencySymbol($donation['currency']), $donation['amount'], $donation['donor']);

  return str_replace($tags, $replaceTags, $strText);
}

function BuildEmail($strEmailTemplate, $strSubject, $strToEmail, $strToName, $strImage, $strMsgTitle, $strMsgContent, $strPreferences) {

  $template_content = array(
    array(
      'name' => 'msg_image',
      'content' => '<img src="' . $strImage . '" width="682" style="max-width:682px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage" alt="Play Mountain Rush">'
    ),
    array(
      'name' => 'msg_title',
      'content' => $strMsgTitle
    ),
    array(
      'name' => 'msg_content',
      'content' => $strMsgContent
    ),
    array(
      'name' => 'msg_preferences',
      'content' => $strPreferences
    )
  );
  $message = array(
    'subject' => $strSubject,
    'from_email' => 'support@mountainrush.co.uk',
    'from_name' => 'Mountain Rush',
    'to' => array(
      array(
        'email' => $strToEmail,
        'name' => $strToName,
        'type' => 'to'
      )
    ),
    'headers' => array('Reply-To' => 'support@mountainrush.co.uk')
  );

  $email = array(
    'template_content' => $template_content,
    'message' => $message
  );

  return $email; 
}

function sendEmail($strEmailTemplate, $strSubject, $strToEmail, $strToName, $strImage, $strMsgTitle, $strMsgContent, $strPreferences) {

  if (DEBUG) {
    echo 'sendEmail:' . $strToEmail . ' : SEND OFF<br/>';
    echo 'msg:' . $strMsgContent . '<br/>';
    return;
  }

  try {
    $mandrill = new Mandrill('kRr66_sxVLQJwehdLnakqg');
    $async = false;
    $ip_pool = '';
    $send_at = '';

    // Build and send TEST email
    $email = BuildEmail($strEmailTemplate, $strSubject . ' DUPLICATE ' . $strToEmail, 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strMsgTitle, $strMsgContent, $strPreferences);
    $mandrill->messages->sendTemplate($strEmailTemplate, $email['template_content'], $email['message'], $async, $ip_pool, $send_at);

    // Build and send email
    $email = BuildEmail($strEmailTemplate, $strSubject, $strToEmail, $strToName, $strImage, $strMsgTitle, $strMsgContent, $strPreferences);
    $result = $mandrill->messages->sendTemplate($strEmailTemplate, $email['template_content'], $email['message'], $async, $ip_pool, $send_at);

    return $result;
  } catch(Mandrill_Error $e) {
//    echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
    // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
//    throw $e;
  }
}
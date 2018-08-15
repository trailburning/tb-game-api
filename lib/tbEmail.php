<?php
function sendInviteEmail($jsonEmail, $game, $player, $activePlayer) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($game['email_template'], $strSubject, $activePlayer['email'], $activePlayer['firstname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], $strSubject . ' DUPLICATE ' . $activePlayer['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendWelcomeEmail($jsonEmail, $game, $player) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $player, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($game['email_template'], $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], $strSubject . ' DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendInactivityEmail($jsonEmail, $game, $activePlayer) {
  $jsonEmail = replaceTags($jsonEmail, $game, $activePlayer, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($game['email_template'], $strSubject, $activePlayer['email'], $activePlayer['firstname'] . ' ' . $activePlayer['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], $strSubject . ' DUPLICATE ' . $activePlayer['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendActivityEmail($jsonEmail, $game, $player, $activePlayer, $activity) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, $activity);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($game['email_template'], $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], $strSubject . ' DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendSummitEmail($jsonEmail, $game, $player, $activePlayer) {
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  $strSubject = $game['campaign_name'] . ' - ' . $arrEmail->title;

  // now send an email
  $result = sendEmail($game['email_template'], $strSubject, $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], $strSubject . ' DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function replaceTags($strText, $game, $player, $activePlayer , $activity) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $tags = array('[CAMPAIGN_ID]', '[INVITE_LINK]', '[GAME_LINK]', '[PLAYER_LINK]', '[PLAYER_FIRSTNAME]', '[PLAYER_LASTNAME]', '[ACTIVE_PLAYER_LINK]', '[ACTIVE_PLAYER_FIRSTNAME]', '[ACTIVE_PLAYER_LASTNAME]', '[GAME_TYPE]', '[GAME_NAME]', '[ACTIVITY_ELEVATION]', '[PREFERENCES_LINK]', '[FUNDRAISING_LINK]');
  $replaceTags = array($game['campaignID'], '<a href=\"' . MR_DOMAIN . 'campaign/' . $game['campaignID'] . '/invite\">here</a>', '<a href=\"' . MR_DOMAIN . 'game/' . $game['id'] . '\">here</a>', '<a href=\"' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $player['id'] . '\">here</a>', $player['firstname'], $player['lastname'], '<a href=\"' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $activePlayer['id'] . '\">here</a>', $activePlayer['firstname'], $activePlayer['lastname'], strtolower($game['type']), $game['name'], floor($activity['total_elevation_gain']), '<a href=\"' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences\">update your preferences</a>', '<a href=\"' . $game['fundraising_page'] . $activePlayer['fundraising_page'] . '\">' . $game['fundraising_provider'] . '</a>');

  return str_replace($tags, $replaceTags, $strText);
}

function sendEmail($strEmailTemplate, $strSubject, $strToEmail, $strToName, $strImage, $strMsgTitle, $strMsgContent, $strPreferences) {

  try {
    $mandrill = new Mandrill('kRr66_sxVLQJwehdLnakqg');

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
    $async = false;
    $ip_pool = '';
    $send_at = '';
    $result = $mandrill->messages->sendTemplate($strEmailTemplate, $template_content, $message, $async, $ip_pool, $send_at);

    return $result;
  } catch(Mandrill_Error $e) {
//    echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
    // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
//    throw $e;
  }
}
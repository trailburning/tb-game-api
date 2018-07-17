<?php
//function sendInviteEmail($game, $invitingPlayer, $inviteName, $inviteEmail) {
function sendInviteEmail($game, $player, $activePlayer) {
  $jsonEmail = '{"title": "Challenge Invitation!", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_invite_682x300.jpg?q=80", "message": "<p>[ACTIVE_PLAYER_FIRSTNAME], you\'ve been invited by <strong>[PLAYER_FIRSTNAME] [PLAYER_LASTNAME]</strong> to a <strong>[GAME_NAME] [GAME_TYPE] challenge</strong>. You can see the challenge [GAME_LINK].<br/><br/>Click [INVITE_LINK] to view the invitation.<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Invitation', $activePlayer['email'], $activePlayer['firstname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Invitation DUPLICATE ' . $activePlayer['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendWelcomeEmail($game, $player) {
  $jsonEmail = '{"title": "Challenge Ready", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_ready_682x300.jpg?q=80", "message": "<p>[PLAYER_FIRSTNAME], your <strong>[GAME_NAME] [GAME_TYPE] challenge</strong> is ready and can be viewed [GAME_LINK].<br/><br/>Don\'t forget to post photos of your journey to the summit using the <a href=\"https://www.strava.com/mobile\">Strava App</a> or <a href=\"https://www.instagram.com\">Instagram</a>.  You can also include <strong>#playmountainrush</strong> in any social posts.<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';
  $jsonEmail = replaceTags($jsonEmail, $game, $player, $player, null);
  $arrEmail = json_decode($jsonEmail);

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Ready', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Ready DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendInactivityEmail($game, $activePlayer) {
  $jsonEmail = '{"title": "Everything Okay", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_inactivity_682x300.jpg?q=80", "message": "<p>We notice you haven\'t logged any activity in your <strong>[GAME_NAME] [GAME_TYPE] challenge</strong> for a while!<br/><br/>You can check your progress [ACTIVE_PLAYER_LINK].<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';

  $jsonEmail = replaceTags($jsonEmail, $game, $activePlayer, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity', $activePlayer['email'], $activePlayer['firstname'] . ' ' . $activePlayer['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity DUPLICATE ' . $activePlayer['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendActivityEmail($game, $player, $activePlayer, $activity) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $jsonEmail = '{"title": "Player Activity", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_activity_682x300.jpg?q=80", "message": "<p>[ACTIVE_PLAYER_FIRSTNAME] [ACTIVE_PLAYER_LASTNAME] has climbed <strong>[ACTIVITY_ELEVATION]m</strong> in the <strong>[GAME_NAME] [GAME_TYPE] challenge</strong>, check [ACTIVE_PLAYER_FIRSTNAME]\'s progress [ACTIVE_PLAYER_LINK].<br/><br/>Don\'t forget to post photos of your journey to the summit using the <a href=\"https://www.strava.com/mobile\">Strava App</a> or <a href=\"https://www.instagram.com\">Instagram</a>.  You can also include <strong>#playmountainrush</strong> in any social posts.<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';

  if ($player['id'] == $activePlayer['id']) {
    $jsonEmail = '{"title": "Player Activity", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_activity_682x300.jpg?q=80", "message": "<p>You have climbed <strong>[ACTIVITY_ELEVATION]m</strong> in the <strong>[GAME_NAME] [GAME_TYPE] challenge</strong> and can check your progress [PLAYER_LINK].<br/><br/>Don\'t forget to post photos of your journey to the summit using the <a href=\"https://www.strava.com/mobile\">Strava App</a> or <a href=\"https://www.instagram.com\">Instagram</a>.  You can also include <strong>#playmountainrush</strong> in any social posts.<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';
  }

  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, $activity);
  $arrEmail = json_decode($jsonEmail);

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function sendSummitEmail($game, $player, $activePlayer) {
  $jsonEmail = '{"title": "Player Summited!", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_summit_682x300.jpg?q=80", "message": "<p>[ACTIVE_PLAYER_FIRSTNAME] [ACTIVE_PLAYER_LASTNAME] has summited and completed the <strong>[GAME_NAME] [GAME_TYPE] challenge</strong>, see [ACTIVE_PLAYER_FIRSTNAME] at the summit [ACTIVE_PLAYER_LINK]!<br/><br/>Don\'t forget to post photos of your journey to the summit using the <a href=\"https://www.strava.com/mobile\">Strava App</a> or <a href=\"https://www.instagram.com\">Instagram</a>.  You can also include <strong>#playmountainrush</strong> in any social posts.<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';

  if ($player['id'] == $activePlayer['id']) {
    $jsonEmail = '{"title": "Congratulations [PLAYER_FIRSTNAME]!", "image": "http://tbassets2.imgix.net/images/brands/mountainrush/edm/[CAMPAIGN_ID]/challenge_summit_682x300.jpg?q=80", "message": "<p>You have <strong>summited</strong> and completed the <strong>[GAME_NAME] [GAME_TYPE] challenge</strong>, see yourself at the summit [PLAYER_LINK]!<br/><br/>Don\t forget to include <strong>#playmountainrush</strong> in any social posts.<br/><br/>The Mountain Rush Team<br/><br/>If you have any questions please <a href=\"mailto:support@mountainrush.co.uk\">contact</a> us!</p>", "preferences": "Want to change how you receive these emails?<br>You can [PREFERENCES_LINK]."}';
  }

  $jsonEmail = replaceTags($jsonEmail, $game, $player, $activePlayer, null);
  $arrEmail = json_decode($jsonEmail);

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Summited!', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Summited! DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $arrEmail->image, $arrEmail->title, $arrEmail->message, $arrEmail->preferences);
}

function replaceTags($strText, $game, $player, $activePlayer , $activity) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $tags = array('[CAMPAIGN_ID]', '[INVITE_LINK]', '[GAME_LINK]', '[PLAYER_LINK]', '[PLAYER_FIRSTNAME]', '[PLAYER_LASTNAME]', '[ACTIVE_PLAYER_LINK]', '[ACTIVE_PLAYER_FIRSTNAME]', '[ACTIVE_PLAYER_LASTNAME]', '[GAME_TYPE]', '[GAME_NAME]', '[ACTIVITY_ELEVATION]', '[PREFERENCES_LINK]');
  $replaceTags = array($game['campaignID'], '<a href=\"' . MR_DOMAIN . 'campaign/' . $game['campaignID'] . '/invite\">here</a>', '<a href=\"' . MR_DOMAIN . 'game/' . $game['id'] . '\">here</a>', '<a href=\"' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $player['id'] . '\">here</a>', $player['firstname'], $player['lastname'], '<a href=\"' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $activePlayer['id'] . '\">here</a>', $activePlayer['firstname'], $activePlayer['lastname'], strtolower($game['type']), $game['name'], floor($activity['total_elevation_gain']), '<a href=\"' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences\">update your preferences</a>');

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
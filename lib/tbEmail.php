<?php
function sendInviteEmail($game, $invitingPlayer, $inviteName, $inviteEmail) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $strWelcome = $game['name'] . ' challenge';
  $strInviteURL = '<a href="' . MR_DOMAIN . 'campaign/' . $game['campaignID'] . '/invite">here</a>';

  $strPlayerURL = '<a href="' . MR_DOMAIN . 'game/' . $game['id'] . '">here</a>';

  $strTitle = 'Challenge Invitation!';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_invite_682x300.jpg?q=80';
  $strMsg = $inviteName . ', you\'ve been invited by <strong>' . $invitingPlayer['firstname'] . ' ' . $invitingPlayer['lastname'] . '</strong> to a <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge</strong>.  You can see the challenge ' . $strPlayerURL . '.<br/><br/>Click ' . $strInviteURL . ' to view the invitation!';

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Invitation', $inviteEmail, $inviteName, $strImage, $strWelcome, $strTitle, $strMsg, '');

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Invitation DUPLICATE ' . $inviteEmail, 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, '');
}

function sendWelcomeEmail($game, $player) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = 'Want to change how you receive these emails?<br>You can <a href="' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences">update your preferences</a>.<br><br>';
  $strPlayerURL = '<a href="' . MR_DOMAIN . 'game/' . $game['id'] . '">here</a>';

  $strTitle = 'Challenge Ready!';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_ready_682x300.jpg?q=80';
  $strMsg = $player['firstname'] . ', your <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge</strong> is ready and can be viewed ' . $strPlayerURL . '.';

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Ready', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Ready DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);
}

function sendInactivityEmail($game, $activePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $playerID = $hashids->decode($activePlayer['id'])[0];
  $hashActivePlayerID = $activePlayer['id'];

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = 'Want to change how you receive these emails?<br>You can <a href="' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences">update your preferences</a>.<br><br>';
  $strPlayerURL = '<a href="' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">here</a>';

  $strTitle = 'Everything Okay?';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_inactivity_682x300.jpg?q=80';
  $strMsg = 'We notice you haven\'t logged any activity in your <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge</strong> for a while!<br/><br/>You can check your progress ' . $strPlayerURL . '.';

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity', $activePlayer['email'], $activePlayer['firstname'] . ' ' . $activePlayer['lastname'], $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity DUPLICATE ' . $activePlayer['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);
}

function sendActivityEmail($game, $player, $activePlayer, $activity) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $playerID = $hashids->decode($player['id'])[0];
  $hashActivePlayerID = $activePlayer['id'];

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = 'Want to change how you receive these emails?<br>You can <a href="' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences">update your preferences</a>.<br><br>';
  $strPlayerURL = '<a href="' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">here</a>';

  $strTitle = 'Player Activity';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_activity_682x300.jpg?q=80';
  $strMsg = $activePlayer['firstname'] . ' ' . $activePlayer['lastname'] . ' has climbed <strong>' . floor($activity['total_elevation_gain']) . 'm</strong> in the <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge</strong>!<br/><br/>Check ' . $activePlayer['firstname'] . '\'s progress ' . $strPlayerURL . '.';
  // player is same player with activity so change msg
  if ($player['id'] == $activePlayer['id']) {
    $strMsg = 'You have climbed <strong>' . floor($activity['total_elevation_gain']) . 'm</strong> in the <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge</strong>!<br/><br/>You can check your progress ' . $strPlayerURL . '.';
  }

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Activity DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);
}

function sendSummitEmail($game, $player, $activePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $playerID = $hashids->decode($player['id'])[0];
  $hashActivePlayerID = $activePlayer['id'];

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = 'Want to change how you receive these emails?<br>You can <a href="' . MR_SECURE_DOMAIN . 'campaign/' . $game['campaignID'] . '/preferences">update your preferences</a>.<br><br>';
  $strPlayerURL = '<a href="' . MR_DOMAIN . 'game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">here</a>';

  $strTitle = 'Player Summited!';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_summit_682x300.jpg?q=80';
  $strMsg = $activePlayer['firstname'] . ' ' . $activePlayer['lastname'] . ' has summited and completed the <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge<strong>!<br/><br/>See ' . $activePlayer['firstname'] . ' at the summit ' . $strPlayerURL . '.';

  // player is same player with activity so change msg
  if ($player['id'] == $activePlayer['id']) {
    $strTitle = 'Congratulations ' . $activePlayer['firstname'] . '!';
    $strMsg = 'You have <strong>summited</strong> and completed the <strong>' . $game['name'] . ' ' . strtolower($game['type']) . ' challenge</strong>!<br/><br/>See yourself at the summit ' . $strPlayerURL . '.';
  }

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Summited!', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Player Summited! DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);
}

function sendEmail($strEmailTemplate, $strSubject, $strToEmail, $strToName, $strImage, $strWelcome, $strMsgTitle, $strMsgContent, $strPreferences) {

  try {
    $mandrill = new Mandrill('kRr66_sxVLQJwehdLnakqg');

    $template_content = array(
      array(
        'name' => 'msg_image',
        'content' => '<img src="' . $strImage . '" width="682" style="max-width:682px; padding-bottom: 0; display: inline !important; vertical-align: bottom;" class="mcnImage" alt="Play Mountain Rush">'
      ),
      array(
        'name' => 'msg_welcome',
        'content' => $strWelcome
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
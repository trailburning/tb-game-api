<?php
function sendInviteEmail($game, $invitingPlayer, $inviteName, $inviteEmail) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = '<a href="http://mountainrush.trailburning.com/campaign/' . $game['campaignID'] . '/preferences">change your preferences</a>';
  $strInviteURL = '<a href="http://mountainrush.trailburning.com/campaign/' . $game['campaignID'] . '/invite">here</a>';

  $strGameURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '">' . $game['name'] . '</a>';  
  $strPlayerURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '">here</a>';

  $strTitle = 'Challenge Invitation!';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_invite_682x300.jpg';
  $strMsg = $inviteName . ', you\'ve been invited by <strong>' . $invitingPlayer['firstname'] . ' ' . $invitingPlayer['lastname'] . '</strong> to a ' . $strGameURL . ' ' . strtolower($game['type']) . ' challenge.  You can see the challenge ' . $strPlayerURL . '.<br/><br/>Click ' . $strInviteURL . ' to view the invitation!';

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Invitation', $inviteEmail, $inviteName, $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Invitation DUPLICATE ' . $inviteEmail, 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);
}

function sendWelcomeEmail($game, $player) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = '<a href="http://mountainrush.trailburning.com/campaign/' . $game['campaignID'] . '/preferences">change your preferences</a>';

  $strGameURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '">' . $game['name'] . '</a>';  
  $strPlayerURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '">here</a>';

  $strTitle = 'Challenge Ready!';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_ready_682x300.jpg';
  $strMsg = $player['firstname'] . ', your ' . $strGameURL . ' ' . strtolower($game['type']) . ' challenge is ready and can be viewed ' . $strPlayerURL . '.';

  // now send an email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Ready', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);

  // MLA - test email
  $result = sendEmail($game['email_template'], 'Mountain Rush - Challenge Ready DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strImage, $strWelcome, $strTitle, $strMsg, $strPreferences);
}

function sendInactivityEmail($game, $activePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $playerID = $hashids->decode($activePlayer['id'])[0];

  $strWelcome = $game['name'] . ' challenge';
  $strPreferences = '<a href="http://mountainrush.trailburning.com/campaign/' . $game['campaignID'] . '/preferences">change your preferences</a>';
  $strGameURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '">' . $game['name'] . '</a>';

  $strTitle = 'Everything Okay?';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_inactivity_682x300.jpg';
  $strMsg = 'We notice you haven\'t logged any activity in your ' . $strGameURL . ' ' . strtolower($game['type']) . ' challenge for a while!';

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
  $strPreferences = '<a href="http://mountainrush.trailburning.com/campaign/' . $game['campaignID'] . '/preferences">change your preferences</a>';
  $strGameURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '">' . $game['name'] . '</a>';
  $strPlayerURL = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">here</a>';

  $strTitle = 'Player Activity';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_activity_682x300.jpg';
  $strMsg = $activePlayer['firstname'] . ' ' . $activePlayer['lastname'] . ' has climbed <strong>' . floor($activity['total_elevation_gain']) . 'm</strong> in the ' . $strGameURL . ' ' . strtolower($game['type']) . ' challenge!<br/><br/>Check ' . $activePlayer['firstname'] . '\'s progress ' . $strPlayerURL . '.';
  // player is same player with activity so change msg
  if ($player['id'] == $activePlayer['id']) {
    $strMsg = 'You have climbed <strong>' . floor($activity['total_elevation_gain']) . 'm</strong> in the ' . $strGameURL . ' ' . strtolower($game['type']) . ' challenge!<br/><br/>Check your progress ' . $strPlayerURL . '.';
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
  $strPreferences = '<a href="http://mountainrush.trailburning.com/campaign/' . $game['campaignID'] . '/preferences">change your preferences</a>';
  $strGame = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">' . $game['name'] . '</a>';

  $strTitle = 'Player Summited!';
  $strImage = 'http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $game['campaignID'] . '/challenge_summit_682x300.jpg';
  $strMsg = $activePlayer['firstname'] . ' ' . $activePlayer['lastname'] . ' has summited and completed the ' . $strGame . ' ' . strtolower($game['type']) . ' challenge!';
  // player is same player with activity so change msg
  if ($player['id'] == $activePlayer['id']) {
    $strTitle = 'Congratulations ' . $activePlayer['firstname'] . '!';
    $strMsg = 'You have summited and completed the ' . $strGame . ' ' . strtolower($game['type']) . ' challenge!';
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
      'from_email' => 'hello@trailburning.com',
      'from_name' => 'Mountain Rush',
      'to' => array(
        array(
          'email' => $strToEmail,
          'name' => $strToName,
          'type' => 'to'
        )
      ),
      'headers' => array('Reply-To' => 'hello@trailburning.com')
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
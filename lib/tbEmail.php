<?php
function sendActivityEmail($game, $player, $activePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $playerID = $hashids->decode($player['id'])[0];
  $hashActivePlayerID = $hashids->encode($activePlayer['id']);

  $strWelcome = $game['name'] . ' challenge';
  $strGame = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">' . $game['name'] . '</a>';

  $strTitle = 'Player Activity';
  $strMsg = $activePlayer['firstname'] . ' ' . $activePlayer['lastname'] . ' has progressed in the ' . $strGame . ' challenge!';
  // player is same player with activity so change msg
  if ($playerID == $activePlayer['id']) {
    $strMsg = 'You have progressed in the ' . $strGame . ' challenge!';
  }

  // now send an email
  $result = sendEmail($game['journeyID'], 'Mountain Rush - Player Activity', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $strWelcome, $strTitle, $strMsg);

  // MLA - test email
  $result = sendEmail($game['journeyID'], 'Mountain Rush - Player Activity DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strWelcome, $strTitle, $strMsg);
}

function sendSummitEmail($game, $player, $activePlayer) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $playerID = $hashids->decode($player['id'])[0];
  $hashActivePlayerID = $hashids->encode($activePlayer['id']);

  $strWelcome = $game['name'] . ' challenge';
  $strGame = '<a href="http://mountainrush.trailburning.com/game/' . $game['id'] . '/player/' . $hashActivePlayerID . '">' . $game['name'] . '</a>';

  $strTitle = 'Player Summited!';
  $strMsg = $activePlayer['firstname'] . ' ' . $activePlayer['lastname'] . ' has summited the ' . $strGame . ' and completed the challenge!';
  // player is same player with activity so change msg
  if ($playerID == $activePlayer['id']) {
    $strTitle = 'Congratulations ' . $activePlayer['firstname'] . '!';
    $strMsg = 'You have summited the ' . $strGame . ' and completed the challenge!';
  }

  // now send an email
  $result = sendEmail($game['journeyID'], 'Mountain Rush - Player Summited!', $player['email'], $player['firstname'] . ' ' . $player['lastname'], $strWelcome, $strTitle, $strMsg);

  // MLA - test email
  $result = sendEmail($game['journeyID'], 'Mountain Rush - Player Summited! DUPLICATE ' . $player['email'], 'mallbeury@mac.com', 'Matt Allbeury', $strWelcome, $strTitle, $strMsg);
}

function sendEmail($strJourneyID, $strSubject, $strToEmail, $strToName, $strWelcome, $strMsgTitle, $strMsgContent) {
  try {
    $mandrill = new Mandrill('kRr66_sxVLQJwehdLnakqg');

    $template_name = 'TB Member EDM';
    $template_content = array(
      array(
        'name' => 'msg_image',
        'content' => '<img src="http://tbassets2.imgix.net/images/brands/mountainrush/edm/' . $strJourneyID . '_682x274.jpg" width="682" alt="Play Mountain Rush">'
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
    $result = $mandrill->messages->sendTemplate($template_name, $template_content, $message, $async, $ip_pool, $send_at);

    return $result;
  } catch(Mandrill_Error $e) {
    // Mandrill errors are thrown as exceptions
    echo 'A mandrill error occurred: ' . get_class($e) . ' - ' . $e->getMessage();
    // A mandrill error occurred: Mandrill_Unknown_Subaccount - No subaccount exists with the id 'customer-123'
    throw $e;
  }
}
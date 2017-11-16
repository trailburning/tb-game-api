<?php
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
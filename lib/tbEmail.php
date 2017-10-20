<?php
function sendEmail($strSubject, $strToEmail, $strToName, $strMsgTitle, $strMsgContent) {
  try {
    $mandrill = new Mandrill('kRr66_sxVLQJwehdLnakqg');

    $template_name = 'TB Member EDM';
    $template_content = array(
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
      'html' => '<p>Example HTML content</p>',
      'text' => 'Example text content',
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
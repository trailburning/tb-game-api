<?php
use Imgix\UrlBuilder;

function buildSocialGameImage($paramaObj) {
  $builder = new UrlBuilder("tbassets2.imgix.net");

  // bottom left data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 55, "txt64" => $paramaObj->mountain);
  $txtMountain = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Regular", "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 27, "txt64" => $paramaObj->region);
  $txtCountry = $builder->createURL("~text", $params);

  $params = array("w" => 600, "h" => 168, "markx" => 46, "marky" => 24, "mark64" => $txtMountain,
  "bx" => 46, "by" => 90, "bm" => 'normal', "blend64" => $txtCountry);
  $leftData = $builder->createURL("images/brands/mountainrush/social/bg_text2.png", $params);

  // bottom right data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 55, "txt64" => $paramaObj->ascent . 'm');
  $txtAscent = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Regular", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 27, "txt64" => $paramaObj->challenge);
  $txtDetail = $builder->createURL("~text", $params);

  $params = array("w" => 600, "h" => 168, "markx" => -46, "marky" => 24, "mark64" => $txtAscent,
  "bx" => -46, "by" => 90, "bm" => 'normal', "blend64" => $txtDetail);
  $rightData = $builder->createURL("images/brands/mountainrush/social/bg_text2.png", $params);

  // bottom data
  $params = array("w" => 1200, "h" => 150, "markx" => 0, "marky" => 0, "mark64" => $leftData,
  "bx" => 600, "by" => 0, "bm" => 'normal', "blend64" => $rightData);
  $bottomImg = $builder->createURL("images/brands/mountainrush/social/bg_blank2.png", $params);

  // overlay
  $params = array("w" => 1200, "h" => 630);
  $overlayImg = $builder->createURL("images/brands/mountainrush/social/overlay.png", $params);

  // do we want to add progress?
  if ($paramaObj->progress) {
    // progress
    $params = array("w" => 1200, "txtfont64" => "Avenir Next Condensed Demi,Bold", "txtalign" => 'right', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 55, "txt64" => $paramaObj->progress);
    $txtProgress = $builder->createURL("~text", $params);

    $params = array("w" => 1200, "h" => 630, "markx" => -46, "marky" => 46, "mark64" => $txtProgress);
    $overlayImg = $builder->createURL("images/brands/mountainrush/social/overlay.png", $params);
  }

  // final image
  $params = array("w" => 1200, "h" => 630, "q" => 80, "markx" => 0, "marky" => 480, "mark64" => $bottomImg,
  "bw" => 1200, "bh" => 630, "bm" => 'normal', "blend64" => $overlayImg);
  $finalImg = $builder->createURL("images/brands/mountainrush/social/games/" . $paramaObj->journeyID . ".jpg", $params);

  return $finalImg;
}

function buildSocialGameProgressImage($paramaObj) {
  $builder = new UrlBuilder("tbassets2.imgix.net");

  // bottom left data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed,Bold", "txtclr" => '474747', "txtpad" => 0, "txtsize" => 60, "txt64" => $paramaObj->ascent . 'm');
  $txtMountain = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed,Medium", "txtclr" => '474747', "txtpad" => 0, "txtsize" => 45, "txt64" => strtoupper($paramaObj->mountain . ' ' . $paramaObj->type));
  $txtCountry = $builder->createURL("~text", $params);

  $params = array("w" => 654, "h" => 107, "markx" => 46, "marky" => 14, "mark64" => $txtMountain,
  "bx" => 244, "by" => 29, "bm" => 'normal', "blend64" => $txtCountry);
  $leftData = $builder->createURL("images/brands/mountainrush/social/wwf/bg_text.png", $params);

  // progress
  $params = array("w" => 228, "h" => 100, "txtfont64" => "Avenir Next Condensed,Bold", "txtalign" => 'center', "txtclr" => 'fff', "txtpad" => 0, "txtsize" => 72, "txt64" => $paramaObj->progress);
  $txtProgress = $builder->createURL("~text", $params);

  // final image
  $params = array("w" => 1200, "h" => 630, "q" => 80, "markx" => 0, "marky" => 523, "mark64" => $leftData,
  "bw" => 228, "bh" => 100, "bx" => 578, "by" => 246, "bm" => 'normal', "blend64" => $txtProgress);
  $finalImg = $builder->createURL("images/brands/mountainrush/social/wwf/CFYW_Gorilla.png", $params);

  return $finalImg;
}

function generateGameSocialImage($gameID) {
  $ret = '';

  $arrResponse = getGameFromDB($gameID);
  if (count($arrResponse)) {
    $paramaObj = (object) [
      'journeyID' => $arrResponse[0]['journeyID'],
      'mountain' => $arrResponse[0]['name'],
      'region' => strtolower($arrResponse[0]['region']),
      'ascent' => $arrResponse[0]['ascent'],
      'challenge' => strtolower($arrResponse[0]['type']) . ' challenge',
      'progress' => 0
    ];
    $ret = buildSocialGameImage($paramaObj);
  }
  return $ret;
}

function generateGameProgressSocialImage($gameID, $progress) {
  $ret = '';

  $arrResponse = getGameFromDB($gameID);
  if (count($arrResponse)) {
    $paramaObj = (object) [
      'journeyID' => $arrResponse[0]['journeyID'],
      'mountain' => $arrResponse[0]['name'],
      'region' => strtolower($arrResponse[0]['region']),
      'ascent' => $arrResponse[0]['ascent'],
      'type' => $arrResponse[0]['type'],
      'challenge' => strtolower($arrResponse[0]['type']) . ' challenge ',
      'progress' => $progress
    ];

    if ($progress) {
      // 180328 MLA - temp until we can build images based on a template system
      echo buildSocialGameProgressImage($paramaObj);
    }
    else {
      echo buildSocialGameImage($paramaObj);
    }
  }
}

<?php
use Imgix\UrlBuilder;

function getChallengeShortDescription($gameType) {
  $gameDescription = '';
  if ($gameType != 'All') {
    $gameDescription = strtolower($gameType);
  }
  return $gameDescription;
}

function getChallengeDescription($gameType) {
  return getChallengeShortDescription($gameType) . ' challenge';
}

function buildSocialGameImage($paramaObj) {
  $builder = new UrlBuilder("tbassets2.imgix.net");
  $builder->setUseHttps(true);

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
  $builder = new UrlBuilder("mountainrush-assets.imgix.net");
  $builder->setUseHttps(true);

  // bottom left data
  $strComplete = $paramaObj->ascent . 'm';
  if ($paramaObj->distance) {
    $strComplete = round($paramaObj->distance / 1000) . 'km';
  }

  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed,Bold", "txtclr" => 'FFFFFF', "txtpad" => 0, "txtsize" => 56, "txt64" => $strComplete);
  $txtMountain = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed,Medium", "txtclr" => 'FFFFFF', "txtpad" => 0, "txtsize" => 45, "txt64" => strtoupper($paramaObj->mountain . ' ' . $paramaObj->challenge));
  $txtCountry = $builder->createURL("~text", $params);

  $params = array("w" => 896, "h" => 101, "markx" => 84, "marky" => 14, "mark64" => $txtMountain,
  "bx" => 264, "by" => 25, "bm" => 'normal', "blend64" => $txtCountry);
  $leftData = $builder->createURL("clients/" . $paramaObj->client . "/social/bg_text_temp1.png", $params);

  // progress
  $params = array("w" => 300, "h" => 120, "txtfont64" => "Avenir Next Condensed,Bold", "txtalign" => 'center', "txtclr" => 'FFFFFF', "txtpad" => 0, "txtsize" => 86, "txt64" => $paramaObj->progress);
  $txtProgress = $builder->createURL("~text", $params);

  // final image
  $params = array("w" => 1200, "h" => 630, "q" => 80, "markx" => 0, "marky" => 523, "mark64" => $leftData,
  "bw" => 228, "bh" => 100, "bx" => 888, "by" => 362, "bm" => 'normal', "blend64" => $txtProgress);
  $finalImg = $builder->createURL("clients/" . $paramaObj->client . "/social/" . $paramaObj->background, $params);

  return $finalImg;
}

function buildSocialGameGoalImage($paramaObj) {
  $builder = new UrlBuilder("mountainrush-assets.imgix.net");
  $builder->setUseHttps(true);

  // bottom left data
  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed,Bold", "txtclr" => 'FFFFFF', "txtpad" => 0, "txtsize" => 56, "txt64" => $paramaObj->ascent . 'm');
  $txtMountain = $builder->createURL("~text", $params);

  $params = array("w" => 600, "txtfont64" => "Avenir Next Condensed,Medium", "txtclr" => 'FFFFFF', "txtpad" => 0, "txtsize" => 45, "txt64" => strtoupper($paramaObj->mountain . ' ' . $paramaObj->challenge));
  $txtCountry = $builder->createURL("~text", $params);

  $params = array("w" => 896, "h" => 101, "markx" => 84, "marky" => 14, "mark64" => $txtMountain,
  "bx" => 264, "by" => 25, "bm" => 'normal', "blend64" => $txtCountry);
  $leftData = $builder->createURL("clients/" . $paramaObj->client . "/social/bg_text_temp1.png", $params);

  // goal
  $params = array("w" => 300, "h" => 120, "txtfont64" => "Avenir Next Condensed,Bold", "txtalign" => 'center', "txtclr" => 'FFFFFF', "txtpad" => 0, "txtsize" => 86, "txt64" => $paramaObj->goal);
  $txtGoal = $builder->createURL("~text", $params);

  // final image
  $params = array("w" => 1200, "h" => 630, "q" => 80, "markx" => 0, "marky" => 523, "mark64" => $leftData,
  "bw" => 228, "bh" => 100, "bx" => 888, "by" => 430, "bm" => 'normal', "blend64" => $txtGoal);
  $finalImg = $builder->createURL("clients/" . $paramaObj->client . "/social/" . $paramaObj->background, $params);

  return $finalImg;
}

function generateGameSocialImage($gameID) {
  $ret = '';

  $arrResponse = getGameFromDB($gameID);
  if (count($arrResponse)) {
    $paramaObj = (object) [
      'journeyID' => $arrResponse[0]['journeyID'],
      'mountain' => $arrResponse[0]['level_name'],
      'region' => strtolower($arrResponse[0]['region']),
      'ascent' => $arrResponse[0]['ascent'],
      'challenge' => getChallengeDescription($arrResponse[0]['type']),
      'progress' => 0
    ];
    $ret = buildSocialGameImage($paramaObj);
  }

  return $ret;
}

function generateGameProgressSocialImage($gameID, $progress) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $arrResponse = getGameFromDB($gameID);
  if (count($arrResponse)) {
    $ownerPlayerID = $hashids->decode($arrResponse[0]['ownerPlayerID'])[0];

    $arrCause = getPlayerGameCauseFromDB($gameID, $ownerPlayerID);
    if (count($arrCause)) {

      $paramaObj = (object) [
        'client' => strtolower($arrCause[0]['name']),
        'journeyID' => $arrResponse[0]['journeyID'],
        'background' => $arrCause[0]['media_share_raised'],
        'mountain' => $arrResponse[0]['level_name'],
        'region' => strtolower($arrResponse[0]['region']),
        'ascent' => $arrResponse[0]['ascent'],
        'distance' => $arrResponse[0]['distance'],
        'type' => $arrResponse[0]['type'],
        'challenge' => getChallengeShortDescription($arrResponse[0]['type']),
        'progress' => $progress
      ];

      if ($progress) {
        echo buildSocialGameProgressImage($paramaObj);
      }
      else {
        echo buildSocialGameImage($paramaObj);
      }
    }
    else {
      echo buildSocialGameImage($paramaObj);      
    }
  }
}

function generateGameGoalSocialImage($gameID, $goal, $bGroupGoal) {
  $hashids = new Hashids\Hashids('mountainrush', 10);

  $arrResponse = getGameFromDB($gameID);
  if (count($arrResponse)) {
    $ownerPlayerID = $hashids->decode($arrResponse[0]['ownerPlayerID'])[0];

    $arrCause = getPlayerGameCauseFromDB($gameID, $ownerPlayerID);
    if (count($arrCause)) {
      $background = $arrCause[0]['media_share_goal'];
      if ($bGroupGoal) {
        $background = $arrCause[0]['media_share_groupgoal'];
      }

      $paramaObj = (object) [
        'client' => strtolower($arrCause[0]['name']),
        'journeyID' => $arrResponse[0]['journeyID'],
        'background' => $background,
        'mountain' => $arrResponse[0]['level_name'],
        'region' => strtolower($arrResponse[0]['region']),
        'ascent' => $arrResponse[0]['ascent'],
        'type' => $arrResponse[0]['type'],
        'challenge' => getChallengeShortDescription($arrResponse[0]['type']),
        'goal' => $goal
      ];

      if ($goal) {
        echo buildSocialGameGoalImage($paramaObj);
      }
      else {
        echo buildSocialGameImage($paramaObj);
      }
    }
    else {
      echo buildSocialGameImage($paramaObj);      
    }
  }
}

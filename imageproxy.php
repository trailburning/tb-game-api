<?php
error_reporting(E_ERROR);

header("Access-Control-Allow-Origin: *");

$url = "";
if( isset( $_GET['url'] ) ) {
  $url = $_GET[ 'url' ];
}
else
{
  exit();
}
$imginfo = getimagesize( $url );
if (!$imginfo) {
  // use default image
  $url = 'http://mountainrush.trailburning.com/static-assets/images/avatar_unknown.jpg';
  $imginfo = getimagesize( $url );
}

header("Content-type: ".$imginfo['mime']);
readfile( $url );

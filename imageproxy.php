<?php
error_reporting(E_ERROR);

header("Access-Control-Allow-Origin: *");

$url = '';
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
  $url = 'http://mountainrush.co.uk/static-assets/images/avatar_unknown.jpg';
  if( isset( $_GET['urlfallback'] ) ) {
    $url = $_GET[ 'urlfallback' ];
  }

  $imginfo = getimagesize( $url );
}

header("Content-type: ".$imginfo['mime']);
readfile( $url );

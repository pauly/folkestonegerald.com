#!/usr/bin/php
<?php

$feeds = array (
  'ipod classic' => array (
    'categoryID' => 73839
  ),
  'vegan msg' => array (
    'categoryID' => 14314
  ),
  'laura ashley milton' => array (
    'categoryID' => 3197
  ),
  'john lewis bergerac' => array (
    'categoryID' => 3197
  ),
  'habitat radius' => array (
    'categoryID' => 3197
  ),
  'vw beetle' => array (
    'categoryID' => 9800
  ),
  'vw split' => array (
    'categoryID' => 14256
  )
);
foreach ( $feeds as $key => $feed ) {
  $url = 'http://rest.ebay.com/epn/v1/find/item.rss?keyword=' . urlencode( $key );
  if ( isset( $feed['categoryID'] ) && $feed['categoryID'] ) {
    $url .= '&categoryId1=' . $feed['categoryID'];
  }
  $url .= '&sortOrder=BestMatch&programid=15&campaignid=5337531671&toolid=10039&customid=666666&listingType1=All&lgeo=1&feedType=rss';
  $rss = file_get_contents( $url );
  $items = preg_match_all( '/<description><!\[CDATA\[(.*?)\]\]><\/description>/', $rss, $match );
  $file = preg_replace( '/\s(\w)/ex', 'strtoupper("${1}")', $key );
  $content = implode( array_slice( $match[1], 0, 5 ));
  $content = preg_replace( '/img src=/', 'img data-lazy=', $content );
  file_put_contents( '/var/www/t/' . $file . '.html', $content );
}

?>

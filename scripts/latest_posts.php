#!/usr/bin/php
<?php

include_once __DIR__ . '/../html/php/init.php';
require_once __DIR__ . '/../html/php/messageObject.php';
$_REQUEST = getopt( '', array( 'messageID::', 'v::', 'mode::', 'debug::' ));
$m = new messageObject( 'message', $CFG );
$messages = DB::singleton( $CFG )->query_fetch_and_cache( 'select * from message where mDate > "' . date( 'Y-m-d', strtotime( '-2 weeks' )) .'" order by mDate desc limit 100' );
foreach ( array( 0, 3, 8, 14 ) as $board ) {
  foreach ( array ( '', 'replies' ) as $inclusivity ) {
    $file = '/var/www/t/latestComments' . $board . $inclusivity;
    $content = array( );
    foreach ( $messages as $message ) {
      if (( 0 == $board ) || ( $message['mBoard'] == $board )) {
        if ( '' === $inclusivity ) { // || ( $message['mPunter'] === 'pauly' )) {
          error_log( $file . ' ' . $board . ' ' . $message['messageID'] . ' ' . $inclusivity . ' ' . $message['mPunter'] . ' (first rule)' );
          array_push( $content, $m->mLink( $message, false, false, true ));
        }
        if (( 'replies' === $inclusivity ) && ( $message['mPunter'] !== 'pauly' )) {
          error_log( $file . ' ' . $board . ' ' . $message['messageID'] . ' ' . $inclusivity . ' ' . $message['mPunter'] );
          array_push( $content, $m->mLink( $message, false, false, true ));
        }
      }
    }
    file_put_contents( $file, '<ul class="🤘 ">' . array_reduce( array_slice( $content, 0, 5 ), function ( $a, $b ) {
      return $a . '<li>' . $b . '</li>';
    } ) . '</ul>' );
  }
}
$todoList = DB::singleton( $CFG )->query_fetch_and_cache( 'select * from message where mCategory like "%@todo%" order by mDate desc limit 5' );
$file = '/var/www/t/todoList';
$content = array( );
foreach ( $todoList as $message ) {
  array_push( $content, $m->mLink( $message, false, false, true ));
}
file_put_contents( '/var/www/t/todoList.html', '<ul>' . array_reduce( $content, function ( $a, $b ) {
  return $a . '<li>' . $b . '</li>';
} ) . '</ul>' );

?>

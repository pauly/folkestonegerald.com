<?php

include_once __DIR__ . '/../html/php/init.php';
require_once __DIR__ . '/../html/php/Gigantasaur.php';
$_REQUEST = getopt( '', array( 'messageID::', 'v::', 'mode::', 'debug::' ));
$obj = new Gigantasaur( 'event', $CFG );
$events = DB::singleton( $CFG )->query_fetch_and_cache( 'select eventDescription, eventDate, eventDetails, eventID, eventVenue, venueName, venueID, eventRating, eventPriceDetails, venue.tags from event join venue on eventVenue = venueID where eventDate > %s order by dateAdded desc limit 5', date( 'U' ));
echo array_reduce( array_slice( $events, 0, 5 ), function ( $a, $event ) use ( $obj ) {
  return $a . $obj->cleanUp( $obj->eLink( $event ));
} );

?>

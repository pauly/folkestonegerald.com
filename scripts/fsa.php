#!/bin/env php
<?php

error_reporting( E_ALL );

if ( ! $argv[1] ) die( 'usage: ' . $argv[0] . ' [fsa file]' );

include_once __DIR__ . '/../html/php/init.php';
require_once __DIR__ . '/../html/php/Gigantasaur.php';

$venues = new SimpleXMLElement( file_get_contents( $argv[1] ));

$_REQUEST = $_GET = array( 'debug' => 1 );
$obj = new Gigantasaur( 'venue' );
$categories = array( 'pub', 'restaurant', 'takeaway', 'hotel', 'school', 'hopspitals' );
$categoriesToSkip = array( 'retailers+-+supermarkets', 'retailers+-+other', 'mobile+caterer', 'hospitals', 'retailers+-+other', 'other+catering+premises', 'manufacturers', 'distributors', 'farmers', 'importers' );
foreach ( $venues->EstablishmentCollection->EstablishmentDetail as $venue ) {
  $category = strtolower( preg_replace( '/\s+/', '+', array_shift( explode( '/', $venue->BusinessType ))));
  echo $category . "\n";
  if ( in_array( $category, $categoriesToSkip )) {
    continue;
  }
  if ( ! in_array( $category, $categories )) {
    print_r( $venue );
    break;
  }
  // echo $venue->BusinessName . "\n";
  // echo $venue->AddressLine2 . "\n";
  $address = $venue->AddressLine2;
  if ( ! $address ) $address = $venue->PostCode;
  if ( ! $address ) $address = $venue->AddressLine1;
  $address = '%' . $address . '%';
  $newVenue = array( );
  $existing = DB::singleton( $CFG )->query_fetch_and_cache( 'select * from `popex`.`venue` where `venueName` like %s and ( `venueAddress` like %s or `venueAddress` like %s )', '%' . $venue->BusinessName . '%', $address, '%' . $venue->PostCode . '%' );
  if ( count( $existing )) {
    $newVenue = $existing[0];
  }
  $tags = array( $category );
  if ( $category === 'restaurant' ) array_push( $tags, 'food' );
  if ( $category === 'takeaway' ) array_push( $tags, 'food' );
  if ( $category === 'hotel' ) array_push( $tags, 'accommodation' );
  array_push( $tags, 'fhrs:' . $venue->FHRSID );
  if ( isset( $newVenue['tags'] ) && $newVenue['tags'] ) {
    $tags = array_unique( array_merge( explode( ' ', $newVenue['tags'] ), $tags ));
  }
  $newVenue = array_merge( $newVenue, array(
    'confirm' => true,
    'venueName' => '' . $venue->BusinessName,
    'venueAddress' => implode( ', ', array( $venue->AddressLine1, $venue->AddressLine2, $venue->AddressLine3, $venue->PostCode )),
    'latitude' => '' . $venue->Geocode->Latitude,
    'longitude' => '' . $venue->Geocode->Longitude,
    'tags' => implode( ' ', $tags )
  ));
  if ( isset( $newVenue['venueID'] ) && $newVenue['venueID'] ) {
    echo $obj->edit( $newVenue );
    continue;
  }
  echo $obj->add( $newVenue );
}

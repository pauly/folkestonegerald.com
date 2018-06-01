#!/usr/bin/php
<ol class="artists">
<li><h2><a href="http://last.fm/user/PaulyPopEx" title="Stats courtesy of last.fm" class="mp3 chart">Chart of the week</a></h2></li>
<?php

/**
 * Get my chart of the week from last.fm and chart the tags
 * 
 * @author PC <paul.clarke+popex@holidayextras.com>
 * @date Fri Jan 20 19:47:37 GMT 2012
 */

function genre ( $tag = '' ) {
  if ( isset( $tag->name )) $tag = $tag->name;
  if ( preg_match( '/\/([\w\+]+)$/', $tag, $m )) {
    $tag = $m[1];
  }
  return strtolower( (string) $tag );
}

function label ( $tag = '' ) {
  switch ( strtolower( (string) $tag )) {
    case 'christmas':
      return 'xmas';
    case 'rock':
      return 'ROCK \\\m/';
  }
  return ucfirst( $tag );
}

require_once __DIR__ . '/../html/php/LastFM.php';
require_once __DIR__ . '/../html/php/plugins/modifier.li.php';
$lastfm = new LastFM;
$chart_size = 10;
$list = $lastfm->user_getweeklychartlist( );
array_pop( $list->weeklychartlist->chart );
$param = json_decode( json_encode( array_pop( $list->weeklychartlist->chart )), true );
// error_log( print_r( $param, true ));
// error_log( 'from ' . date( 'd/m/Y', $param['from'] ) . ' to ' . date( 'd/m/Y', $param['to'] ));

$chart = $lastfm->user_getweeklyartistchart( $param );
if ( $chart->weeklyartistchart->artist ) {
  foreach ( $chart->weeklyartistchart->artist as $i => $artist ) {
    if ( $i < $chart_size ) {
      if ($artist->name !== 'Spotify') {
        echo smarty_modifier_li($artist->name, 'gig', $artist->playcount . ' play' . ($artist->playcount ? 's' : ''));
      }
    }
  }
  echo '</ol><ol class="tracks"><li><h2>And top tracks of the week</h2></li>';
}
else {
  echo '<li>Something up with last.fm I think...</li>';
}
$max_tags_per_artist = 3;
$tags = array( );
$doubles = array( );
$chart = $lastfm->user_getweeklytrackchart( );
if ( isset( $chart->weeklytrackchart->track ) && $chart->weeklytrackchart->track ) {
  $shownArtistPicture = array();
  foreach ( $chart->weeklytrackchart->track as $i => $track ) {
    if ($track->name === 'Spotify') continue;
    $artist = $track->artist->{'#text'};
    if ( $i < $chart_size ) {
      echo '<li><a rel="tag" href="/gig/' . preg_replace( '/\W+/', '+', strtolower( urlencode( $artist ))) . '"';
      echo ' title="' . htmlspecialchars( $artist ) . '<br />' . $track->playcount . ' play' . ( $track->playcount > 1 ? 's' : '' );
      $img = '<br /><img title="' . htmlspecialchars( $artist ) . '" src="' . $track->image[2]->{'#text'} . '" />';
      if ($i && !$shownArtistPicture[$artist]) {
        echo htmlspecialchars($img);
      }
      echo '"';
      echo '>' . htmlspecialchars( $track->name );
      $img = '<br /><img height="140px" width="140px" title="' . htmlspecialchars( $artist ) . '" data-lazy="' . $track->image[2]->{'#text'} . '" />';
      if (!$i) {
        $shownArtistPicture[$artist] = true;
        echo $img;
      }
      echo '</a></li>';
    }
    $reply = $lastfm->track_gettags( array( 'artist' => $artist, 'track' => $track->name, 'user' => $lastfm->username ));
    if ( ! ( isset( $reply->tags ) and isset( $reply->tags->tag ))) {
      $reply = $lastfm->artist_gettags( array( 'artist' => $artist, 'user' => $lastfm->username ));
    }
    if ( isset( $reply->tags ) and isset( $reply->tags->tag )) {
      foreach ( $reply->tags->tag as $tag ) {
        $tag = genre( $tag );
        $tags[ $tag ] = isset( $tags[ $tag ] ) ? $tags[ $tag ] + 1 : 1;
        if ( ! isset( $doubles[ $tag ] )) $doubles[ $tag ] = array( );
        foreach ( $reply->tags->tag as $tag2 ) {
          $tag2 = genre( $tag2 );
          if ( $tag2 == $tag ) continue;
          $doubles[ $tag ][ $tag2 ] = isset( $doubles[ $tag ][ $tag2 ] ) ? $doubles[ $tag ][ $tag2 ] + 1 : 1;
          foreach ( $reply->tags->tag as $tag3 ) {
            $tag3 = genre( $tag3 );
            if ( $tag3 == $tag ) continue;
            if ( $tag3 == $tag2 ) continue;
            $trebles[ $tag ][ $tag2 ][ $tag3 ] = isset( $trebles[ $tag ][ $tag2 ][ $tag3 ] ) ? $trebles[ $tag ][ $tag2 ][ $tag3 ] + 1 : 1;
          }
        }
      }
    }
    /* else {
      $reply = $lastfm->artist_gettoptags( array( 'artist' => $artist ));
      if ( isset( $reply->toptags ) and isset( $reply->toptags->tag ) and count( $reply->toptags->tag )) {
        foreach ( array_slice( $reply->toptags->tag, 0, $max_tags_per_artist ) as $tag ) {
          $tags[ $tag->name ] = isset( $tags[ $tag->name ] ) ? $tags[ $tag->name ] + 1 : 1;
        }
      }
    } */
    arsort( $tags );
  }
}

foreach ( $tags as $tag => $score ) {
  switch ( strtolower( (string) $tag )) {
    case '60s':
    case '70s':
    case '80s':
    case '90s':
    case '00s':
    case '10s':
    case 'indie pop':
    case 'seen live':
    case '':
      unset( $tags[$tag] );
  }
}
// echo '<!--  ';
  $venn_size = count( $tags );
  if ( $venn_size > 2 ) $venn_size = 2;
  $genres = array_keys( array_slice( $tags, 0, $venn_size + 1 ));
  // print_r( $genres );
  $venn_data = array( );
  // The first three values specify the sizes of three circles: A, B, and C. For a chart with only two circles, specify zero for the third value.
  $venn_data[0] = (int) $tags[ $genres[ 0 ]];
  // print_r( $doubles[ $genres[ 0 ]] );
  $venn_data[1] = (int) $tags[ $genres[ 1 ]];
  // print_r( $doubles[ $genres[ 1 ]] );
  $venn_data[2] = (int) $tags[ $genres[ 2 ]];
  // print_r( $doubles[ $genres[ 2 ]] );
  if ( count( $genres ) > 2 ) {
    // The fourth value specifies the size of the intersection of A and B.
    $venn_data[3] = (int) $doubles[ $genres[0] ][ $genres[1] ];
    // The fifth value specifies the size of the intersection of A and C. For a chart with only two circles, do not specify a value here.
    if ( $venn_data[3] == 0 ) error_log( 'No crossover between ' . $genres[0] . ' and ' . $genres[1] . ' (' . print_r( $doubles[ $genres[0] ], true ));
    $venn_data[4] = (int) $doubles[ $genres[0] ][ $genres[2] ];
    if ( $venn_data[4] == 0 ) error_log( 'No crossover between ' . $genres[0] . ' and ' . $genres[2] . ' ( ' . print_r( $doubles[ $genres[0] ], true ));
    // The sixth value specifies the size of the intersection of B and C. For a chart with only two circles, do not specify a value here.
    $venn_data[5] = (int) $doubles[ $genres[1] ][ $genres[2] ];
    if ( $venn_data[5] == 0 ) error_log( 'No crossover between ' . $genres[1] . ' and ' . $genres[2] . ' ( ' . print_r( $doubles[ $genres[1] ], true ));
    // The seventh value specifies the size of the common intersection of A, B, and C. For a chart with only two circles, do not specify a value here.
    $venn_data[6] = (int) $trebles[ $genres[0] ][ $genres[1] ][ $genres[2] ];
    if ( $venn_data[6] == 0 ) {
      error_log( 'No crossover between ' . $genres[0] . ' and ' . $genres[1] . ' and ' . $genres[2] . ' ( ' . print_r( $trebles[ $genres[0] ][ $genres[1] ], true ));
      error_log( print_r( $trebles[ $genres[1] ][ $genres[0] ], true ));
    }
  }
  // print_r( $venn_data );
  foreach ( $genres as &$genre ) {
    $genre = label( $genre );
  }
  $venn_src = 'https://chart.googleapis.com/chart?cht=v&chs=270x250&chd=t:' . implode( ',', $venn_data ) . '&chdl=' . urlencode( implode( '|', $genres )); // . '&chtt=Music+Venn';
// echo ' -->';

?></ol><?php
/* if ( $tags ) {
?><!-- {literal} --><script type="text/javascript" src="https://www.google.com/jsapi"></script><script type="text/javascript">google.load("visualization", "1", {packages:["corechart"]});google.setOnLoadCallback(drawChart);function drawChart() { var data = new google.visualization.DataTable(); data.addColumn('string', 'Genre'); data.addColumn('number', 'Listens'); data.addRows( <?php
  $array = array( );
  foreach ( $tags as $tag => $score ) {
    $array[] = array( label( $tag ), $score );
  }
  echo json_encode( $array ); ?> ); var options = { width: 270, height: 250, legend: { position: 'none' }, pieSliceText: 'label', sliceVisibilityThreshold: 2 / 360 }; var chart = new google.visualization.PieChart(document.getElementById('chart_div')); chart.draw(data, options); } </script> <!-- {/literal} --> <div id="chart_div"></div><div><img data-lazy="<?php echo $venn_src ?>" width="270" height="250" alt="The VENN of ROCK" /></div>
<?php
} */
?>

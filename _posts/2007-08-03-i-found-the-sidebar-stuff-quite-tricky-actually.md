---
title: I found the sidebar stuff quite tricky actually
date: 2007-08-03T12:38:06.000Z
post: 10376
board: 8
layout: post
parent: 10316
tags: [folkestone]
permalink: /m/10376/
---
I can't remember how I did it right now, I can only suggest looking in the source, it's all integral. 

I'll try and update <a href="http://www.folkestonegerald.com/js/map.js">my source code</a> with some comments when I get time... Or, having looked at it again, completely rewrite it, it's rubbish... This looks like the bit you wan though:

<blockquote>var marker = new GMarker( new GLatLng( lat, lon ));
GEvent.addListener( marker, "click", function() {
marker.openInfoWindowTabsHtml( tabs );
} );
marker.lat = lat;
marker.lon = lon;
marker.guid = guid;
var li = document.createElement("li");
li.name = li.id = guid;
var a = document.createElement("a");
a.innerHTML = content;
addEvent( a, "click", function() {
d( "Got a click on link " + guid );
marker.openInfoWindowTabsHtml( tabs );
} );
li.appendChild(a);
myGmap.panel.appendChild(li);
// d( li.innerHTML );</blockquote>

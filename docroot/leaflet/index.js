var mymap = L.map('mapid').setView([0, 0], 2);
var key = 'pk.eyJ1IjoiaWllZCIsImEiOiJjazAzcHVtMmcyYXZ5M2xvMXFlaWk2dWh3In0.cSVpPstX-AU9kPBR47kQuA';

L.tileLayer('https://api.mapbox.com/styles/v1/iied/ck03pwvo2006v1co70ojs0oz2/tiles/256/' +
    '{z}/{x}/{y}?access_token=' + key, {
		maxZoom: 18,
		attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, ' +
			'<a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, ' +
			'Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
		id: 'mapbox.streets'
	}).addTo(mymap);

  

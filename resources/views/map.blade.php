<!DOCTYPE html>
<html>
  <head>
    <title>flight radar</title>
    <link rel="stylesheet" href="/style.css">
  </head>
  <body>
    <div id="searchContainer">
      <input type="text" id="searchInput" placeholder="Flight number...">
      <button id="searchBtn">Search</button>
    </div>
    <button id="styleToggle">Toggle Style</button>
    <div id="map"></div>
    
    <div id="sidebar">
      <button class="close-btn" onclick="hideSidebar()">×</button>
      <div class="sidebar-content"></div>
    </div>

    <script>
      const REFRESH_INTERVAL_MS = 20 * 1000; // 20 seconds
      
      let markers = new Map(); 
      let iconCache = new Map(); 
      let map;
      let currentStyleIndex = 0;
      let selectedMarker = null; // Atlasītā lidmašīna (violēta)
      let fallbackData = null; // Store fallback data from plane.json

      const mapStyles = [
        {
          name: 'Night Mode',
          styles: [
            { elementType: 'geometry', stylers: [{ color: '#242f3e' }] },
            { elementType: 'labels.text.stroke', stylers: [{ color: '#242f3e' }] },
            { elementType: 'labels.text.fill', stylers: [{ color: '#746855' }] },
            { featureType: 'administrative.locality', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
            { featureType: 'poi', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
            { featureType: 'poi.park', elementType: 'geometry', stylers: [{ color: '#263c3f' }] },
            { featureType: 'poi.park', elementType: 'labels.text.fill', stylers: [{ color: '#6b9a76' }] },
            { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#38414e' }] },
            { featureType: 'road', elementType: 'geometry.stroke', stylers: [{ color: '#212a37' }] },
            { featureType: 'road', elementType: 'labels.text.fill', stylers: [{ color: '#9ca5b3' }] },
            { featureType: 'road.highway', elementType: 'geometry', stylers: [{ color: '#746855' }] },
            { featureType: 'road.highway', elementType: 'geometry.stroke', stylers: [{ color: '#1f2835' }] },
            { featureType: 'road.highway', elementType: 'labels.text.fill', stylers: [{ color: '#f3d19c' }] },
            { featureType: 'transit', elementType: 'geometry', stylers: [{ color: '#2f3948' }] },
            { featureType: 'transit.station', elementType: 'labels.text.fill', stylers: [{ color: '#d59563' }] },
            { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#17263c' }] },
            { featureType: 'water', elementType: 'labels.text.fill', stylers: [{ color: '#515c6d' }] },
            { featureType: 'water', elementType: 'labels.text.stroke', stylers: [{ color: '#17263c' }] },
          ]
        },
        {
          name: 'Standard',
          styles: []
        },
        {
          name: 'Retro',
          styles: [
            { elementType: "geometry", stylers: [{ color: "#ebe3cd" }] },
            { elementType: "labels.text.fill", stylers: [{ color: "#523735" }] },
            { elementType: "labels.text.stroke", stylers: [{ color: "#f5f1e6" }] },
            { featureType: "administrative", elementType: "geometry.stroke", stylers: [{ color: "#c9b2a6" }] },
            { featureType: "administrative.land_parcel", elementType: "geometry.stroke", stylers: [{ color: "#dcd2be" }] },
            { featureType: "administrative.land_parcel", elementType: "labels.text.fill", stylers: [{ color: "#ae9e90" }] },
            { featureType: "landscape.natural", elementType: "geometry", stylers: [{ color: "#dfd2ae" }] },
            { featureType: "poi", elementType: "geometry", stylers: [{ color: "#dfd2ae" }] },
            { featureType: "poi", elementType: "labels.text.fill", stylers: [{ color: "#93817c" }] },
            { featureType: "poi.park", elementType: "geometry.fill", stylers: [{ color: "#a5b076" }] },
            { featureType: "poi.park", elementType: "labels.text.fill", stylers: [{ color: "#447530" }] },
            { featureType: "road", elementType: "geometry", stylers: [{ color: "#f5f1e6" }] },
            { featureType: "road.arterial", elementType: "geometry", stylers: [{ color: "#fdfcf8" }] },
            { featureType: "road.highway", elementType: "geometry", stylers: [{ color: "#f8c967" }] },
            { featureType: "road.highway", elementType: "geometry.stroke", stylers: [{ color: "#e9bc62" }] },
            { featureType: "road.highway.controlled_access", elementType: "geometry", stylers: [{ color: "#e98d58" }] },
            { featureType: "road.highway.controlled_access", elementType: "geometry.stroke", stylers: [{ color: "#db8555" }] },
            { featureType: "road.local", elementType: "labels.text.fill", stylers: [{ color: "#806b63" }] },
            { featureType: "transit.line", elementType: "geometry", stylers: [{ color: "#dfd2ae" }] },
            { featureType: "transit.line", elementType: "labels.text.fill", stylers: [{ color: "#8f7d77" }] },
            { featureType: "transit.line", elementType: "labels.text.stroke", stylers: [{ color: "#ebe3cd" }] },
            { featureType: "transit.station", elementType: "geometry", stylers: [{ color: "#dfd2ae" }] },
            { featureType: "water", elementType: "geometry.fill", stylers: [{ color: "#b9d3c2" }] },
            { featureType: "water", elementType: "labels.text.fill", stylers: [{ color: "#92998d" }] },
          ]
        },
        {
          name: 'Satellite',
          styles: null // lietos satellite map type
        }
      ];

      function toggleMapStyle() {
        currentStyleIndex = (currentStyleIndex + 1) % mapStyles.length;
        const currentStyle = mapStyles[currentStyleIndex];
        
        if (currentStyle.name === 'Satellite') {
          map.setMapTypeId('satellite');
        } else {
          map.setMapTypeId('roadmap');
          map.setOptions({ styles: currentStyle.styles });
        }
        
        document.getElementById('styleToggle').textContent = `Style: ${currentStyle.name}`;
      }

      function showPlaneInfo(data, marker) {
        const sidebar = document.getElementById('sidebar');
        const content = sidebar.querySelector('.sidebar-content');

        if (selectedMarker && selectedMarker !== marker) {
          const oldData = selectedMarker.flightData;
          selectedMarker.setIcon(createPlaneIcon(oldData.heading));
        }

        marker.setIcon(createSelectedIcon(data.heading));
        selectedMarker = marker;
        
        sidebar.classList.add('visible');
        
        const { snapshotTime, callsign, country, lon, lat, baroAlt, geoAlt, velocity, heading, onGround, lastRecv } = data;
        const snapshotDate = new Date(snapshotTime * 1000).toLocaleString();
        const lastContactDate = new Date(lastRecv * 1000).toLocaleString();
        
        content.innerHTML = `
          <div class="plane-info">
            <h2>${callsign !== 'N/A' ? callsign : 'Unknown'}</h2>
            <div class="info-row">
              <span class="info-label">Country origin:</span>
              <span class="info-value">${country || 'Unknown'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Latitude:</span>
              <span class="info-value">${lat ? lat.toFixed(5) : 'N/A'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Longitude:</span>
              <span class="info-value">${lon ? lon.toFixed(5) : 'N/A'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Altitude (baro):</span>
              <span class="info-value">${baroAlt !== null ? Math.round(baroAlt) + ' m' : 'N/A'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Altitude (geo):</span>
              <span class="info-value">${geoAlt !== null ? Math.round(geoAlt) + ' m' : 'N/A'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Speed:</span>
              <span class="info-value">${velocity !== null ? velocity.toFixed(1) + ' m/s (' + Math.round(velocity * 3.6) + ' km/h)' : 'N/A'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Heading:</span>
              <span class="info-value">${heading !== null ? Math.round(heading) + '°' : 'N/A'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">On ground:</span>
              <span class="info-value">${onGround ? 'Yes' : 'No'}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Last Contact:</span>
              <span class="info-value">${lastContactDate}</span>
            </div>
            <div class="info-row">
              <span class="info-label">Snapshot Time:</span>
              <span class="info-value">${snapshotDate}</span>
            </div>
          </div>
        `;
      }
      
      function hideSidebar() {
        const sidebar = document.getElementById('sidebar');
        sidebar.classList.remove('visible');
        
        // Atjaunot atlasītās lidmašīnas krāsu uz normāku
        if (selectedMarker) {
          const data = selectedMarker.flightData;
          selectedMarker.setIcon(createPlaneIcon(data.heading));
          selectedMarker = null;
        }
      }

      function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
          center: { lat: 20, lng: 0 },
          zoom: 3,
          styles: mapStyles[0].styles, // sāks ar nigth mode
        });

        document.getElementById('styleToggle').addEventListener('click', toggleMapStyle);
        document.getElementById('styleToggle').textContent = `Style: ${mapStyles[0].name}`;

        //  search funct
        document.getElementById('searchBtn').addEventListener('click', searchFlight);
        document.getElementById('searchInput').addEventListener('keypress', (e) => {
          if (e.key === 'Enter') searchFlight();
        });

        // Load fallback data priekš immediate display
        loadFallbackData().then(() => {
          // start live updates
          loadFlights();
          setInterval(loadFlights, REFRESH_INTERVAL_MS);
        });
      }

      function searchFlight() {
        const searchTerm = document.getElementById('searchInput').value.trim().toUpperCase();
        
        if (!searchTerm) {
          alert('Please enter a flight number');
          return;
        }

        let found = false;
        for (const [icao, marker] of markers.entries()) {
          const callsign = marker.flightData.callsign;
          
          if (callsign && callsign.toUpperCase().includes(searchTerm)) {
            found = true;

            map.setCenter(marker.getPosition());
            map.setZoom(10);

            showPlaneInfo(marker.flightData, marker);
            break;
          }
        }
        
        if (!found) {
          alert(`Flight "${searchTerm}" not found`);
        }
      }

      function createSelectedIcon(heading) {
        const rotation = Math.round(heading || 0);
        const planeSvg = `
          <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" style="transform: rotate(${rotation}deg)">
            <path d="M16 10h4a2 2 0 0 1 0 4h-4l-4 7h-3l2 -7h-4l-2 2h-3l2 -4l-2 -4h3l2 2h4l-2 -7h3z" fill="#9b59b6" stroke="#fff" stroke-width="0.5" />
          </svg>`;
        return {
          url: 'data:image/svg+xml;utf8,' + encodeURIComponent(planeSvg),
          scaledSize: new google.maps.Size(30, 30),
          anchor: new google.maps.Point(15, 15),
        };
      }

      function createPlaneIcon(heading) {
        const rotation = Math.round(heading || 0);

        if (iconCache.has(rotation)) {
          return iconCache.get(rotation);
        }
        
        const planeSvg = `
          <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" style="transform: rotate(${rotation}deg)">
            <path d="M16 10h4a2 2 0 0 1 0 4h-4l-4 7h-3l2 -7h-4l-2 2h-3l2 -4l-2 -4h3l2 2h4l-2 -7h3z" fill="#ffff00" stroke="none" />
          </svg>`;

        const icon = {
          url: 'data:image/svg+xml;utf8,' + encodeURIComponent(planeSvg),
          scaledSize: new google.maps.Size(20, 20),
          anchor: new google.maps.Point(10, 10),
        };

        if (iconCache.size < 360) {
          iconCache.set(rotation, icon);
        }
        
        return icon;
      }

      async function loadFlights() {
        try {
          const response = await fetch('/api/states');
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          
          const data = await response.json();

          if (!data.states || !Array.isArray(data.states)) {
            console.error('No flight data available from API, using fallback');
            if (fallbackData) {
              processFlightData(fallbackData, true);
            }
            return;
          }

          processFlightData(data, false);
        } catch (error) {
          console.error('Error loading flight data:', error);
          if (fallbackData) {
            console.log('Using fallback data from plane.json');
            processFlightData(fallbackData, true);
          }
        }
      }

      function processFlightData(data, isFallback = false) {
        if (!data.states || !Array.isArray(data.states)) {
          console.error('Invalid flight data');
          return;
        }

        const snapshotTime = data.time; // API snapshot timestamp
        const currentAircraft = new Set();

        // Update vai create markers
        data.states.forEach(s => {
            const icao = s[0];
            const callsign = s[1] ? s[1].trim() : 'N/A';
            const country = s[2];
            const lastRecv = s[4];
            const lon = s[5];
            const lat = s[6];
            const baroAlt = s[7];
            const onGround = s[8];
            const velocity = s[9];
            const heading = s[10];
            const geoAlt = s[13];

            if (!lat || !lon) {
              return;
            }
            currentAircraft.add(icao);
            const position = { lat, lng: lon };

            const flightData = { 
              snapshotTime, callsign, country, lon, lat, onGround, 
              velocity, heading, baroAlt, geoAlt, lastRecv
            };

            // Update existing marker vai create new one
            if (markers.has(icao)) {
              const marker = markers.get(icao);
              marker.setPosition(position);
              marker.setIcon(createPlaneIcon(heading));
              marker.setTitle(callsign !== 'N/A' ? callsign : icao);
              marker.flightData = flightData;
            } else {
              const markerIcon = createPlaneIcon(heading);
              
              const marker = new google.maps.Marker({
                position,
                map,
                icon: markerIcon,
                title: callsign !== 'N/A' ? callsign : icao,
                zIndex: 10,
              });

              marker.flightData = flightData;

              // Click - pilna info sidebar
              marker.addListener('click', () => {
                showPlaneInfo(marker.flightData, marker);
              });

              markers.set(icao, marker);
            }
          });

        for (const [icao24, marker] of markers.entries()) {
          if (!currentAircraft.has(icao24)) {
            marker.setMap(null);
            markers.delete(icao24);
          }
        }

        console.log(`Total in API: ${data.states.length} | Planes: ${markers.size}${isFallback ? ' [FALLBACK DATA]' : ''}`);
      }

      // fallback data plane.json
      async function loadFallbackData() {
        try {
          const response = await fetch('/plane.json');
          if (response.ok) {
            fallbackData = await response.json();
            console.log('Fallback data loaded from plane.json');
            processFlightData(fallbackData, true);
          }
        } catch (error) {
          console.warn('Could not load fallback data:', error);
        }
      }
    </script>

    <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBX3LK28LELaPEtAqa1cuvPmCTdLRbcRB4&callback=initMap&loading=async"></script>
  </body>
</html>

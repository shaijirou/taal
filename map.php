<?php
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

// Get all points of interest
$query = "SELECT * FROM points_of_interest WHERE status = 'active' ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specific POI if requested
$selected_poi = null;
if (isset($_GET['poi'])) {
    $poi_id = (int)$_GET['poi'];
    $query = "SELECT * FROM points_of_interest WHERE id = ? AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->execute([$poi_id]);
    $selected_poi = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Log POI view activity
    if ($selected_poi && isLoggedIn()) {
        logUserActivity($_SESSION['user_id'], 'view_poi', $poi_id);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Interactive Map - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.css" />
    <style>
        #map { 
            height: 600px; 
            width: 100%; 
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
        }
        
        .map-controls {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .control-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .control-group label {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .directions-panel {
            background: var(--surface-elevated);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1.5rem;
            border: 1px solid var(--border-light);
            display: none;
        }
        
        .directions-panel.active {
            display: block;
        }
        
        .directions-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .directions-steps {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .direction-step {
            padding: 0.75rem;
            border-bottom: 1px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .direction-step:last-child {
            border-bottom: none;
        }
        
        .step-icon {
            width: 24px;
            height: 24px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .poi-popup {
            max-width: 280px;
        }
        
        .poi-popup h4 {
            margin: 0 0 0.5rem 0;
            color: var(--text-primary);
            font-size: 1.1rem;
        }
        
        .poi-popup .category {
            display: inline-block;
            background: var(--secondary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }
        
        .poi-popup .rating {
            color: #f6ad55;
            margin-bottom: 0.5rem;
        }
        
        .poi-popup .description {
            color: var(--text-secondary);
            font-size: 0.875rem;
            margin-bottom: 1rem;
            line-height: 1.4;
        }
        
        .poi-popup .actions {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        
        .poi-popup .btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.75rem;
            text-decoration: none;
            border-radius: var(--radius-sm);
        }
        
        .distance-info {
            background: var(--surface);
            padding: 0.5rem;
            border-radius: var(--radius-sm);
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: var(--text-secondary);
        }
        
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            border-radius: var(--radius-xl);
        }
        
        .loading-overlay.hidden {
            display: none;
        }
        
        @media (max-width: 768px) {
            .map-controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .control-group {
                width: 100%;
            }
            
            #map {
                height: 400px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">Explore Taal with Interactive Map</h1>
            
             <!-- Enhanced Map Controls  -->
            <div class="card">
                <div class="map-controls">
                    <div class="control-group">
                        <label for="category-filter">Filter by Category:</label>
                        <select id="category-filter" class="form-control" style="max-width: 200px;">
                            <option value="">All Categories</option>
                            <option value="attraction">Attractions</option>
                            <option value="restaurant">Restaurants</option>
                            <option value="accommodation">Accommodations</option>
                            <option value="cultural">Cultural Sites</option>
                            <option value="historical">Historical Sites</option>
                        </select>
                    </div>
                    
                    <div class="control-group">
                        <label for="search-poi">Search Places:</label>
                        <input type="text" id="search-poi" class="form-control" placeholder="Search for a place..." style="max-width: 250px;">
                    </div>
                    
                    <div style="display: flex; gap: 0.5rem; align-items: end;">
                        <button id="get-location" class="btn btn-success">
                            <span>📍 Get My Location</span>
                        </button>
                        <button id="clear-directions" class="btn btn-warning" style="display: none;">
                            <span>🗑️ Clear Route</span>
                        </button>
                    </div>
                </div>
            </div>
            
             <!-- Map Container with Loading Overlay  -->
            <div style="position: relative;">
                <div id="map"></div>
                <div id="loading-overlay" class="loading-overlay hidden">
                    <div style="text-align: center;">
                        <div class="spinner" style="width: 2rem; height: 2rem; margin-bottom: 1rem;"></div>
                        <p>Loading directions...</p>
                    </div>
                </div>
            </div>
            
             <!-- Directions Panel  -->
            <div id="directions-panel" class="directions-panel">
                <div class="directions-header">
                    <h3>Directions</h3>
                    <button id="close-directions" class="btn btn-danger" style="padding: 0.5rem;">✕</button>
                </div>
                <div id="directions-summary" style="margin-bottom: 1rem;"></div>
                <div id="directions-steps" class="directions-steps"></div>
            </div>
            
             <!-- POI List  -->
            <div class="card mt-3">
                <h3>Points of Interest</h3>
                <div id="poi-list" class="poi-grid">
                    <?php foreach ($pois as $poi): ?>
                        <div class="poi-card" 
                             data-category="<?php echo $poi['category']; ?>" 
                             data-lat="<?php echo $poi['latitude']; ?>" 
                             data-lng="<?php echo $poi['longitude']; ?>"
                             data-name="<?php echo htmlspecialchars($poi['name']); ?>"
                             data-id="<?php echo $poi['id']; ?>">
                            <?php if ($poi['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($poi['name']); ?>" 
                                     class="poi-image">
                            <?php endif; ?>
                            <div class="poi-content">
                                <div class="poi-category"><?php echo ucfirst($poi['category']); ?></div>
                                <h4 class="poi-title"><?php echo htmlspecialchars($poi['name']); ?></h4>
                                <div class="poi-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $poi['rating'] ? '⭐' : '☆'; ?>
                                    <?php endfor; ?>
                                    (<?php echo $poi['rating']; ?>)
                                </div>
                                <p class="poi-description"><?php echo htmlspecialchars(substr($poi['description'], 0, 100)) . '...'; ?></p>
                                <div class="poi-info">
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($poi['address']); ?></p>
                                    <?php if ($poi['opening_hours']): ?>
                                        <p><strong>Hours:</strong> <?php echo htmlspecialchars($poi['opening_hours']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="distance-info" id="distance-<?php echo $poi['id']; ?>" style="display: none;"></div>
                                <div class="mt-2" style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                    <button class="btn btn-primary show-on-map" 
                                            data-lat="<?php echo $poi['latitude']; ?>" 
                                            data-lng="<?php echo $poi['longitude']; ?>" 
                                            data-name="<?php echo htmlspecialchars($poi['name']); ?>"
                                            data-id="<?php echo $poi['id']; ?>">
                                        Show on Map
                                    </button>
                                    <button class="btn btn-success get-directions" 
                                            data-lat="<?php echo $poi['latitude']; ?>" 
                                            data-lng="<?php echo $poi['longitude']; ?>" 
                                            data-name="<?php echo htmlspecialchars($poi['name']); ?>"
                                            style="display: none;">
                                        Get Directions
                                    </button>
                                    <a href="poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-warning">Details</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine/dist/leaflet-routing-machine.js"></script>
    <script>
        let map;
        let userLocation = null;
        let markers = [];
        let poiLayerGroup = null;
        let routingControl = null;
        let userMarker = null;

        // POI data from PHP
        const pois = <?php echo json_encode($pois); ?>;
        const selectedPoi = <?php echo json_encode($selected_poi); ?>;

        function getMarkerIcon(category) {
            const icons = {
                'attraction': 'red',
                'restaurant': 'orange', 
                'accommodation': 'blue',
                'cultural': 'violet',
                'historical': 'green'
            };
            const color = icons[category] || 'red';
            return new L.Icon({
                iconUrl: `https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-${color}.png`,
                shadowUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
        }

        function getUserLocationIcon() {
            return new L.Icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
                shadowUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            });
        }

        function initMap() {
            // Center map on Taal, Batangas
            const taalCenter = [13.8781, 121.1537];
            let center = taalCenter;
            let zoom = 13;
            
            if (selectedPoi) {
                center = [parseFloat(selectedPoi.latitude), parseFloat(selectedPoi.longitude)];
                zoom = 16;
            }

            map = L.map('map').setView(center, zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            poiLayerGroup = L.layerGroup().addTo(map);
            addPOIMarkers();

            if (selectedPoi) {
                showPOIOnMap(parseFloat(selectedPoi.latitude), parseFloat(selectedPoi.longitude), selectedPoi.name, selectedPoi.id);
            }
        }

        function addPOIMarkers() {
            poiLayerGroup.clearLayers();
            markers = [];
            
            pois.forEach(poi => {
                const marker = L.marker([parseFloat(poi.latitude), parseFloat(poi.longitude)], {
                    title: poi.name,
                    icon: getMarkerIcon(poi.category)
                }).addTo(poiLayerGroup);

                // Enhanced popup content
                const popupContent = createPopupContent(poi);
                marker.bindPopup(popupContent);

                marker.on('click', function() {
                    showPOIDetails(poi);
                    // Log POI view if user is logged in
                    <?php if (isLoggedIn()): ?>
                    logPOIView(poi.id);
                    <?php endif; ?>
                });

                markers.push({ marker, poi });
            });
        }

        function createPopupContent(poi) {
            const distanceInfo = userLocation ? 
                `<div class="distance-info">Distance: ${formatDistance(calculateDistance(userLocation.lat, userLocation.lng, parseFloat(poi.latitude), parseFloat(poi.longitude)))}</div>` : '';
            
            return `
                <div class="poi-popup">
                    <h4>${poi.name}</h4>
                    <div class="category">${poi.category.charAt(0).toUpperCase() + poi.category.slice(1)}</div>
                    <div class="rating">${'⭐'.repeat(poi.rating)}${'☆'.repeat(5-poi.rating)} (${poi.rating})</div>
                    <div class="description">${poi.description.substring(0, 100)}...</div>
                    ${distanceInfo}
                    <div class="actions">
                        <a href="poi-details.php?id=${poi.id}" class="btn btn-primary" target="_blank">View Details</a>
                        ${userLocation ? `<button class="btn btn-success" onclick="getDirections(${poi.latitude}, ${poi.longitude}, '${poi.name}')">Get Directions</button>` : ''}
                    </div>
                </div>
            `;
        }

        function showPOIOnMap(lat, lng, name, poiId) {
            map.setView([lat, lng], 16);
            
            // Find and open the corresponding marker popup
            markers.forEach(({ marker, poi }) => {
                if (parseFloat(poi.latitude) === lat && parseFloat(poi.longitude) === lng) {
                    marker.openPopup();
                }
            });
        }

        function showPOIDetails(poi) {
            // Update the popup content with fresh distance info
            const marker = markers.find(m => m.poi.id === poi.id);
            if (marker) {
                const popupContent = createPopupContent(poi);
                marker.marker.setPopupContent(popupContent);
            }
        }

        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };

                        // Remove existing user marker
                        if (userMarker) {
                            map.removeLayer(userMarker);
                        }

                        // Add user location marker
                        userMarker = L.marker([userLocation.lat, userLocation.lng], {
                            title: 'Your Location',
                            icon: getUserLocationIcon()
                        }).addTo(map).bindPopup('Your Location').openPopup();

                        map.setView([userLocation.lat, userLocation.lng], 15);
                        
                        // Show direction buttons and update distances
                        document.querySelectorAll('.get-directions').forEach(btn => {
                            btn.style.display = 'inline-block';
                        });
                        
                        updateDistances();
                        updatePopupContents();
                    },
                    (error) => {
                        alert('Error getting your location: ' + error.message);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 300000
                    }
                );
            } else {
                alert('Geolocation is not supported by this browser.');
            }
        }

        function updateDistances() {
            if (!userLocation) return;

            const poiCards = document.querySelectorAll('.poi-card');
            poiCards.forEach(card => {
                const lat = parseFloat(card.dataset.lat);
                const lng = parseFloat(card.dataset.lng);
                const poiId = card.dataset.id;
                const distance = calculateDistance(userLocation.lat, userLocation.lng, lat, lng);

                const distanceElement = document.getElementById(`distance-${poiId}`);
                if (distanceElement) {
                    distanceElement.textContent = `Distance from your location: ${formatDistance(distance)}`;
                    distanceElement.style.display = 'block';
                }
            });
        }

        function updatePopupContents() {
            markers.forEach(({ marker, poi }) => {
                const popupContent = createPopupContent(poi);
                marker.setPopupContent(popupContent);
            });
        }

        function getDirections(destLat, destLng, destName) {
            if (!userLocation) {
                alert('Please get your location first!');
                return;
            }

            showLoading(true);

            // Remove existing routing control
            if (routingControl) {
                map.removeControl(routingControl);
            }

            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(userLocation.lat, userLocation.lng),
                    L.latLng(destLat, destLng)
                ],
                routeWhileDragging: false,
                addWaypoints: false,
                createMarker: function() { return null; }, // Don't create additional markers
                lineOptions: {
                    styles: [{ color: '#6366f1', weight: 4, opacity: 0.8 }]
                }
            }).on('routesfound', function(e) {
                showLoading(false);
                const routes = e.routes;
                const summary = routes[0].summary;
                
                showDirectionsPanel(routes[0], destName, summary);
                document.getElementById('clear-directions').style.display = 'inline-block';
            }).on('routingerror', function(e) {
                showLoading(false);
                alert('Could not find a route to this destination. Please try again.');
            }).addTo(map);
        }

        function showDirectionsPanel(route, destName, summary) {
            const panel = document.getElementById('directions-panel');
            const summaryDiv = document.getElementById('directions-summary');
            const stepsDiv = document.getElementById('directions-steps');

            // Update summary
            const distance = (summary.totalDistance / 1000).toFixed(1);
            const time = Math.round(summary.totalTime / 60);
            summaryDiv.innerHTML = `
                <div style="background: var(--surface); padding: 1rem; border-radius: var(--radius-md);">
                    <h4 style="margin: 0 0 0.5rem 0;">Route to ${destName}</h4>
                    <p style="margin: 0; color: var(--text-secondary);">
                        <strong>Distance:</strong> ${distance} km &nbsp;|&nbsp; 
                        <strong>Estimated time:</strong> ${time} minutes
                    </p>
                </div>
            `;

            // Update steps
            stepsDiv.innerHTML = '';
            route.instructions.forEach((instruction, index) => {
                const stepDiv = document.createElement('div');
                stepDiv.className = 'direction-step';
                stepDiv.innerHTML = `
                    <div class="step-icon">${index + 1}</div>
                    <div>
                        <div style="font-weight: 600; margin-bottom: 0.25rem;">${instruction.text}</div>
                        <div style="font-size: 0.875rem; color: var(--text-muted);">
                            ${(instruction.distance / 1000).toFixed(1)} km
                        </div>
                    </div>
                `;
                stepsDiv.appendChild(stepDiv);
            });

            panel.classList.add('active');
        }

        function clearDirections() {
            if (routingControl) {
                map.removeControl(routingControl);
                routingControl = null;
            }
            
            document.getElementById('directions-panel').classList.remove('active');
            document.getElementById('clear-directions').style.display = 'none';
        }

        function showLoading(show) {
            const overlay = document.getElementById('loading-overlay');
            if (show) {
                overlay.classList.remove('hidden');
            } else {
                overlay.classList.add('hidden');
            }
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371000; // Earth's radius in meters
            const dLat = (lat2 - lat1) * Math.PI / 180;
            const dLon = (lon2 - lon1) * Math.PI / 180;
            const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                      Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                      Math.sin(dLon/2) * Math.sin(dLon/2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
            return R * c;
        }

        function formatDistance(distance) {
            if (distance < 1000) {
                return Math.round(distance) + ' m';
            } else {
                return (distance / 1000).toFixed(1) + ' km';
            }
        }

        function filterPOIs(category) {
            const poiCards = document.querySelectorAll('.poi-card');
            markers.forEach(({ marker, poi }) => {
                if (category === '' || poi.category === category) {
                    marker.addTo(poiLayerGroup);
                } else {
                    poiLayerGroup.removeLayer(marker);
                }
            });

            poiCards.forEach(card => {
                if (category === '' || card.dataset.category === category) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function searchPOIs(searchTerm) {
            const term = searchTerm.toLowerCase();
            const poiCards = document.querySelectorAll('.poi-card');
            
            markers.forEach(({ marker, poi }) => {
                const matches = poi.name.toLowerCase().includes(term) || 
                               poi.description.toLowerCase().includes(term) ||
                               poi.category.toLowerCase().includes(term);
                
                if (term === '' || matches) {
                    marker.addTo(poiLayerGroup);
                } else {
                    poiLayerGroup.removeLayer(marker);
                }
            });

            poiCards.forEach(card => {
                const matches = card.dataset.name.toLowerCase().includes(term);
                if (term === '' || matches) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function logPOIView(poiId) {
            fetch('log-activity.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=view_poi&poi_id=${poiId}`
            }).catch(error => {
                console.log('Activity logging failed:', error);
            });
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            initMap();

            document.getElementById('get-location').addEventListener('click', getUserLocation);
            document.getElementById('clear-directions').addEventListener('click', clearDirections);
            document.getElementById('close-directions').addEventListener('click', clearDirections);
            
            document.getElementById('category-filter').addEventListener('change', (e) => {
                filterPOIs(e.target.value);
            });

            document.getElementById('search-poi').addEventListener('input', (e) => {
                searchPOIs(e.target.value);
            });

            document.querySelectorAll('.show-on-map').forEach(button => {
                button.addEventListener('click', (e) => {
                    const lat = parseFloat(e.target.dataset.lat);
                    const lng = parseFloat(e.target.dataset.lng);
                    const name = e.target.dataset.name;
                    const poiId = e.target.dataset.id;
                    showPOIOnMap(lat, lng, name, poiId);
                });
            });

            document.querySelectorAll('.get-directions').forEach(button => {
                button.addEventListener('click', (e) => {
                    const lat = parseFloat(e.target.dataset.lat);
                    const lng = parseFloat(e.target.dataset.lng);
                    const name = e.target.dataset.name;
                    getDirections(lat, lng, name);
                });
            });
        });
    </script>
</body>
</html>

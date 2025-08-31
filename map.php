<?php
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

// Get all points of interest
$query = "SELECT * FROM points_of_interest ORDER BY name";
$stmt = $db->prepare($query);
$stmt->execute();
$pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get specific POI if requested
$selected_poi = null;
if (isset($_GET['poi'])) {
    $poi_id = (int)$_GET['poi'];
    $query = "SELECT * FROM points_of_interest WHERE id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$poi_id]);
    $selected_poi = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <style>
        #map { height: 500px; width: 100%; }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">Explore Taal with Interactive Map</h1>
            
            <!-- Map Controls -->
            <div class="card mb-3">
                <div class="form-group">
                    <label for="category-filter">Filter by Category:</label>
                    <select id="category-filter" class="form-control" style="max-width: 200px; display: inline-block;">
                        <option value="">All Categories</option>
                        <option value="attraction">Attractions</option>
                        <option value="restaurant">Restaurants</option>
                        <option value="accommodation">Accommodations</option>
                        <option value="cultural">Cultural Sites</option>
                        <option value="historical">Historical Sites</option>
                    </select>
                </div>
                <button id="get-location" class="btn btn-success">📍 Get My Location</button>
                <button id="get-directions" class="btn btn-primary" style="display: none;">🗺️ Get Directions</button>
            </div>
            
            <!-- Map Container -->
            <div id="map"></div>
            
            <!-- POI List -->
            <div class="card mt-3">
                <h3>Points of Interest</h3>
                <div id="poi-list" class="poi-grid">
                    <?php foreach ($pois as $poi): ?>
                        <div class="poi-card" data-category="<?php echo $poi['category']; ?>" data-lat="<?php echo $poi['latitude']; ?>" data-lng="<?php echo $poi['longitude']; ?>">
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
                                <div class="mt-2">
                                    <button class="btn btn-primary show-on-map" data-lat="<?php echo $poi['latitude']; ?>" data-lng="<?php echo $poi['longitude']; ?>" data-name="<?php echo htmlspecialchars($poi['name']); ?>">Show on Map</button>
                                    <a href="poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-success">Details</a>
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
    <script>
        let map;
        let userLocation = null;
        let markers = [];
        let poiLayerGroup = null;

        // POI data from PHP
        const pois = <?php echo json_encode($pois); ?>;
        const selectedPoi = <?php echo json_encode($selected_poi); ?>;

        function getMarkerIcon(category) {
            // Use colored marker icons from https://github.com/pointhi/leaflet-color-markers
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
                showPOIOnMap(parseFloat(selectedPoi.latitude), parseFloat(selectedPoi.longitude), selectedPoi.name);
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

                marker.bindPopup(`
                    <div style="max-width: 200px;">
                        <h4>${poi.name}</h4>
                        <p><strong>Category:</strong> ${poi.category}</p>
                        <p><strong>Rating:</strong> ${poi.rating}/5</p>
                        <p>${poi.description.substring(0, 100)}...</p>
                        <a href="poi-details.php?id=${poi.id}" target="_blank">View Details</a>
                    </div>
                `);

                markers.push({ marker, poi });
            });
        }

        function showPOIOnMap(lat, lng, name) {
            map.setView([lat, lng], 16);
            // Optionally open popup for the marker
            markers.forEach(({ marker, poi }) => {
                if (parseFloat(poi.latitude) === lat && parseFloat(poi.longitude) === lng) {
                    marker.openPopup();
                }
            });
        }

        function getUserLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        userLocation = {
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        };

                        // Add user location marker
                        L.marker([userLocation.lat, userLocation.lng], {
                            title: 'Your Location',
                            icon: new L.Icon({
                                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-blue.png',
                                shadowUrl: 'https://unpkg.com/leaflet@1.7.1/dist/images/marker-shadow.png',
                                iconSize: [25, 41],
                                iconAnchor: [12, 41],
                                popupAnchor: [1, -34],
                                shadowSize: [41, 41]
                            })
                        }).addTo(map).bindPopup('Your Location').openPopup();

                        map.setView([userLocation.lat, userLocation.lng], 15);
                        document.getElementById('get-directions').style.display = 'inline-block';

                        // Calculate distances to POIs
                        updateDistances();
                    },
                    (error) => {
                        alert('Error getting your location: ' + error.message);
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
                const distance = calculateDistance(userLocation.lat, userLocation.lng, lat, lng);

                let distanceElement = card.querySelector('.distance');
                if (!distanceElement) {
                    distanceElement = document.createElement('p');
                    distanceElement.className = 'distance';
                    distanceElement.style.color = '#666';
                    distanceElement.style.fontSize = '0.9rem';
                    card.querySelector('.poi-content').appendChild(distanceElement);
                }
                distanceElement.textContent = `Distance: ${formatDistance(distance)}`;
            });
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

        document.addEventListener('DOMContentLoaded', function() {
            initMap();

            document.getElementById('get-location').addEventListener('click', getUserLocation);
            document.getElementById('category-filter').addEventListener('change', (e) => {
                filterPOIs(e.target.value);
            });

            document.querySelectorAll('.show-on-map').forEach(button => {
                button.addEventListener('click', (e) => {
                    const lat = parseFloat(e.target.dataset.lat);
                    const lng = parseFloat(e.target.dataset.lng);
                    const name = e.target.dataset.name;
                    showPOIOnMap(lat, lng, name);
                });
            });
        });
    </script>
</body>
</html>

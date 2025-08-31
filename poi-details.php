<?php
require_once 'config/config.php';

if (!isset($_GET['id'])) {
    header('Location: places.php');
    exit();
}

$poi_id = (int)$_GET['id'];

$database = new Database();
$db = $database->getConnection();

// Get POI details
$query = "SELECT * FROM points_of_interest WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$poi_id]);
$poi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poi) {
    header('Location: places.php');
    exit();
}

if (isLoggedIn()) {
    logUserActivity($_SESSION['user_id'], 'view_poi', $poi_id, [
        'poi_name' => $poi['name'],
        'poi_category' => $poi['category']
    ]);
}

// Update view count
$view_query = "UPDATE points_of_interest SET view_count = view_count + 1 WHERE id = ?";
$view_stmt = $db->prepare($view_query);
$view_stmt->execute([$poi_id]);

// Get reviews
$query = "SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.poi_id = ? ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$poi_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if user has favorited this POI
$is_favorited = false;
if (isLoggedIn()) {
    $query = "SELECT id FROM user_favorites WHERE user_id = ? AND poi_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $poi_id]);
    $is_favorited = $stmt->rowCount() > 0;
}

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isLoggedIn() && isset($_POST['submit_review'])) {
    $rating = (int)$_POST['rating'];
    $comment = sanitizeInput($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5) {
        $query = "INSERT INTO reviews (user_id, poi_id, rating, comment) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id'], $poi_id, $rating, $comment]);
        
        logUserActivity($_SESSION['user_id'], 'add_review', $poi_id, [
            'rating' => $rating,
            'has_comment' => !empty($comment)
        ]);
        
        // Update POI average rating
        $query = "UPDATE points_of_interest SET rating = (SELECT AVG(rating) FROM reviews WHERE poi_id = ?) WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$poi_id, $poi_id]);
        
        header("Location: poi-details.php?id=$poi_id");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($poi['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <div class="card">
                <!-- POI Header -->
                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 2rem;">
                    <div>
                        <div class="poi-category"><?php echo ucfirst($poi['category']); ?></div>
                        <h1><?php echo htmlspecialchars($poi['name']); ?></h1>
                        <div class="poi-rating" style="font-size: 1.2rem;">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <?php echo $i <= $poi['rating'] ? '⭐' : '☆'; ?>
                            <?php endfor; ?>
                            (<?php echo $poi['rating']; ?>/5)
                        </div>
                    </div>
                    <div>
                        <?php if (isLoggedIn()): ?>
                            <button class="btn <?php echo $is_favorited ? 'btn-danger' : 'btn-warning'; ?> add-favorite" data-poi-id="<?php echo $poi['id']; ?>">
                                <?php echo $is_favorited ? '💔 Remove Favorite' : '❤️ Add to Favorites'; ?>
                            </button>
                        <?php endif; ?>
                        <a href="map.php?poi=<?php echo $poi['id']; ?>" class="btn btn-success">📍 Show on Map</a>
                    </div>
                </div>
                
                <!-- POI Image -->
                <?php if ($poi['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" alt="<?php echo htmlspecialchars($poi['name']); ?>" style="width: 100%; max-height: 400px; object-fit: cover; border-radius: 10px; margin-bottom: 2rem;">
                <?php endif; ?>
                
                <!-- POI Information -->
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                    <div>
                        <h3>About</h3>
                        <p><?php echo nl2br(htmlspecialchars($poi['description'])); ?></p>
                    </div>
                    <div>
                        <h3>Information</h3>
                        <div class="poi-info">
                            <p><strong>📍 Address:</strong><br><?php echo htmlspecialchars($poi['address']); ?></p>
                            <?php if ($poi['contact_info']): ?>
                                <p><strong>📞 Contact:</strong><br><?php echo htmlspecialchars($poi['contact_info']); ?></p>
                            <?php endif; ?>
                            <?php if ($poi['opening_hours']): ?>
                                <p><strong>🕒 Opening Hours:</strong><br><?php echo htmlspecialchars($poi['opening_hours']); ?></p>
                            <?php endif; ?>
                            <?php if ($poi['price_range']): ?>
                                <p><strong>💰 Price Range:</strong><br><?php echo htmlspecialchars($poi['price_range']); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Map -->
                <div style="margin-bottom: 2rem;">
                    <h3>Location</h3>
                    <div id="poi-map" style="height: 300px; border-radius: 10px;"></div>
                </div>
            </div>
            
            <!-- Reviews Section -->
            <div class="card">
                <h3>Reviews (<?php echo count($reviews); ?>)</h3>
                
                <?php if (isLoggedIn()): ?>
                    <!-- Add Review Form -->
                    <div style="background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-bottom: 2rem;">
                        <h4>Write a Review</h4>
                        <form method="POST">
                            <div class="form-group">
                                <label for="rating">Rating:</label>
                                <select id="rating" name="rating" class="form-control" required style="max-width: 200px;">
                                    <option value="">Select Rating</option>
                                    <option value="5">⭐⭐⭐⭐⭐ (5 stars)</option>
                                    <option value="4">⭐⭐⭐⭐☆ (4 stars)</option>
                                    <option value="3">⭐⭐⭐☆☆ (3 stars)</option>
                                    <option value="2">⭐⭐☆☆☆ (2 stars)</option>
                                    <option value="1">⭐☆☆☆☆ (1 star)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="comment">Your Review:</label>
                                <textarea id="comment" name="comment" class="form-control" rows="4" placeholder="Share your experience..."></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <a href="login.php">Login</a> to write a review.
                    </div>
                <?php endif; ?>
                
                <!-- Reviews List -->
                <?php if (empty($reviews)): ?>
                    <p>No reviews yet. Be the first to review this place!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php foreach ($reviews as $review): ?>
                            <div style="border: 1px solid #eee; padding: 1.5rem; border-radius: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <strong><?php echo htmlspecialchars($review['full_name']); ?></strong>
                                        <div class="poi-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php echo $i <= $review['rating'] ? '⭐' : '☆'; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small style="color: #666;"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></small>
                                </div>
                                <?php if ($review['comment']): ?>
                                    <p><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Initialize map
        function initPOIMap() {
            const poiLocation = { 
                lat: <?php echo $poi['latitude']; ?>, 
                lng: <?php echo $poi['longitude']; ?> 
            };
            
            const map = new google.maps.Map(document.getElementById('poi-map'), {
                zoom: 15,
                center: poiLocation,
                mapTypeId: 'roadmap'
            });
            
            const marker = new google.maps.Marker({
                position: poiLocation,
                map: map,
                title: '<?php echo addslashes($poi['name']); ?>'
            });
            
            const infoWindow = new google.maps.InfoWindow({
                content: `
                    <div style="max-width: 200px;">
                        <h4><?php echo addslashes($poi['name']); ?></h4>
                        <p><?php echo addslashes($poi['address']); ?></p>
                    </div>
                `
            });
            
            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });
        }
        
        // Add to favorites functionality
        document.querySelector('.add-favorite')?.addEventListener('click', function() {
            const poiId = this.dataset.poiId;
            
            fetch('add-favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'poi_id=' + poiId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    this.textContent = data.added ? '💔 Remove Favorite' : '❤️ Add to Favorites';
                    this.className = data.added ? 'btn btn-danger add-favorite' : 'btn btn-warning add-favorite';
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        });
    </script>
    
    <script async defer src="https://maps.googleapis.com/maps/api/js?key=<?php echo GOOGLE_MAPS_API_KEY; ?>&callback=initPOIMap"></script>
</body>
</html>

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
$query = "SELECT * FROM points_of_interest WHERE id = ? AND status = 'active'";
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

// Get reviews with pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 5;
$offset = ($page - 1) * $per_page;

$query = "SELECT r.*, u.full_name FROM reviews r JOIN users u ON r.user_id = u.id WHERE r.poi_id = ? ORDER BY r.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($query);
$stmt->execute([$poi_id]);
$reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total review count
$count_query = "SELECT COUNT(*) as total FROM reviews WHERE poi_id = ?";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute([$poi_id]);
$total_reviews = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_reviews / $per_page);

// Get review statistics
$stats_query = "SELECT 
    AVG(rating) as avg_rating,
    COUNT(*) as total_reviews,
    COUNT(CASE WHEN rating = 5 THEN 1 END) as five_star,
    COUNT(CASE WHEN rating = 4 THEN 1 END) as four_star,
    COUNT(CASE WHEN rating = 3 THEN 1 END) as three_star,
    COUNT(CASE WHEN rating = 2 THEN 1 END) as two_star,
    COUNT(CASE WHEN rating = 1 THEN 1 END) as one_star
FROM reviews WHERE poi_id = ?";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$poi_id]);
$review_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Check if user has favorited this POI
$is_favorited = false;
if (isLoggedIn()) {
    $query = "SELECT id FROM user_favorites WHERE user_id = ? AND poi_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['user_id'], $poi_id]);
    $is_favorited = $stmt->rowCount() > 0;
}

// Get similar POIs
$similar_query = "SELECT * FROM points_of_interest 
                  WHERE category = ? AND id != ? AND status = 'active' 
                  ORDER BY rating DESC, view_count DESC LIMIT 3";
$similar_stmt = $db->prepare($similar_query);
$similar_stmt->execute([$poi['category'], $poi_id]);
$similar_pois = $similar_stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isLoggedIn() && isset($_POST['submit_review'])) {
    $rating = (int)$_POST['rating'];
    $comment = sanitizeInput($_POST['comment']);
    
    if ($rating >= 1 && $rating <= 5) {
        // Check if user already reviewed this POI
        $check_query = "SELECT id FROM reviews WHERE user_id = ? AND poi_id = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$_SESSION['user_id'], $poi_id]);
        
        if ($check_stmt->rowCount() == 0) {
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
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($poi['name']); ?> - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css" />
    <style>
        .poi-header {
            background: linear-gradient(135deg, var(--surface) 0%, var(--background) 100%);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }
        
        .poi-image-gallery {
            position: relative;
            margin-bottom: 2rem;
        }
        
        .poi-main-image {
            width: 100%;
            height: 700px;
            object-fit: cover;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
        }
        
        .poi-info-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .info-card {
            background: var(--surface-elevated);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }
        
        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-light);
        }
        
        .info-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }
        
        .info-icon {
            width: 24px;
            height: 24px;
            background: var(--secondary);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.875rem;
            flex-shrink: 0;
            margin-top: 0.125rem;
        }
        
        .review-stats {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .rating-breakdown {
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 0.75rem;
            align-items: center;
            margin-bottom: 0.5rem;
        }
        
        .rating-bar {
            height: 8px;
            background: var(--border-light);
            border-radius: 4px;
            overflow: hidden;
        }
        
        .rating-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--warning) 0%, #f6ad55 100%);
            transition: width 0.3s ease;
        }
        
        .review-form {
            background: var(--surface);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border-light);
        }
        
        .star-rating {
            display: flex;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }
        
        .star-rating input {
            display: none;
        }
        
        .star-rating label {
            font-size: 1.5rem;
            color: var(--border);
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #f6ad55;
        }
        
        .review-card {
            background: var(--surface-elevated);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-sm);
        }
        
        .similar-pois {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 1rem;
        }
        
        .breadcrumb {
            margin-bottom: 1rem;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: var(--secondary);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 768px) {
            .poi-info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .poi-header {
                padding: 1.5rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
             <!-- Added breadcrumb navigation  -->
            <div class="breadcrumb">
                <a href="index.php">Home</a> > 
                <a href="places.php">Places</a> > 
                <a href="places.php?category=<?php echo $poi['category']; ?>"><?php echo ucfirst($poi['category']); ?></a> > 
                <?php echo htmlspecialchars($poi['name']); ?>
            </div>
            
             <!-- Enhanced POI header with better layout  -->
            <div class="poi-header">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
                    <div style="flex: 1; min-width: 300px;">
                        <div class="poi-category" style="margin-bottom: 0.5rem;"><?php echo ucfirst($poi['category']); ?></div>
                        <h1 style="margin-bottom: 1rem; font-size: clamp(1.75rem, 4vw, 2.5rem);"><?php echo htmlspecialchars($poi['name']); ?></h1>
                        <div style="display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem;">
                            <div class="poi-rating" style="font-size: 1.25rem;">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <?php echo $i <= round($poi['rating']) ? '⭐' : '☆'; ?>
                                <?php endfor; ?>
                                <span style="margin-left: 0.5rem; color: var(--text-secondary);">
                                    <?php echo number_format($poi['rating'], 1); ?>/5 (<?php echo $total_reviews; ?> reviews)
                                </span>
                            </div>
                            <div style="color: var(--text-muted); font-size: 0.875rem;">
                                <?php echo number_format($poi['view_count']); ?> views
                            </div>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <?php if (isLoggedIn()): ?>
                            <button class="btn <?php echo $is_favorited ? 'btn-danger' : 'btn-warning'; ?> add-favorite" data-poi-id="<?php echo $poi['id']; ?>">
                                <?php echo $is_favorited ? '💔 Remove Favorite' : '❤️ Add to Favorites'; ?>
                            </button>
                        <?php endif; ?>
                        <a href="map.php?poi=<?php echo $poi['id']; ?>" class="btn btn-success">📍 Show on Map</a>
                        <button class="btn btn-primary" onclick="shareLocation()">📤 Share</button>
                    </div>
                </div>
            </div>
            
             <!-- Enhanced image display  -->
            <?php if ($poi['image_url']): ?>
                <div class="poi-image-gallery">
                    <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" 
                         alt="<?php echo htmlspecialchars($poi['name']); ?>" 
                         class="poi-main-image">
                </div>
            <?php endif; ?>
            
             <!-- Improved information layout  -->
            <div class="poi-info-grid">
                <div>
                    <div class="info-card">
                        <h3 style="margin-bottom: 1.5rem;">About This Place</h3>
                        <p style="line-height: 1.7; color: var(--text-secondary);">
                            <?php echo nl2br(htmlspecialchars($poi['description'])); ?>
                        </p>
                    </div>
                </div>
                
                <div>
                    <div class="info-card">
                        <h3 style="margin-bottom: 1.5rem;">Details</h3>
                        
                        <div class="info-item">
                            <div class="info-icon">📍</div>
                            <div>
                                <strong>Address</strong><br>
                                <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($poi['address']); ?></span>
                            </div>
                        </div>
                        
                        <?php if ($poi['contact_info']): ?>
                            <div class="info-item">
                                <div class="info-icon">📞</div>
                                <div>
                                    <strong>Contact</strong><br>
                                    <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($poi['contact_info']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($poi['opening_hours']): ?>
                            <div class="info-item">
                                <div class="info-icon">🕒</div>
                                <div>
                                    <strong>Opening Hours</strong><br>
                                    <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($poi['opening_hours']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($poi['price_range']): ?>
                            <div class="info-item">
                                <div class="info-icon">💰</div>
                                <div>
                                    <strong>Price Range</strong><br>
                                    <span style="color: var(--text-secondary);"><?php echo htmlspecialchars($poi['price_range']); ?></span>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="info-item">
                            <div class="info-icon">📊</div>
                            <div>
                                <strong>Category</strong><br>
                                <span style="color: var(--text-secondary);"><?php echo ucfirst($poi['category']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
             <!-- Enhanced map section  -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Location & Directions</h3>
                <div id="poi-map" style="height: 400px; border-radius: var(--radius-lg); box-shadow: var(--shadow-md);"></div>
                <div style="margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
                    <button class="btn btn-primary" onclick="getDirections()">🗺️ Get Directions</button>
                    <button class="btn btn-success" onclick="openInMaps()">📱 Open in Maps App</button>
                </div>
            </div>
            
             <!-- Enhanced reviews section with statistics  -->
            <div class="card">
                <h3 style="margin-bottom: 1.5rem;">Reviews & Ratings</h3>
                
                <?php if ($review_stats['total_reviews'] > 0): ?>
                    <div class="review-stats">
                        <div style="display: grid; grid-template-columns: auto 1fr; gap: 2rem; align-items: center; margin-bottom: 1.5rem;">
                            <div style="text-align: center;">
                                <div style="font-size: 3rem; font-weight: 700; color: var(--text-primary);">
                                    <?php echo number_format($review_stats['avg_rating'], 1); ?>
                                </div>
                                <div style="color: #f6ad55; font-size: 1.25rem; margin-bottom: 0.5rem;">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= round($review_stats['avg_rating']) ? '⭐' : '☆'; ?>
                                    <?php endfor; ?>
                                </div>
                                <div style="color: var(--text-muted); font-size: 0.875rem;">
                                    Based on <?php echo $review_stats['total_reviews']; ?> reviews
                                </div>
                            </div>
                            
                            <div>
                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                    <div class="rating-breakdown">
                                        <span style="font-size: 0.875rem;"><?php echo $star; ?> ⭐</span>
                                        <div class="rating-bar">
                                            <?php 
                                            $count = $review_stats[strtolower(number_to_words($star)) . '_star'];
                                            $percentage = $review_stats['total_reviews'] > 0 ? ($count / $review_stats['total_reviews']) * 100 : 0;
                                            ?>
                                            <div class="rating-fill" style="width: <?php echo $percentage; ?>%;"></div>
                                        </div>
                                        <span style="font-size: 0.875rem; color: var(--text-muted);"><?php echo $count; ?></span>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isLoggedIn()): ?>
                     <!-- Enhanced review form  -->
                    <div class="review-form">
                        <h4 style="margin-bottom: 1rem;">Write a Review</h4>
                        <form method="POST">
                            <div class="form-group">
                                <label>Your Rating:</label>
                                <div class="star-rating">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                        <input type="radio" id="star<?php echo $i; ?>" name="rating" value="<?php echo $i; ?>" required>
                                        <label for="star<?php echo $i; ?>">⭐</label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="comment">Your Review:</label>
                                <textarea id="comment" name="comment" class="form-control" rows="4" 
                                         placeholder="Share your experience with other travelers..."></textarea>
                            </div>
                            <button type="submit" name="submit_review" class="btn btn-primary">Submit Review</button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <a href="login.php">Login</a> to write a review and help other travelers.
                    </div>
                <?php endif; ?>
                
                 <!-- Enhanced reviews display  -->
                <?php if (empty($reviews)): ?>
                    <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                        <div style="font-size: 3rem; margin-bottom: 1rem;">📝</div>
                        <p>No reviews yet. Be the first to review this place!</p>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 2rem;">
                        <?php foreach ($reviews as $review): ?>
                            <div class="review-card">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem;">
                                    <div>
                                        <strong style="color: var(--text-primary);"><?php echo htmlspecialchars($review['full_name']); ?></strong>
                                        <div style="color: #f6ad55; margin-top: 0.25rem;">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <?php echo $i <= $review['rating'] ? '⭐' : '☆'; ?>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <small style="color: var(--text-muted);"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></small>
                                </div>
                                <?php if ($review['comment']): ?>
                                    <p style="line-height: 1.6; color: var(--text-secondary);"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                        
                         <!-- Added pagination for reviews  -->
                        <?php if ($total_pages > 1): ?>
                            <div class="pagination" style="margin-top: 2rem;">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <a href="?id=<?php echo $poi_id; ?>&page=<?php echo $i; ?>" 
                                       class="<?php echo $i == $page ? 'current' : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
             <!-- Added similar places section  -->
            <?php if (!empty($similar_pois)): ?>
                <div class="card">
                    <h3 style="margin-bottom: 1.5rem;">Similar Places</h3>
                    <div class="similar-pois">
                        <?php foreach ($similar_pois as $similar): ?>
                            <div class="poi-card">
                                <?php if ($similar['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($similar['image_url']); ?>" 
                                         alt="<?php echo htmlspecialchars($similar['name']); ?>" 
                                         class="poi-image">
                                <?php endif; ?>
                                <div class="poi-content">
                                    <div class="poi-category"><?php echo ucfirst($similar['category']); ?></div>
                                    <h4 class="poi-title"><?php echo htmlspecialchars($similar['name']); ?></h4>
                                    <div class="poi-rating">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <?php echo $i <= $similar['rating'] ? '⭐' : '☆'; ?>
                                        <?php endfor; ?>
                                        (<?php echo $similar['rating']; ?>)
                                    </div>
                                    <p class="poi-description"><?php echo htmlspecialchars(substr($similar['description'], 0, 100)) . '...'; ?></p>
                                    <div class="mt-2">
                                        <a href="poi-details.php?id=<?php echo $similar['id']; ?>" class="btn btn-primary">View Details</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
    <script>
        let map;
        let userLocation = null;
        
        function initPOIMap() {
            const poiLocation = [<?php echo $poi['latitude']; ?>, <?php echo $poi['longitude']; ?>];
            
            map = L.map('poi-map').setView(poiLocation, 16);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            const marker = L.marker(poiLocation).addTo(map);
            marker.bindPopup(`
                <div style="max-width: 200px;">
                    <h4><?php echo addslashes($poi['name']); ?></h4>
                    <p><?php echo addslashes($poi['address']); ?></p>
                </div>
            `).openPopup();
        }
        
        function getDirections() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        const userLat = position.coords.latitude;
                        const userLng = position.coords.longitude;
                        const poiLat = <?php echo $poi['latitude']; ?>;
                        const poiLng = <?php echo $poi['longitude']; ?>;
                        
                        // Open in Google Maps with directions
                        const url = `https://www.google.com/maps/dir/${userLat},${userLng}/${poiLat},${poiLng}`;
                        window.open(url, '_blank');
                    },
                    (error) => {
                        // Fallback to just showing the location
                        openInMaps();
                    }
                );
            } else {
                openInMaps();
            }
        }
        
        function openInMaps() {
            const lat = <?php echo $poi['latitude']; ?>;
            const lng = <?php echo $poi['longitude']; ?>;
            const name = encodeURIComponent('<?php echo addslashes($poi['name']); ?>');
            
            // Try to open in native maps app, fallback to Google Maps
            const url = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}&query_place_id=${name}`;
            window.open(url, '_blank');
        }
        
        function shareLocation() {
            const url = window.location.href;
            const title = '<?php echo addslashes($poi['name']); ?>';
            const text = `Check out ${title} in Taal, Batangas!`;
            
            if (navigator.share) {
                navigator.share({
                    title: title,
                    text: text,
                    url: url
                });
            } else {
                // Fallback to copying URL
                navigator.clipboard.writeText(url).then(() => {
                    alert('Location URL copied to clipboard!');
                });
            }
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
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initPOIMap);
    </script>
</body>
</html>

<?php
// Helper function for rating breakdown
function number_to_words($number) {
    $words = ['one', 'two', 'three', 'four', 'five'];
    return $words[$number - 1] ?? 'unknown';
}
?>

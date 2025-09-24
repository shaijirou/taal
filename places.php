<?php
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Build query
$query = "SELECT * FROM points_of_interest WHERE 1=1";
$params = [];

if ($category) {
    $query .= " AND category = ?";
    $params[] = $category;
}

if ($search) {
    $query .= " AND (name LIKE ? OR description LIKE ? OR address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query .= " ORDER BY rating DESC, name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$categoryQuery = "SELECT DISTINCT category FROM points_of_interest ORDER BY category";
$categoryStmt = $db->prepare($categoryQuery);
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Places to Visit - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">Discover Amazing Places in Taal</h1>
            
            <!-- Search and Filter -->
            <div class="card mb-3">
                <form method="GET" class="form-group">
                    <div style="display: flex; gap: 1rem; align-items: end; flex-wrap: wrap;">
                        <div style="flex: 1; min-width: 200px;">
                            <label for="search">Search Places:</label>
                            <input type="text" id="search" name="search" class="form-control" placeholder="Search by name, description, or location..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div>
                            <label for="category">Category:</label>
                            <select id="category" name="category" class="form-control">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat; ?>" <?php echo $category === $cat ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <button type="submit" class="btn btn-primary">Search</button>
                            <a href="places.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Results count -->
            <div class="mb-3">
                <p><strong><?php echo count($pois); ?></strong> places found</p>
            </div>
            
            <!-- Places Grid -->
            <div class="poi-grid">
                <?php if (empty($pois)): ?>
                    <div class="card text-center" style="grid-column: 1 / -1;">
                        <h3>No places found</h3>
                        <p>Try adjusting your search criteria or browse all places.</p>
                        <a href="places.php" class="btn btn-primary">View All Places</a>
                    </div>
                <?php else: ?>
                    <?php foreach ($pois as $poi): ?>
                        <div class="poi-card">
                            <?php if ($poi['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" alt="<?php echo htmlspecialchars($poi['name']); ?>" class="poi-image">
                            <?php else: ?>
                                <div class="poi-image" style="background: linear-gradient(45deg, #3498db, #2c3e50); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
                                    <?php
                                    $icons = [
                                        'attraction' => '🏞️',
                                        'restaurant' => '🍽️',
                                        'accommodation' => '🏨',
                                        'cultural' => '🏛️',
                                        'historical' => '🏰'
                                    ];
                                    echo $icons[$poi['category']] ?? '📍';
                                    ?>
                                </div>
                            <?php endif; ?>
                            <div class="poi-content">
                                <div class="poi-category"><?php echo ucfirst($poi['category']); ?></div>
                                <h3 class="poi-title"><?php echo htmlspecialchars($poi['name']); ?></h3>
                                <div class="poi-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $poi['rating'] ? '⭐' : '☆'; ?>
                                    <?php endfor; ?>
                                    (<?php echo $poi['rating']; ?>)
                                </div>
                                <p class="poi-description"><?php echo htmlspecialchars(substr($poi['description'], 0, 120)) . '...'; ?></p>
                                <div class="poi-info">
                                    <p><strong>📍 Address:</strong> <?php echo htmlspecialchars($poi['address']); ?></p>
                                    <?php if ($poi['opening_hours']): ?>
                                        <p><strong>🕒 Hours:</strong> <?php echo htmlspecialchars($poi['opening_hours']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($poi['price_range']): ?>
                                        <p><strong>💰 Price Range:</strong> <?php echo htmlspecialchars($poi['price_range']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($poi['contact_info']): ?>
                                        <p><strong>📞 Contact:</strong> <?php echo htmlspecialchars($poi['contact_info']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <!-- Updated button container for consistent alignment -->
                                <div class="poi-buttons">
                                    <a href="poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-primary">View Details</a>
                                    <a href="map.php?poi=<?php echo $poi['id']; ?>" class="btn btn-success">Show on Map</a>
                                    <?php if (isLoggedIn()): ?>
                                        <button class="btn btn-warning add-favorite" data-poi-id="<?php echo $poi['id']; ?>">❤️ Favorite</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Add to favorites functionality
        document.querySelectorAll('.add-favorite').forEach(button => {
            button.addEventListener('click', function() {
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
                        this.textContent = data.added ? '❤️ Added!' : '💔 Removed';
                        this.style.background = data.added ? '#27ae60' : '#e74c3c';
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                });
            });
        });
    </script>
</body>
</html>

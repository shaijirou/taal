<?php
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

// Get featured points of interest
$query = "SELECT * FROM points_of_interest ORDER BY rating DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$featured_pois = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <!-- Hero Section -->
            <section class="hero mb-4">
                <div class="card text-center">
                    <h1>Welcome to Ala Eh! </h1>
                    <p class="mb-3">Your intelligent guide to exploring the beautiful Municipality of Taal, Batangas. Discover hidden gems, local cuisine, and rich cultural heritage with our ChatBot tourism assistant.</p>
                    <div>
                        <a href="map.php" class="btn btn-primary">Explore Map</a>
                        <a href="ai-guide.php" class="btn btn-success">Chat with ChatBot Guide</a>
                        <?php if (!isLoggedIn()): ?>
                            <a href="register.php" class="btn btn-warning">Get Started</a>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Features Section -->
            <section class="features mb-4">
                <h2 class="text-center mb-3">Why Choose Ala Eh?</h2>
                <div class="poi-grid">
                    <!-- <div class="card">
                        <h3>🗺️ Interactive Map</h3>
                        <p>Real-time GPS navigation with accurate directions to all tourist destinations in Taal.</p>
                    </div> -->
                    <div class="card">
                        <h3>🤖 ChatBot Tour Guide</h3>
                        <p>Chat with our intelligent assistant for personalized recommendations and local insights.</p>
                    </div>
                    <div class="card">
                        <h3>📍 Points of Interest</h3>
                        <p>Discover attractions, restaurants, accommodations, and cultural sites with detailed information.</p>
                    </div>
                    <div class="card">
                        <h3>⭐ Reviews & Ratings</h3>
                        <p>Read authentic reviews from fellow travelers and share your own experiences.</p>
                    </div>
                </div>
            </section>

            <!-- Featured Places -->
            <section class="featured-places">
                <h2 class="text-center mb-3">Featured Places</h2>
                <div class="poi-grid">
                    <?php foreach ($featured_pois as $poi): ?>
                        <div class="poi-card">
                            <?php if ($poi['image_url']): ?>
                                <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" alt="<?php echo htmlspecialchars($poi['name']); ?>" class="poi-image">
                            <?php endif; ?>
                            <div class="poi-content">
                                <div class="poi-category"><?php echo ucfirst($poi['category']); ?></div>
                                <h3 class="poi-title"><?php echo htmlspecialchars($poi['name']); ?></h3>
                                <div class="poi-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php if ($i <= $poi['rating']): ?>
                                            ⭐
                                        <?php else: ?>
                                            ☆
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    (<?php echo $poi['rating']; ?>)
                                </div>
                                <p class="poi-description"><?php echo htmlspecialchars(substr($poi['description'], 0, 100)) . '...'; ?></p>
                                <div class="poi-info">
                                    <p><strong>Address:</strong> <?php echo htmlspecialchars($poi['address']); ?></p>
                                    <?php if ($poi['opening_hours']): ?>
                                        <p><strong>Hours:</strong> <?php echo htmlspecialchars($poi['opening_hours']); ?></p>
                                    <?php endif; ?>
                                    <?php if ($poi['price_range']): ?>
                                        <p><strong>Price Range:</strong> <?php echo htmlspecialchars($poi['price_range']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <!-- Updated button container for consistent alignment -->
                                <div class="poi-buttons">
                                    <a href="poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-primary">View Details</a>
                                    <a href="map.php?poi=<?php echo $poi['id']; ?>" class="btn btn-success">Show on Map</a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>
</body>
</html>

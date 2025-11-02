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
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&display=swap" rel="stylesheet">
<style>
.video-container {
    width: 1038px;
    height: 514px;
    margin: 0 auto; /* center the video */
}

.video-container iframe {
    width: 100%;
    height: 100%;
}

/* Make video responsive on smaller screens */
@media (max-width: 1038px) {
    .video-container {
        width: 100%;
        height: auto;
        aspect-ratio: 1038 / 514; /* maintain aspect ratio */
    }
}
</style>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <!-- Hero Section -->
            <section class="hero mb-4">
                <div class="card text-center">
                    <h1 style="color: #7b3e19;">Welcome to Ala Eh! </h1>
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

            <!-- <div class="container my-4">
                <div class="card shadow-sm border-0">
                    <div class="card-body">
                    <h5 class="card-title text-center mb-3">TAAL HERITAGE TOWN Overview</h5>
                    <div class="ratio ratio-16x9">
                        <iframe width="914" height="514" src="https://www.youtube.com/embed/KDm05Xl7GXo" title="TAAL HERITAGE TOWN (TOURISM VIDEO)" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    <p class="card-text text-center mt-3 text-muted">
                        Video by Philippine Tourism Promotions Board
                    </p>
                    </div>
                </div>
            </div> -->
            <!-- Modern Video Section -->
            <section class="py-5" style="background: #f8fafc;">
                <div class="container">
                    <div class="card border-1 shadow-lg rounded-4 overflow-hidden" style="border: 2px solid rgba(123, 62, 25, 1);">
                           <h5 class="fw-bold text-uppercase mb-3" 
                                style="
                                    font-family: 'Cinzel', serif;
                                    background-color: #7b3e19; border: 1px solid rgba(229, 212, 177, 1);
                                    color: #fffaf3;
                                    padding: 12px;
                                    border-radius: 10px;
                                    letter-spacing: 1px;
                                    text-align: center;
                                    font-size: 24px;
                                ">
                                TAAL HERITAGE TOWN
                            </h5>


                       <!-- <div class="ratio ratio-16x9 bg-dark">
                        <video 
                            width="100%" 
                            height="514" 
                            controls 
                            autoplay 
                            muted
                            playsinline 
                            poster="images/thumbnail.jpg" 
                            class="rounded-0" 
                            style="border: 2px solid rgba(123, 62, 25, 1);">
                            
                            <source src="images/video.mp4" type="video/mp4">
                            Your browser does not support the video tag.
                        </video>
                        </div> -->

                       <div class="video-container">
    <iframe 
        src="https://www.youtube.com/embed/KDm05Xl7GXo" 
        title="TAAL HERITAGE TOWN (TOURISM VIDEO)" 
        frameborder="0" 
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" 
        referrerpolicy="strict-origin-when-cross-origin" 
        allowfullscreen>
    </iframe>
</div>


                        <div class="card-body text-center bg-white">
                            <p class="text-muted mb-0">
                            Discover the timeless charm of Taal Heritage Town — a place rich in history, culture, and architecture.  
                            <br>
                            <small>🎥 Video by Taal Tourism Promotions Board</small>
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Taal Information Section -->
            <div class="container my-5 p-4 rounded shadow" 
                style="background-color: #fffaf3; border: 1px solid rgba(229, 212, 177, 1);">
            
            <h3 class="text-center mb-4 text-uppercase" 
                style="font-family: 'Cinzel', serif; color: #7b3e19;">
                A Glimpse of Taal’s Rich History
            </h3>

            <div class="row align-items-center">
                <!-- Image -->
                <div class="col-md-5 text-center mb-3 mb-md-0">
                <img src="images/taal.png" 
                    class="img-fluid rounded shadow-sm" 
                    alt="Taal Heritage Houses" style="border: 3px solid #7b3e19; width: 100%; max-width: 700px;">
                </div>

                <!-- Text -->
                <div class="col-md-7">
                <p style="font-family: 'Georgia', serif; color: #4a3b2c; text-align: justify;">
                    The town of <strong>Taal</strong> in Batangas stands as a living museum of Spanish-era architecture and Filipino heritage. 
                    Founded in the late 1500s, it once served as a bustling center for trade, faith, and artistry in Southern Luzon. 
                    Its cobblestone streets are lined with ancestral houses, each telling stories of noble families and revolutions.
                </p>
                <p style="font-family: 'Georgia', serif; color: #4a3b2c; text-align: justify;">
                    Notable landmarks include the majestic <strong>Basilica of St. Martin de Tours</strong> — the largest Catholic church in Asia — 
                    and the historic <strong>Marcela Agoncillo Museum</strong>, home of the woman who sewed the first Philippine flag. 
                    To this day, Taal continues to preserve its charm and identity as the “Heritage Town of Batangas.”
                </p>
                </div>
            </div>
            </div>



            <!-- Features Section -->
            <section class="features mb-4">
                <br>
                <h2 class="text-center mb-3">Why Choose Ala Eh?</h2>
                <div class="poi-grid">
                    <!-- <div class="card">
                        <h3>🗺️ Interactive Map</h3>
                        <p>Real-time GPS navigation with accurate directions to all tourist destinations in Taal.</p>
                    </div> -->
                    <div class="card">
                        <h2>🤖 ChatBot Tour Guide</h2>
                        <p>Chat with our intelligent assistant for personalized recommendations and local insights.</p>
                    </div>
                    <div class="card">
                        <h2>📍 Points of Interest</h2>
                        <p>Discover attractions, restaurants, accommodations, and cultural sites with detailed information.</p>
                    </div>
                    <div class="card">
                        <h2>⭐ Reviews & Ratings</h2>
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

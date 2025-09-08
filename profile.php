<?php
require_once 'config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get user information
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Set session for role if not already set
if (!isset($_SESSION['role']) && isset($user['role'])) {
    $_SESSION['role'] = $user['role'];
}


// Get user's favorites
$query = "SELECT p.* FROM points_of_interest p JOIN user_favorites f ON p.id = f.poi_id WHERE f.user_id = ? ORDER BY f.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user's reviews
$query = "SELECT r.*, p.name as poi_name FROM reviews r JOIN points_of_interest p ON r.poi_id = p.id WHERE r.user_id = ? ORDER BY r.created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute([$_SESSION['user_id']]);
$user_reviews = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle profile update
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $full_name = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    
    if (empty($full_name) || empty($email)) {
        $error = 'Please fill in all required fields.';
    } else {
        // Check if email is already used by another user
        $query = "SELECT id FROM users WHERE email = ? AND id != ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$email, $_SESSION['user_id']]);
        
        if ($stmt->rowCount() > 0) {
            $error = 'Email is already used by another account.';
        } else {
            $query = "UPDATE users SET full_name = ?, email = ?, phone = ? WHERE id = ?";
            $stmt = $db->prepare($query);
            
            if ($stmt->execute([$full_name, $email, $phone, $_SESSION['user_id']])) {
                $success = 'Profile updated successfully!';
                $user['full_name'] = $full_name;
                $user['email'] = $email;
                $user['phone'] = $phone;
                $_SESSION['full_name'] = $full_name;
            } else {
                $error = 'Failed to update profile. Please try again.';
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">My Profile</h1>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                <!-- Profile Information -->
                <div class="card">
                    <h3>Profile Information</h3>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-error"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" class="form-control" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label for="full_name">Full Name *</label>
                            <input type="text" id="full_name" name="full_name" class="form-control" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email *</label>
                            <input type="email" id="email" name="email" class="form-control" required value="<?php echo htmlspecialchars($user['email']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" value="<?php echo htmlspecialchars($user['phone']); ?>">
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                    </form>
                </div>
              
                <!-- Account Statistics -->
                <div class="card">
                    <h3>Account Statistics</h3>
                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <h4 style="margin: 0; color: #3498db;">❤️ <?php echo count($favorites); ?></h4>
                            <p style="margin: 0;">Favorite Places</p>
                        </div>
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <h4 style="margin: 0; color: #27ae60;">⭐ <?php echo count($user_reviews); ?></h4>
                            <p style="margin: 0;">Reviews Written</p>
                        </div>
                        <div style="padding: 1rem; background: #f8f9fa; border-radius: 5px;">
                            <h4 style="margin: 0; color: #f39c12;">📅 <?php echo date('M j, Y', strtotime($user['created_at'])); ?></h4>
                            <p style="margin: 0;">Member Since</p>
                        </div>
                    </div>
                    <div>
                        
                        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                            <h3>Settings</h3>
                            <div class="card" style="display: flex; align-items: center; justify-content: center;">
                                <a href="admin/index.php" class="btn btn-primary    " style="margin-top: 2rem;">⚙️ Administrator</a>
                            </div>
                        <?php endif; ?>
                   
                    </div>
                </div>
            </div>
            
            
            <!-- Favorite Places -->
            <div class="card mt-4">
                <h3>My Favorite Places (<?php echo count($favorites); ?>)</h3>
                <?php if (empty($favorites)): ?>
                    <p>You haven't added any favorites yet. <a href="places.php">Explore places</a> and add some to your favorites!</p>
                <?php else: ?>
                    <div class="poi-grid">
                        <?php foreach ($favorites as $poi): ?>
                            <div class="poi-card">
                                <?php if ($poi['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" alt="<?php echo htmlspecialchars($poi['name']); ?>" class="poi-image">
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
                                    <div class="mt-2">
                                        <a href="poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-primary">View Details</a>
                                        <a href="map.php?poi=<?php echo $poi['id']; ?>" class="btn btn-success">Show on Map</a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- My Reviews -->
            <div class="card mt-4">
                <h3>My Reviews (<?php echo count($user_reviews); ?>)</h3>
                <?php if (empty($user_reviews)): ?>
                    <p>You haven't written any reviews yet. <a href="places.php">Visit some places</a> and share your experiences!</p>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                        <?php foreach ($user_reviews as $review): ?>
                            <div style="border: 1px solid #eee; padding: 1.5rem; border-radius: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <div>
                                        <h4 style="margin: 0;"><a href="poi-details.php?id=<?php echo $review['poi_id']; ?>"><?php echo htmlspecialchars($review['poi_name']); ?></a></h4>
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
</body>
</html>

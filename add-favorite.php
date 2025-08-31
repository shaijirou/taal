<?php
require_once 'config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['poi_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

$poi_id = (int)$_POST['poi_id'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

$poi_query = "SELECT name, category FROM points_of_interest WHERE id = ?";
$poi_stmt = $db->prepare($poi_query);
$poi_stmt->execute([$poi_id]);
$poi = $poi_stmt->fetch(PDO::FETCH_ASSOC);

// Check if already favorited
$query = "SELECT id FROM user_favorites WHERE user_id = ? AND poi_id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id, $poi_id]);

if ($stmt->rowCount() > 0) {
    // Remove from favorites
    $query = "DELETE FROM user_favorites WHERE user_id = ? AND poi_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $poi_id]);
    
    if ($poi) {
        logUserActivity($user_id, 'remove_favorite', $poi_id, [
            'poi_name' => $poi['name'],
            'poi_category' => $poi['category']
        ]);
    }
    
    echo json_encode(['success' => true, 'added' => false]);
} else {
    // Add to favorites
    $query = "INSERT INTO user_favorites (user_id, poi_id) VALUES (?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([$user_id, $poi_id]);
    
    if ($poi) {
        logUserActivity($user_id, 'add_favorite', $poi_id, [
            'poi_name' => $poi['name'],
            'poi_category' => $poi['category']
        ]);
    }
    
    echo json_encode(['success' => true, 'added' => true]);
}
?>

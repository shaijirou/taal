<?php
session_start();

// Database configuration
require_once 'database.php';

// Site configuration
define('SITE_NAME', 'Ala Eh! - Taal Tourist Guide');
define('SITE_URL', 'http://localhost/taal-tourist');
define('UPLOAD_PATH', 'uploads/');

// AI API Configuration
// To enable advanced AI responses, get a free API key from: https://makersuite.google.com/app/apikey
// Then uncomment and set your API key below:
define('GEMINI_API_KEY', '');


// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function getUserRole() {
    if (!isLoggedIn()) {
        return null;
    }
    
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT role FROM users WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $user ? $user['role'] : null;
    } catch (Exception $e) {
        error_log("Get user role error: " . $e->getMessage());
        return null;
    }
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

function formatDistance($distance) {
    if ($distance < 1000) {
        return round($distance) . ' m';
    } else {
        return round($distance / 1000, 1) . ' km';
    }
}

// Calculate distance between two coordinates
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371000; // Earth's radius in meters
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c;
}

function logUserActivity($user_id, $activity_type, $poi_id = null, $activity_data = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Convert activity data to JSON if it's an array
        $json_data = null;
        if ($activity_data !== null) {
            $json_data = is_array($activity_data) ? json_encode($activity_data) : $activity_data;
        }
        
        $query = "INSERT INTO user_activities (user_id, activity_type, poi_id, activity_data, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id, $activity_type, $poi_id, $json_data, $ip_address, $user_agent]);
        
        // Update user interests if POI-related activity
        if ($poi_id && in_array($activity_type, ['view_poi', 'add_favorite', 'add_review'])) {
            updateUserInterests($user_id, $poi_id, $activity_type);
        }
        
        return true;
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
        return false;
    }
}

function updateUserInterests($user_id, $poi_id, $activity_type) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Get POI category
        $poi_query = "SELECT category FROM points_of_interest WHERE id = ?";
        $poi_stmt = $db->prepare($poi_query);
        $poi_stmt->execute([$poi_id]);
        $poi = $poi_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($poi) {
            // Calculate interest score increment based on activity type
            $score_increment = 0;
            switch ($activity_type) {
                case 'view_poi':
                    $score_increment = 0.1;
                    break;
                case 'add_favorite':
                    $score_increment = 0.5;
                    break;
                case 'add_review':
                    $score_increment = 0.3;
                    break;
            }
            
            // Call stored procedure to update interests
            $interest_query = "CALL UpdateUserInterest(?, ?, ?)";
            $interest_stmt = $db->prepare($interest_query);
            $interest_stmt->execute([$user_id, $poi['category'], $score_increment]);
        }
    } catch (Exception $e) {
        error_log("Interest update error: " . $e->getMessage());
    }
}

function getUserActivityHistory($user_id, $limit = 50, $activity_type = null) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $where_clause = "WHERE ua.user_id = ?";
        $params = [$user_id];
        
        if ($activity_type) {
            $where_clause .= " AND ua.activity_type = ?";
            $params[] = $activity_type;
        }
        
        $query = "SELECT ua.*, poi.name as poi_name, poi.category as poi_category 
                  FROM user_activities ua 
                  LEFT JOIN points_of_interest poi ON ua.poi_id = poi.id 
                  $where_clause 
                  ORDER BY ua.created_at DESC 
                  LIMIT ?";
        $params[] = $limit;
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Activity history error: " . $e->getMessage());
        return [];
    }
}

function getAllUsersActivitySummary($days = 30) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT 
                    u.id, u.username, u.full_name, u.email,
                    COUNT(ua.id) as total_activities,
                    COUNT(CASE WHEN ua.activity_type = 'login' THEN 1 END) as login_count,
                    COUNT(CASE WHEN ua.activity_type = 'view_poi' THEN 1 END) as poi_views,
                    COUNT(CASE WHEN ua.activity_type = 'add_favorite' THEN 1 END) as favorites_added,
                    COUNT(CASE WHEN ua.activity_type = 'add_review' THEN 1 END) as reviews_added,
                    MAX(ua.created_at) as last_activity
                  FROM users u
                  LEFT JOIN user_activities ua ON u.id = ua.user_id 
                    AND ua.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  WHERE u.role = 'user'
                  GROUP BY u.id, u.username, u.full_name, u.email
                  ORDER BY total_activities DESC, last_activity DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Activity summary error: " . $e->getMessage());
        return [];
    }
}

function getActivityStats($days = 30) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT 
                    activity_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users,
                    DATE(created_at) as activity_date
                  FROM user_activities 
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                  GROUP BY activity_type, DATE(created_at)
                  ORDER BY activity_date DESC, count DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$days]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("Activity stats error: " . $e->getMessage());
        return [];
    }
}

function updateUserLastLogin($user_id) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        // Log login activity
        logUserActivity($user_id, 'login', null, ['login_time' => date('Y-m-d H:i:s')]);
        
        return true;
    } catch (Exception $e) {
        error_log("Last login update error: " . $e->getMessage());
        return false;
    }
}
?>

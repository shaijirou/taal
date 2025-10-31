<?php
require_once '../config/config.php';

// Simple admin authentication
if (!isLoggedIn() || $_SESSION['username'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get parameters
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : null;
$activity_type = $_GET['activity_type'] ?? '';
$days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$offset = ($page - 1) * $per_page;

// Get user details if specific user selected
$selected_user = null;
if ($user_id) {
    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $db->prepare($user_query);
    $user_stmt->execute([$user_id]);
    $selected_user = $user_stmt->fetch(PDO::FETCH_ASSOC);
}

// Build activity query
$where_conditions = [];
$params = [];

if ($user_id) {
    $where_conditions[] = "ua.user_id = ?";
    $params[] = $user_id;
}

if ($activity_type) {
    $where_conditions[] = "ua.activity_type = ?";
    $params[] = $activity_type;
}

$where_conditions[] = "ua.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
$params[] = $days;

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get activities with pagination
$activity_query = "SELECT ua.*, u.username, u.full_name, poi.name as poi_name, poi.category as poi_category
                   FROM user_activities ua
                   LEFT JOIN users u ON ua.user_id = u.id
                   LEFT JOIN points_of_interest poi ON ua.poi_id = poi.id
                   $where_clause
                   ORDER BY ua.created_at DESC
                   LIMIT $per_page OFFSET $offset";

$stmt = $db->prepare($activity_query);
$stmt->execute($params);
$activities = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_params = array_slice($params, 0, -2); // Remove limit and offset
$count_query = "SELECT COUNT(*) as total FROM user_activities ua
                LEFT JOIN users u ON ua.user_id = u.id
                LEFT JOIN points_of_interest poi ON ua.poi_id = poi.id
                $where_clause";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_activities = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_activities / $per_page);
// Get all users for dropdown
$users_query = "SELECT id, username, full_name FROM users WHERE role = 'user' ORDER BY full_name";
$users_stmt = $db->prepare($users_query);
$users_stmt->execute();
$all_users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

$summary_query = "SELECT 
                    activity_type,
                    COUNT(*) as count,
                    COUNT(DISTINCT user_id) as unique_users
                  FROM user_activities ua
                  $where_clause
                  GROUP BY activity_type
                  ORDER BY count DESC";
$summary_stmt = $db->prepare($summary_query);
$summary_stmt->execute($params);
$activity_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
$activity_summary = $summary_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity History - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
     <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&display=swap" rel="stylesheet">
     <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
       
    </style>
</head>
<body>
     <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="../index.php" style="color: #7b3e19; text-decoration: none;">Ala Eh! Admin </a>
                </div>
                <nav>
                    <ul >
                        <li style="font-size: 16px;"><a href="index.php">Dashboard</a></li>
                        <li><a href="analytics.php">Analytics</a></li>
                        <li><a href="user-history.php">User History</a></li>
                        <li><a href="location-management.php">Location Management</a></li>
                        <li><a href="../index.php">View Site</a></li>
                        <li><a href="../logout.php">Logout</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </header>
    
    <main>
        <div class="container">
            <h1 class="text-center mb-3">User Activity History</h1>
            
            <?php if ($selected_user): ?>
                <div class="card mb-4">
                    <h3>Viewing Activity for: <?php echo htmlspecialchars($selected_user['full_name']); ?></h3>
                    <p><strong>Username:</strong> @<?php echo htmlspecialchars($selected_user['username']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($selected_user['email']); ?></p>
                    <p><strong>Member Since:</strong> <?php echo date('M j, Y', strtotime($selected_user['created_at'])); ?></p>
                    <a href="user-history.php" class="btn btn-secondary">View All Users</a>
                </div>
            <?php endif; ?>
            
            <!-- Activity Summary -->
            <div class="card mb-4">
                <h2>Activity Summary (Last <?php echo $days; ?> days)</h2>
                <div class="summary-grid">
                    <?php foreach ($activity_summary as $summary): ?>
                        <div class="summary-card">
                            <h4><?php echo $summary['count']; ?></h4>
                            <p><?php echo ucfirst(str_replace('_', ' ', $summary['activity_type'])); ?></p>
                            <small><?php echo $summary['unique_users']; ?> users</small>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <h2>Filter Activities</h2>
                <form method="GET" class="activity-filters">
                    <div>
                        <label>User:</label>
                        <select name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($all_users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $user_id == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['full_name']); ?> (@<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label>Activity Type:</label>
                        <select name="activity_type">
                            <option value="">All Activities</option>
                            <option value="login" <?php echo $activity_type === 'login' ? 'selected' : ''; ?>>Login</option>
                            <option value="logout" <?php echo $activity_type === 'logout' ? 'selected' : ''; ?>>Logout</option>
                            <option value="view_poi" <?php echo $activity_type === 'view_poi' ? 'selected' : ''; ?>>View POI</option>
                            <option value="add_favorite" <?php echo $activity_type === 'add_favorite' ? 'selected' : ''; ?>>Add Favorite</option>
                            <option value="remove_favorite" <?php echo $activity_type === 'remove_favorite' ? 'selected' : ''; ?>>Remove Favorite</option>
                            <option value="add_review" <?php echo $activity_type === 'add_review' ? 'selected' : ''; ?>>Add Review</option>
                            <option value="search" <?php echo $activity_type === 'search' ? 'selected' : ''; ?>>Search</option>
                            <option value="ai_chat" <?php echo $activity_type === 'ai_chat' ? 'selected' : ''; ?>>AI Chat</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Time Period:</label>
                        <select name="days">
                            <option value="7" <?php echo $days === 7 ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30" <?php echo $days === 30 ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo $days === 90 ? 'selected' : ''; ?>>Last 90 days</option>
                            <option value="365" <?php echo $days === 365 ? 'selected' : ''; ?>>Last year</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="user-history.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Activities List -->
            <div class="card">
                <h2>Activities (<?php echo number_format($total_activities); ?> total)</h2>
                
                <?php if (empty($activities)): ?>
                    <p>No activities found for the selected criteria.</p>
                <?php else: ?>
                    <?php foreach ($activities as $activity): ?>
                        <div class="activity-item">
                            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                                <div>
                                    <span class="activity-type <?php echo $activity['activity_type']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $activity['activity_type'])); ?>
                                    </span>
                                    <strong><?php echo htmlspecialchars($activity['full_name']); ?></strong>
                                    <small style="color: #666;">(@<?php echo htmlspecialchars($activity['username']); ?>)</small>
                                </div>
                                <small style="color: #666;">
                                    <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                </small>
                            </div>
                            
                            <div style="margin-left: 1rem;">
                                <?php if ($activity['poi_name']): ?>
                                    <p><strong>POI:</strong> <?php echo htmlspecialchars($activity['poi_name']); ?> 
                                    <span style="color: #666;">(<?php echo ucfirst($activity['poi_category']); ?>)</span></p>
                                <?php endif; ?>
                                
                                <?php if ($activity['activity_data']): ?>
                                    <?php $data = json_decode($activity['activity_data'], true); ?>
                                    <?php if ($data): ?>
                                        <div style="background: #f8f9fa; padding: 0.5rem; border-radius: 4px; font-size: 0.9rem;">
                                            <?php foreach ($data as $key => $value): ?>
                                                <span style="margin-right: 1rem;">
                                                    <strong><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</strong> 
                                                    <?php echo is_bool($value) ? ($value ? 'Yes' : 'No') : htmlspecialchars($value); ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <small style="color: #999;">
                                    IP: <?php echo htmlspecialchars($activity['ip_address']); ?>
                                </small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                <?php if ($i == $page): ?>
                                    <span class="current"><?php echo $i; ?></span>
                                <?php else: ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
</body>
</html>

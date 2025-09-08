<?php
require_once '../config/config.php';

// Simple admin authentication
if (!isLoggedIn() || $_SESSION['username'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Get date range parameters
$date_range = $_GET['range'] ?? '30';
$start_date = date('Y-m-d', strtotime("-{$date_range} days"));
$end_date = date('Y-m-d');

// 1. User Engagement Analytics
$engagement_query = "SELECT 
    DATE(created_at) as date,
    COUNT(DISTINCT user_id) as active_users,
    COUNT(*) as total_activities,
    COUNT(CASE WHEN activity_type = 'login' THEN 1 END) as logins,
    COUNT(CASE WHEN activity_type = 'view_poi' THEN 1 END) as poi_views,
    COUNT(CASE WHEN activity_type = 'add_favorite' THEN 1 END) as favorites,
    COUNT(CASE WHEN activity_type = 'add_review' THEN 1 END) as reviews
    FROM user_activities 
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY DATE(created_at)
    ORDER BY date DESC";
$engagement_stmt = $db->prepare($engagement_query);
$engagement_stmt->execute([$start_date, $end_date]);
$engagement_data = $engagement_stmt->fetchAll(PDO::FETCH_ASSOC);

// 2. Popular Locations Analytics
$popular_locations_query = "SELECT 
    poi.id, poi.name, poi.category,
    COUNT(ua.id) as total_interactions,
    COUNT(CASE WHEN ua.activity_type = 'view_poi' THEN 1 END) as views,
    COUNT(CASE WHEN ua.activity_type = 'add_favorite' THEN 1 END) as favorites,
    COUNT(CASE WHEN ua.activity_type = 'add_review' THEN 1 END) as reviews,
    poi.rating, poi.view_count
    FROM points_of_interest poi
    LEFT JOIN user_activities ua ON poi.id = ua.poi_id AND DATE(ua.created_at) BETWEEN ? AND ?
    GROUP BY poi.id, poi.name, poi.category, poi.rating, poi.view_count
    ORDER BY total_interactions DESC
    LIMIT 10";
$popular_stmt = $db->prepare($popular_locations_query);
$popular_stmt->execute([$start_date, $end_date]);
$popular_locations = $popular_stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. User Interest Analytics
$interest_query = "SELECT 
    ui.category,
    COUNT(ui.user_id) as user_count,
    AVG(ui.interest_score) as avg_score,
    SUM(ui.interaction_count) as total_interactions
    FROM user_interests ui
    JOIN users u ON ui.user_id = u.id
    WHERE u.role = 'user'
    GROUP BY ui.category
    ORDER BY avg_score DESC";
$interest_stmt = $db->prepare($interest_query);
$interest_stmt->execute();
$interest_data = $interest_stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Geographic Analytics
$geographic_query = "SELECT 
    poi.category,
    COUNT(ua.id) as activity_count,
    COUNT(DISTINCT ua.user_id) as unique_users,
    AVG(poi.latitude) as avg_lat,
    AVG(poi.longitude) as avg_lng
    FROM points_of_interest poi
    LEFT JOIN user_activities ua ON poi.id = ua.poi_id AND DATE(ua.created_at) BETWEEN ? AND ?
    GROUP BY poi.category
    ORDER BY activity_count DESC";
$geographic_stmt = $db->prepare($geographic_query);
$geographic_stmt->execute([$start_date, $end_date]);
$geographic_data = $geographic_stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Search Analytics
$search_query = "SELECT 
    search_term,
    COUNT(*) as search_count,
    COUNT(DISTINCT user_id) as unique_searchers,
    AVG(results_count) as avg_results,
    COUNT(clicked_poi_id) as clicks
    FROM search_analytics
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY search_term
    ORDER BY search_count DESC
    LIMIT 20";
$search_stmt = $db->prepare($search_query);
$search_stmt->execute([$start_date, $end_date]);
$search_data = $search_stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. User Behavior Patterns
$behavior_query = "SELECT 
    HOUR(created_at) as hour,
    COUNT(*) as activity_count,
    COUNT(DISTINCT user_id) as unique_users
    FROM user_activities
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY HOUR(created_at)
    ORDER BY hour";
$behavior_stmt = $db->prepare($behavior_query);
$behavior_stmt->execute([$start_date, $end_date]);
$behavior_data = $behavior_stmt->fetchAll(PDO::FETCH_ASSOC);

// 7. Top Users Analytics
$top_users_query = "SELECT 
    u.id, u.username, u.full_name,
    COUNT(ua.id) as total_activities,
    COUNT(CASE WHEN ua.activity_type = 'view_poi' THEN 1 END) as poi_views,
    COUNT(CASE WHEN ua.activity_type = 'add_favorite' THEN 1 END) as favorites,
    COUNT(CASE WHEN ua.activity_type = 'add_review' THEN 1 END) as reviews,
    MAX(ua.created_at) as last_activity
    FROM users u
    LEFT JOIN user_activities ua ON u.id = ua.user_id AND DATE(ua.created_at) BETWEEN ? AND ?
    WHERE u.role = 'user'
    GROUP BY u.id, u.username, u.full_name
    ORDER BY total_activities DESC
    LIMIT 10";
$top_users_stmt = $db->prepare($top_users_query);
$top_users_stmt->execute([$start_date, $end_date]);
$top_users = $top_users_stmt->fetchAll(PDO::FETCH_ASSOC);

// 8. Overall Statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'user' AND DATE(created_at) BETWEEN ? AND ?) as new_users,
    (SELECT COUNT(DISTINCT user_id) FROM user_activities WHERE DATE(created_at) BETWEEN ? AND ?) as active_users,
    (SELECT COUNT(*) FROM points_of_interest WHERE status = 'active') as active_pois,
    (SELECT COUNT(*) FROM reviews WHERE DATE(created_at) BETWEEN ? AND ?) as new_reviews,
    (SELECT COUNT(*) FROM user_favorites WHERE DATE(created_at) BETWEEN ? AND ?) as new_favorites,
    (SELECT COUNT(*) FROM user_activities WHERE DATE(created_at) BETWEEN ? AND ?) as total_activities";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([$start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date, $start_date, $end_date]);
$overall_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Prepare data for charts
$engagement_chart_data = json_encode($engagement_data);
$interest_chart_data = json_encode($interest_data);
$behavior_chart_data = json_encode($behavior_data);
$geographic_chart_data = json_encode($geographic_data);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
       
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <a href="../index.php" style="color: white; text-decoration: none;">Ala Eh! Admin 🔧</a>
                </div>
                <nav>
                    <ul>
                        <li><a href="index.php">Dashboard</a></li>
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
            <div class="analytics-header">
                <h1>Analytics Dashboard</h1>
                <div class="date-filter">
                    <form method="GET">
                        <select name="range" onchange="this.form.submit()">
                            <option value="7" <?php echo $date_range === '7' ? 'selected' : ''; ?>>Last 7 days</option>
                            <option value="30" <?php echo $date_range === '30' ? 'selected' : ''; ?>>Last 30 days</option>
                            <option value="90" <?php echo $date_range === '90' ? 'selected' : ''; ?>>Last 90 days</option>
                            <option value="365" <?php echo $date_range === '365' ? 'selected' : ''; ?>>Last year</option>
                        </select>
                    </form>
                </div>
            </div>
            
            <!-- Overview Statistics -->
            <div class="stats-overview">
                <div class="stat-card users">
                    <h3><?php echo number_format($overall_stats['active_users']); ?></h3>
                    <p>Active Users</p>
                    <small><?php echo $overall_stats['new_users']; ?> new in period</small>
                </div>
                <div class="stat-card engagement">
                    <h3><?php echo number_format($overall_stats['total_activities']); ?></h3>
                    <p>Total Activities</p>
                    <small>User interactions</small>
                </div>
                <div class="stat-card content">
                    <h3><?php echo number_format($overall_stats['new_reviews']); ?></h3>
                    <p>New Reviews</p>
                    <small><?php echo $overall_stats['new_favorites']; ?> new favorites</small>
                </div>
                <div class="stat-card activity">
                    <h3><?php echo number_format($overall_stats['active_pois']); ?></h3>
                    <p>Active Locations</p>
                    <small>Available to users</small>
                </div>
            </div>
            
            <!-- Charts Grid -->
            <div class="analytics-grid">
                <!-- User Engagement Chart -->
                <div class="analytics-card">
                    <h3>Daily User Engagement</h3>
                    <div class="chart-container">
                        <canvas id="engagementChart"></canvas>
                    </div>
                </div>
                
                <!-- User Interests Chart -->
                <div class="analytics-card">
                    <h3>User Interest Categories</h3>
                    <div class="chart-container">
                        <canvas id="interestChart"></canvas>
                    </div>
                </div>
                
                <!-- Hourly Activity Pattern -->
                <div class="analytics-card">
                    <h3>Hourly Activity Pattern</h3>
                    <div class="chart-container">
                        <canvas id="behaviorChart"></canvas>
                    </div>
                </div>
                
                <!-- Geographic Distribution -->
                <div class="analytics-card">
                    <h3>Category Activity Distribution</h3>
                    <div class="chart-container">
                        <canvas id="geographicChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Data Tables -->
            <div class="analytics-grid">
                <!-- Popular Locations -->
                <div class="analytics-card">
                    <h3>Most Popular Locations</h3>
                    <div class="table-container">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th>Category</th>
                                    <th>Views</th>
                                    <th>Favorites</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($popular_locations as $location): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($location['name']); ?></strong>
                                        </td>
                                        <td><?php echo ucfirst($location['category']); ?></td>
                                        <td><?php echo number_format($location['views']); ?></td>
                                        <td><?php echo number_format($location['favorites']); ?></td>
                                        <td><?php echo $location['rating']; ?>/5</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Top Users -->
                <div class="analytics-card">
                    <h3>Most Active Users</h3>
                    <div class="table-container">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Activities</th>
                                    <th>Views</th>
                                    <th>Reviews</th>
                                    <th>Last Active</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                            <small>@<?php echo htmlspecialchars($user['username']); ?></small>
                                        </td>
                                        <td><?php echo number_format($user['total_activities']); ?></td>
                                        <td><?php echo number_format($user['poi_views']); ?></td>
                                        <td><?php echo number_format($user['reviews']); ?></td>
                                        <td>
                                            <?php if ($user['last_activity']): ?>
                                                <?php echo date('M j, Y', strtotime($user['last_activity'])); ?>
                                            <?php else: ?>
                                                <span style="color: #999;">No activity</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Search Analytics -->
            <?php if (!empty($search_data)): ?>
                <div class="analytics-card">
                    <h3>Popular Search Terms</h3>
                    <div class="table-container">
                        <table class="analytics-table">
                            <thead>
                                <tr>
                                    <th>Search Term</th>
                                    <th>Searches</th>
                                    <th>Unique Users</th>
                                    <th>Avg Results</th>
                                    <th>Click Rate</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_data as $search): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($search['search_term']); ?></strong></td>
                                        <td><?php echo number_format($search['search_count']); ?></td>
                                        <td><?php echo number_format($search['unique_searchers']); ?></td>
                                        <td><?php echo number_format($search['avg_results'], 1); ?></td>
                                        <td>
                                            <?php 
                                            $click_rate = $search['search_count'] > 0 ? ($search['clicks'] / $search['search_count']) * 100 : 0;
                                            echo number_format($click_rate, 1) . '%';
                                            ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        // Chart.js configuration
        Chart.defaults.font.family = 'system-ui, -apple-system, sans-serif';
        Chart.defaults.color = '#666';
        
        // User Engagement Chart
        const engagementData = <?php echo $engagement_chart_data; ?>;
        const engagementCtx = document.getElementById('engagementChart').getContext('2d');
        new Chart(engagementCtx, {
            type: 'line',
            data: {
                labels: engagementData.map(d => new Date(d.date).toLocaleDateString()),
                datasets: [
                    {
                        label: 'Active Users',
                        data: engagementData.map(d => d.active_users),
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4
                    },
                    {
                        label: 'POI Views',
                        data: engagementData.map(d => d.poi_views),
                        borderColor: '#f093fb',
                        backgroundColor: 'rgba(240, 147, 251, 0.1)',
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // User Interests Chart
        const interestData = <?php echo $interest_chart_data; ?>;
        const interestCtx = document.getElementById('interestChart').getContext('2d');
        new Chart(interestCtx, {
            type: 'doughnut',
            data: {
                labels: interestData.map(d => d.category.charAt(0).toUpperCase() + d.category.slice(1)),
                datasets: [{
                    data: interestData.map(d => d.user_count),
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#43e97b',
                        '#ffa726'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Hourly Behavior Chart
        const behaviorData = <?php echo $behavior_chart_data; ?>;
        const behaviorCtx = document.getElementById('behaviorChart').getContext('2d');
        new Chart(behaviorCtx, {
            type: 'bar',
            data: {
                labels: behaviorData.map(d => d.hour + ':00'),
                datasets: [{
                    label: 'Activities',
                    data: behaviorData.map(d => d.activity_count),
                    backgroundColor: 'rgba(67, 233, 123, 0.8)',
                    borderColor: '#43e97b',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Geographic Distribution Chart
        const geographicData = <?php echo $geographic_chart_data; ?>;
        const geographicCtx = document.getElementById('geographicChart').getContext('2d');
        new Chart(geographicCtx, {
            type: 'polarArea',
            data: {
                labels: geographicData.map(d => d.category.charAt(0).toUpperCase() + d.category.slice(1)),
                datasets: [{
                    data: geographicData.map(d => d.activity_count),
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(67, 233, 123, 0.8)',
                        'rgba(255, 167, 38, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>

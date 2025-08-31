<?php
require_once '../config/config.php';

// Simple admin authentication
if (!isLoggedIn() || $_SESSION['username'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle POI operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_poi'])) {
        $poi_id = (int)$_POST['poi_id'];
        $name = sanitizeInput($_POST['name']);
        $description = sanitizeInput($_POST['description']);
        $category = sanitizeInput($_POST['category']);
        $latitude = (float)$_POST['latitude'];
        $longitude = (float)$_POST['longitude'];
        $address = sanitizeInput($_POST['address']);
        $contact_info = sanitizeInput($_POST['contact_info']);
        $opening_hours = sanitizeInput($_POST['opening_hours']);
        $price_range = sanitizeInput($_POST['price_range']);
        $image_url = sanitizeInput($_POST['image_url']);
        $status = sanitizeInput($_POST['status']);
        $featured = isset($_POST['featured']) ? 1 : 0;
        $admin_notes = sanitizeInput($_POST['admin_notes']);
        
        // Get old data for logging
        $old_query = "SELECT * FROM points_of_interest WHERE id = ?";
        $old_stmt = $db->prepare($old_query);
        $old_stmt->execute([$poi_id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
        
        $query = "UPDATE points_of_interest SET name = ?, description = ?, category = ?, latitude = ?, longitude = ?, address = ?, contact_info = ?, opening_hours = ?, price_range = ?, image_url = ?, status = ?, featured = ?, admin_notes = ?, updated_by = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$name, $description, $category, $latitude, $longitude, $address, $contact_info, $opening_hours, $price_range, $image_url, $status, $featured, $admin_notes, $_SESSION['user_id'], $poi_id]);
        
        // Log admin action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_data, new_data, description, ip_address) VALUES (?, 'update_poi', 'poi', ?, ?, ?, 'Updated POI details', ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$admin_id, $poi_id, json_encode($old_data), json_encode(['name' => $name, 'status' => $status, 'featured' => $featured]), $_SERVER['REMOTE_ADDR']]);
        
        header('Location: location-management.php?success=poi_updated');
        exit();
    }
    
    if (isset($_POST['bulk_action']) && isset($_POST['selected_pois'])) {
        $action = $_POST['bulk_action'];
        $selected_pois = $_POST['selected_pois'];
        
        foreach ($selected_pois as $poi_id) {
            $poi_id = (int)$poi_id;
            
            switch ($action) {
                case 'activate':
                    $query = "UPDATE points_of_interest SET status = 'active' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$poi_id]);
                    break;
                    
                case 'deactivate':
                    $query = "UPDATE points_of_interest SET status = 'inactive' WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$poi_id]);
                    break;
                    
                case 'feature':
                    $query = "UPDATE points_of_interest SET featured = 1 WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$poi_id]);
                    break;
                    
                case 'unfeature':
                    $query = "UPDATE points_of_interest SET featured = 0 WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$poi_id]);
                    break;
                    
                case 'delete':
                    // Get POI data for logging
                    $poi_query = "SELECT * FROM points_of_interest WHERE id = ?";
                    $poi_stmt = $db->prepare($poi_query);
                    $poi_stmt->execute([$poi_id]);
                    $poi_data = $poi_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $query = "DELETE FROM points_of_interest WHERE id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$poi_id]);
                    
                    // Log admin action
                    $admin_id = $_SESSION['user_id'];
                    $log_query = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_data, description, ip_address) VALUES (?, 'delete_poi', 'poi', ?, ?, 'Bulk deleted POI', ?)";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([$admin_id, $poi_id, json_encode($poi_data), $_SERVER['REMOTE_ADDR']]);
                    break;
            }
        }
        
        header('Location: location-management.php?success=bulk_action_completed');
        exit();
    }
}

// Get filters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$featured_filter = $_GET['featured'] ?? '';
$search = $_GET['search'] ?? '';
$sort = $_GET['sort'] ?? 'created_at';
$order = $_GET['order'] ?? 'DESC';

// Build query conditions
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(name LIKE ? OR description LIKE ? OR address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($category_filter)) {
    $conditions[] = "category = ?";
    $params[] = $category_filter;
}

if (!empty($status_filter)) {
    $conditions[] = "status = ?";
    $params[] = $status_filter;
}

if (!empty($featured_filter)) {
    $conditions[] = "featured = ?";
    $params[] = $featured_filter === 'yes' ? 1 : 0;
}

$where_clause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get POIs with filtering and sorting
$valid_sorts = ['name', 'category', 'status', 'rating', 'view_count', 'created_at', 'updated_at'];
$sort = in_array($sort, $valid_sorts) ? $sort : 'created_at';
$order = strtoupper($order) === 'ASC' ? 'ASC' : 'DESC';

$query = "SELECT *, 
          (SELECT COUNT(*) FROM reviews WHERE poi_id = points_of_interest.id) as review_count,
          (SELECT COUNT(*) FROM user_favorites WHERE poi_id = points_of_interest.id) as favorite_count
          FROM points_of_interest 
          $where_clause 
          ORDER BY $sort $order";

$stmt = $db->prepare($query);
$stmt->execute($params);
$pois = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get POI for editing
$edit_poi = null;
if (isset($_GET['edit'])) {
    $edit_poi_id = (int)$_GET['edit'];
    $edit_query = "SELECT * FROM points_of_interest WHERE id = ?";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->execute([$edit_poi_id]);
    $edit_poi = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_pois,
    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_pois,
    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_pois,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_pois,
    COUNT(CASE WHEN featured = 1 THEN 1 END) as featured_pois,
    AVG(rating) as avg_rating,
    SUM(view_count) as total_views
    FROM points_of_interest";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);

// Get category breakdown
$category_query = "SELECT category, COUNT(*) as count FROM points_of_interest GROUP BY category ORDER BY count DESC";
$category_stmt = $db->prepare($category_query);
$category_stmt->execute();
$category_breakdown = $category_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Location Management - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .location-filters {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: end;
        }
        .location-filters > div {
            display: flex;
            flex-direction: column;
        }
        .location-filters input, .location-filters select {
            padding: 0.5rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-top: 0.25rem;
        }
        .poi-status {
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .poi-status.active { background: #d4edda; color: #155724; }
        .poi-status.inactive { background: #f8d7da; color: #721c24; }
        .poi-status.pending { background: #fff3cd; color: #856404; }
        .featured-badge {
            background: #ffd700;
            color: #333;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .bulk-actions {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: none;
        }
        .bulk-actions.show {
            display: block;
        }
        .poi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        .poi-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }
        .poi-card.inactive {
            opacity: 0.7;
            background: #f8f9fa;
        }
        .poi-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .poi-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }
        .edit-form {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .edit-form-content {
            background: white;
            padding: 2rem;
            border-radius: 8px;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            width: 90%;
        }
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
            <h1 class="text-center mb-3">Location Management System</h1>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php
                    switch($_GET['success']) {
                        case 'poi_updated': echo 'Location successfully updated!'; break;
                        case 'bulk_action_completed': echo 'Bulk action completed successfully!'; break;
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3 style="color: #3498db;"><?php echo $stats['total_pois']; ?></h3>
                    <p>Total Locations</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #27ae60;"><?php echo $stats['active_pois']; ?></h3>
                    <p>Active</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #f39c12;"><?php echo $stats['pending_pois']; ?></h3>
                    <p>Pending</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #e74c3c;"><?php echo $stats['inactive_pois']; ?></h3>
                    <p>Inactive</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #9b59b6;"><?php echo $stats['featured_pois']; ?></h3>
                    <p>Featured</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #34495e;"><?php echo number_format($stats['total_views']); ?></h3>
                    <p>Total Views</p>
                </div>
            </div>
            
            <!-- Category Breakdown -->
            <div class="card mb-4">
                <h3>Category Breakdown</h3>
                <div style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    <?php foreach ($category_breakdown as $cat): ?>
                        <div style="text-align: center;">
                            <h4><?php echo $cat['count']; ?></h4>
                            <p><?php echo ucfirst($cat['category']); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="card mb-4">
                <h3>Filter & Search Locations</h3>
                <form method="GET" class="location-filters">
                    <div>
                        <label>Search:</label>
                        <input type="text" name="search" placeholder="Search locations..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div>
                        <label>Category:</label>
                        <select name="category">
                            <option value="">All Categories</option>
                            <option value="attraction" <?php echo $category_filter === 'attraction' ? 'selected' : ''; ?>>Attraction</option>
                            <option value="restaurant" <?php echo $category_filter === 'restaurant' ? 'selected' : ''; ?>>Restaurant</option>
                            <option value="accommodation" <?php echo $category_filter === 'accommodation' ? 'selected' : ''; ?>>Accommodation</option>
                            <option value="cultural" <?php echo $category_filter === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                            <option value="historical" <?php echo $category_filter === 'historical' ? 'selected' : ''; ?>>Historical</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Status:</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Featured:</label>
                        <select name="featured">
                            <option value="">All</option>
                            <option value="yes" <?php echo $featured_filter === 'yes' ? 'selected' : ''; ?>>Featured</option>
                            <option value="no" <?php echo $featured_filter === 'no' ? 'selected' : ''; ?>>Not Featured</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Sort by:</label>
                        <select name="sort">
                            <option value="created_at" <?php echo $sort === 'created_at' ? 'selected' : ''; ?>>Date Created</option>
                            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="category" <?php echo $sort === 'category' ? 'selected' : ''; ?>>Category</option>
                            <option value="rating" <?php echo $sort === 'rating' ? 'selected' : ''; ?>>Rating</option>
                            <option value="view_count" <?php echo $sort === 'view_count' ? 'selected' : ''; ?>>Views</option>
                        </select>
                    </div>
                    
                    <div>
                        <label>Order:</label>
                        <select name="order">
                            <option value="DESC" <?php echo $order === 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $order === 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                    
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="location-management.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Bulk Actions -->
            <div class="bulk-actions" id="bulkActions">
                <form method="POST" onsubmit="return confirm('Are you sure you want to perform this bulk action?');">
                    <div style="display: flex; gap: 1rem; align-items: center;">
                        <span><strong>Bulk Actions:</strong></span>
                        <select name="bulk_action" required>
                            <option value="">Select Action</option>
                            <option value="activate">Activate</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="feature">Feature</option>
                            <option value="unfeature">Unfeature</option>
                            <option value="delete">Delete</option>
                        </select>
                        <button type="submit" class="btn btn-warning">Apply to Selected</button>
                        <button type="button" class="btn btn-secondary" onclick="clearSelection()">Clear Selection</button>
                    </div>
                    <div id="selectedPois"></div>
                </form>
            </div>
            
            <!-- Locations Grid -->
            <div class="card">
                <h3>Locations (<?php echo count($pois); ?> found)</h3>
                
                <?php if (empty($pois)): ?>
                    <p>No locations found matching your criteria.</p>
                <?php else: ?>
                    <div class="poi-grid">
                        <?php foreach ($pois as $poi): ?>
                            <div class="poi-card <?php echo $poi['status']; ?>">
                                <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 1rem;">
                                    <input type="checkbox" class="poi-checkbox" value="<?php echo $poi['id']; ?>" onchange="updateBulkActions()">
                                    <div style="display: flex; gap: 0.5rem;">
                                        <span class="poi-status <?php echo $poi['status']; ?>"><?php echo ucfirst($poi['status']); ?></span>
                                        <?php if ($poi['featured']): ?>
                                            <span class="featured-badge">Featured</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <?php if ($poi['image_url']): ?>
                                    <img src="<?php echo htmlspecialchars($poi['image_url']); ?>" alt="<?php echo htmlspecialchars($poi['name']); ?>" class="poi-image">
                                <?php endif; ?>
                                
                                <h4><?php echo htmlspecialchars($poi['name']); ?></h4>
                                <p style="color: #666; font-size: 0.9rem;"><?php echo ucfirst($poi['category']); ?></p>
                                <p style="font-size: 0.9rem;"><?php echo htmlspecialchars(substr($poi['description'], 0, 100)) . '...'; ?></p>
                                
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0; font-size: 0.9rem; color: #666;">
                                    <span>Rating: <?php echo $poi['rating']; ?>/5</span>
                                    <span>Views: <?php echo number_format($poi['view_count']); ?></span>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0; font-size: 0.9rem; color: #666;">
                                    <span>Reviews: <?php echo $poi['review_count']; ?></span>
                                    <span>Favorites: <?php echo $poi['favorite_count']; ?></span>
                                </div>
                                
                                <?php if ($poi['admin_notes']): ?>
                                    <div style="background: #fff3cd; padding: 0.5rem; border-radius: 4px; margin: 1rem 0; font-size: 0.9rem;">
                                        <strong>Admin Notes:</strong> <?php echo htmlspecialchars($poi['admin_notes']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="poi-actions">
                                    <a href="?edit=<?php echo $poi['id']; ?>" class="btn btn-primary">Edit</a>
                                    <a href="../poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-secondary" target="_blank">View</a>
                                </div>
                                
                                <div style="margin-top: 1rem; font-size: 0.8rem; color: #999;">
                                    Created: <?php echo date('M j, Y', strtotime($poi['created_at'])); ?>
                                    <?php if ($poi['updated_at'] !== $poi['created_at']): ?>
                                        <br>Updated: <?php echo date('M j, Y', strtotime($poi['updated_at'])); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- Edit Form Modal -->
    <?php if ($edit_poi): ?>
        <div class="edit-form">
            <div class="edit-form-content">
                <h3>Edit Location: <?php echo htmlspecialchars($edit_poi['name']); ?></h3>
                <form method="POST">
                    <input type="hidden" name="poi_id" value="<?php echo $edit_poi['id']; ?>">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="name">Name *</label>
                            <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($edit_poi['name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="category">Category *</label>
                            <select id="category" name="category" class="form-control" required>
                                <option value="attraction" <?php echo $edit_poi['category'] === 'attraction' ? 'selected' : ''; ?>>Attraction</option>
                                <option value="restaurant" <?php echo $edit_poi['category'] === 'restaurant' ? 'selected' : ''; ?>>Restaurant</option>
                                <option value="accommodation" <?php echo $edit_poi['category'] === 'accommodation' ? 'selected' : ''; ?>>Accommodation</option>
                                <option value="cultural" <?php echo $edit_poi['category'] === 'cultural' ? 'selected' : ''; ?>>Cultural</option>
                                <option value="historical" <?php echo $edit_poi['category'] === 'historical' ? 'selected' : ''; ?>>Historical</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="latitude">Latitude *</label>
                            <input type="number" id="latitude" name="latitude" class="form-control" step="0.000001" value="<?php echo $edit_poi['latitude']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="longitude">Longitude *</label>
                            <input type="number" id="longitude" name="longitude" class="form-control" step="0.000001" value="<?php echo $edit_poi['longitude']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" class="form-control" required>
                                <option value="active" <?php echo $edit_poi['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo $edit_poi['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="pending" <?php echo $edit_poi['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="featured" <?php echo $edit_poi['featured'] ? 'checked' : ''; ?>>
                                Featured Location
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description *</label>
                        <textarea id="description" name="description" class="form-control" rows="3" required><?php echo htmlspecialchars($edit_poi['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Address</label>
                        <input type="text" id="address" name="address" class="form-control" value="<?php echo htmlspecialchars($edit_poi['address']); ?>">
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label for="contact_info">Contact Info</label>
                            <input type="text" id="contact_info" name="contact_info" class="form-control" value="<?php echo htmlspecialchars($edit_poi['contact_info']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="opening_hours">Opening Hours</label>
                            <input type="text" id="opening_hours" name="opening_hours" class="form-control" value="<?php echo htmlspecialchars($edit_poi['opening_hours']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="price_range">Price Range</label>
                            <input type="text" id="price_range" name="price_range" class="form-control" value="<?php echo htmlspecialchars($edit_poi['price_range']); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="image_url">Image URL</label>
                        <input type="url" id="image_url" name="image_url" class="form-control" value="<?php echo htmlspecialchars($edit_poi['image_url']); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="admin_notes">Admin Notes</label>
                        <textarea id="admin_notes" name="admin_notes" class="form-control" rows="2" placeholder="Internal notes for admin use..."><?php echo htmlspecialchars($edit_poi['admin_notes']); ?></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" name="update_poi" class="btn btn-success">Update Location</button>
                        <a href="location-management.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
    
    <?php include '../includes/footer.php'; ?>
    
    <script>
        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.poi-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            const selectedPois = document.getElementById('selectedPois');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.add('show');
                
                // Add hidden inputs for selected POIs
                selectedPois.innerHTML = '';
                checkboxes.forEach(checkbox => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_pois[]';
                    input.value = checkbox.value;
                    selectedPois.appendChild(input);
                });
            } else {
                bulkActions.classList.remove('show');
            }
        }
        
        function clearSelection() {
            document.querySelectorAll('.poi-checkbox').forEach(checkbox => {
                checkbox.checked = false;
            });
            updateBulkActions();
        }
        
        // Close modal when clicking outside
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('edit-form')) {
                window.location.href = 'location-management.php';
            }
        });
    </script>
</body>
</html>

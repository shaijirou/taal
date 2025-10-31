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

        $image_url = sanitizeInput($_POST['image_url']); // Keep existing image by default
        
        // Handle file upload
        if (isset($_FILES['image_upload']) && $_FILES['image_upload']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/poi_images/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['image_upload']['name']);
            $file_extension = strtolower($file_info['extension']);
            
            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($file_extension, $allowed_extensions)) {
                // Generate unique filename
                $new_filename = 'poi_' . $poi_id . '_' . time() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                // Validate file size (max 5MB)
                if ($_FILES['image_upload']['size'] <= 5 * 1024 * 1024) {
                    if (move_uploaded_file($_FILES['image_upload']['tmp_name'], $upload_path)) {
                        // Delete old image if it exists and is not a URL
                        if ($image_url && !filter_var($image_url, FILTER_VALIDATE_URL) && file_exists('../' . $image_url)) {
                            unlink('../' . $image_url);
                        }
                        $image_url = 'uploads/poi_images/' . $new_filename;
                    }
                }
            }
        }
        
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
    
    // if (isset($_POST['bulk_action']) && isset($_POST['selected_pois'])) {
    //     $action = $_POST['bulk_action'];
    //     $selected_pois = $_POST['selected_pois'];
        
    //     foreach ($selected_pois as $poi_id) {
    //         $poi_id = (int)$poi_id;
            
    //         switch ($action) {
    //             case 'activate':
    //                 $query = "UPDATE points_of_interest SET status = 'active' WHERE id = ?";
    //                 $stmt = $db->prepare($query);
    //                 $stmt->execute([$poi_id]);
    //                 break;
                    
    //             case 'deactivate':
    //                 $query = "UPDATE points_of_interest SET status = 'inactive' WHERE id = ?";
    //                 $stmt = $db->prepare($query);
    //                 $stmt->execute([$poi_id]);
    //                 break;
                    
    //             case 'feature':
    //                 $query = "UPDATE points_of_interest SET featured = 1 WHERE id = ?";
    //                 $stmt = $db->prepare($query);
    //                 $stmt->execute([$poi_id]);
    //                 break;
                    
    //             case 'unfeature':
    //                 $query = "UPDATE points_of_interest SET featured = 0 WHERE id = ?";
    //                 $stmt = $db->prepare($query);
    //                 $stmt->execute([$poi_id]);
    //                 break;
                    
    //             case 'delete':
    //                 // Get POI data for logging
    //                 $poi_query = "SELECT * FROM points_of_interest WHERE id = ?";
    //                 $poi_stmt = $db->prepare($poi_query);
    //                 $poi_stmt->execute([$poi_id]);
    //                 $poi_data = $poi_stmt->fetch(PDO::FETCH_ASSOC);
                    
    //                 $query = "DELETE FROM points_of_interest WHERE id = ?";
    //                 $stmt = $db->prepare($query);
    //                 $stmt->execute([$poi_id]);
                    
    //                 // Log admin action
    //                 $admin_id = $_SESSION['user_id'];
    //                 $log_query = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_data, description, ip_address) VALUES (?, 'delete_poi', 'poi', ?, ?, 'Bulk deleted POI', ?)";
    //                 $log_stmt = $db->prepare($log_query);
    //                 $log_stmt->execute([$admin_id, $poi_id, json_encode($poi_data), $_SERVER['REMOTE_ADDR']]);
    //                 break;
    //         }
    //     }
        
    //     header('Location: location-management.php?success=bulk_action_completed');
    //     exit();
    // }
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
     <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        /* Added styles for image upload and preview */
        .image-upload-container {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .image-preview {
            max-width: 200px;
            max-height: 150px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid #ddd;
        }
        
        .image-upload-area {
            border: 2px dashed #ddd;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: border-color 0.3s;
        }
        
        .image-upload-area:hover {
            border-color: #3498db;
        }
        
        .image-upload-area.dragover {
            border-color: #3498db;
            background-color: #f8f9fa;
        }
        
        .upload-text {
            color: #666;
            margin-top: 0.5rem;
        }
        
        .current-image {
            margin-bottom: 1rem;
        }
        
        .remove-image-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            margin-top: 0.5rem;
        }

        
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
                    <p style="color: #666;">Total Locations</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #27ae60;"><?php echo $stats['active_pois']; ?></h3>
                    <p style="color: #666;">Active</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #f39c12;"><?php echo $stats['pending_pois']; ?></h3>
                    <p style="color: #666;">Pending</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #e74c3c;"><?php echo $stats['inactive_pois']; ?></h3>
                    <p style="color: #666;">Inactive</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #9b59b6;"><?php echo $stats['featured_pois']; ?></h3>
                    <p style="color: #666;">Featured</p>
                </div>
                <div class="stat-card">
                    <h3 style="color: #34495e;"><?php echo number_format($stats['total_views']); ?></h3>
                    <p style="color: #666;">Total Views</p>
                </div>
            </div>
            
            <!-- Category Breakdown -->
            <div class="card mb-4">
                <h4>Category Breakdown</h4>
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
                <h2>Locations (<?php echo count($pois); ?> found)</h2>
                
                <?php if (empty($pois)): ?>
                    <p>No locations found matching your criteria.</p>
                <?php else: ?>
                    <div class="poi-grid">
                        <?php foreach ($pois as $poi): ?>
                            <div class="poi-card <?php echo $poi['status']; ?>">
                               
                                  <?php if ($poi['image_url']): ?>
                                        <img src="../<?php echo htmlspecialchars($poi['image_url']); ?>" 
                                            alt="<?php echo htmlspecialchars($poi['name']); ?>" 
                                            class="poi-image">
                                    <?php else: ?>
                                        <div class="poi-image" style="background: linear-cgradient(45deg, #3498db, #2c3e50); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem;">
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

                                <br>
                                <h4 style="padding: .5rem"><?php echo htmlspecialchars($poi['name']); ?></h4>
                                <p class="poi-category" style="color: #fffaf3; font-size: 0.9rem;"><?php echo ucfirst($poi['category']); ?></p>
                                <p class="poi-description" style="font-size: 0.9rem; padding: .5rem"><?php echo htmlspecialchars(substr($poi['description'], 0, 100)) . '...'; ?></p>
                                
                                <div class="poi-rating" style="display: flex; justify-content: space-between; margin: 1rem 0; font-size: 0.9rem; color: #666; padding: .5rem">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <?php echo $i <= $poi['rating'] ? '⭐' : '☆'; ?>
                                    <?php endfor; ?>
                                    (<?php echo $poi['rating']; ?>)
                                    <span>Views: <?php echo number_format($poi['view_count']); ?></span>
                                </div>
                                
                                <div style="display: flex; justify-content: space-between; margin: 1rem 0; font-size: 0.9rem; color: #666; padding: .5rem">
                                    <span>Reviews: <?php echo $poi['review_count']; ?></span>
                                    <span>Favorites: <?php echo $poi['favorite_count']; ?></span>
                                </div>
                                
                                <?php if ($poi['admin_notes']): ?>
                                    <div style="background: #fff3cd; padding: 0.5rem; border-radius: 4px; margin: 1rem 0; font-size: 0.9rem;">
                                        <strong>Admin Notes:</strong> <?php echo htmlspecialchars($poi['admin_notes']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="poi-actions" style="text-align: center">
                                    <a href="?edit=<?php echo $poi['id']; ?>" class="btn btn-primary">Edit</a>
                                    <a href="../poi-details.php?id=<?php echo $poi['id']; ?>" class="btn btn-primary" target="_blank">View</a>
                                </div>
                                
                                <div style="margin-top: 1rem; font-size: 0.8rem; color: #999; text-align: center">
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
                <h2>Edit Location: <?php echo htmlspecialchars($edit_poi['name']); ?></h2>
                <!-- Added enctype for file upload -->
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="poi_id" value="<?php echo $edit_poi['id']; ?>">
                    <input type="hidden" name="image_url" value="<?php echo htmlspecialchars($edit_poi['image_url']); ?>">
                    
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
                    
                    <!-- Replaced image URL input with file upload -->
                    <div class="form-group">
                        <label for="image_upload">Location Image</label>
                        <div class="image-upload-container">
                            <?php if ($edit_poi['image_url']): ?>
                                <div class="current-image">
                                    <p><strong>Current Image:</strong></p>
                                    <img src="<?php echo htmlspecialchars($edit_poi['image_url']); ?>" alt="Current image" class="image-preview" id="currentImage">
                                    <button type="button" class="remove-image-btn" onclick="removeCurrentImage()">Remove Current Image</button>
                                </div>
                            <?php endif; ?>
                            
                            <div class="image-upload-area" onclick="document.getElementById('image_upload').click()">
                                <div>📷</div>
                                <div><strong>Click to upload new image</strong></div>
                                <div class="upload-text">or drag and drop</div>
                                <div class="upload-text">JPG, PNG, GIF, WEBP (max 5MB)</div>
                            </div>
                            
                            <input type="file" id="image_upload" name="image_upload" accept="image/*" style="display: none;" onchange="previewImage(this)">
                            
                            <div id="imagePreview" style="display: none;">
                                <p><strong>New Image Preview:</strong></p>
                                <img id="previewImg" class="image-preview" alt="Preview">
                            </div>
                        </div>
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
        
        function previewImage(input) {
            const file = input.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.getElementById('imagePreview');
                    const previewImg = document.getElementById('previewImg');
                    previewImg.src = e.target.result;
                    previewDiv.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        }
        
        function removeCurrentImage() {
            const currentImageDiv = document.querySelector('.current-image');
            const hiddenInput = document.querySelector('input[name="image_url"]');
            
            if (confirm('Are you sure you want to remove the current image?')) {
                currentImageDiv.style.display = 'none';
                hiddenInput.value = '';
            }
        }
        
        // Drag and drop functionality
        const uploadArea = document.querySelector('.image-upload-area');
        const fileInput = document.getElementById('image_upload');
        
        if (uploadArea && fileInput) {
            ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, preventDefaults, false);
            });
            
            function preventDefaults(e) {
                e.preventDefault();
                e.stopPropagation();
            }
            
            ['dragenter', 'dragover'].forEach(eventName => {
                uploadArea.addEventListener(eventName, highlight, false);
            });
            
            ['dragleave', 'drop'].forEach(eventName => {
                uploadArea.addEventListener(eventName, unhighlight, false);
            });
            
            function highlight(e) {
                uploadArea.classList.add('dragover');
            }
            
            function unhighlight(e) {
                uploadArea.classList.remove('dragover');
            }
            
            uploadArea.addEventListener('drop', handleDrop, false);
            
            function handleDrop(e) {
                const dt = e.dataTransfer;
                const files = dt.files;
                
                if (files.length > 0) {
                    fileInput.files = files;
                    previewImage(fileInput);
                }
            }
        }
    </script>
</body>
</html>

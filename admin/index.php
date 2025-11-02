<?php
require_once '../config/config.php';

// Simple admin authentication (in production, use proper admin authentication)
if (!isLoggedIn() || $_SESSION['username'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle User operations
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_user'])) {
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $full_name = sanitizeInput($_POST['full_name']);
        $phone = sanitizeInput($_POST['phone']);
        $role = sanitizeInput($_POST['role']);
        
        $query = "INSERT INTO users (username, email, password, full_name, phone, role) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $email, $password, $full_name, $phone, $role]);
        
        // Log admin action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, new_data, description, ip_address) VALUES (?, 'create_user', 'user', ?, ?, 'Created new user', ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$admin_id, $db->lastInsertId(), json_encode(['username' => $username, 'role' => $role]), $_SERVER['REMOTE_ADDR']]);
        
        header('Location: index.php?tab=users&success=user_added');
        exit();
    }
    
    if (isset($_POST['update_user'])) {
        $user_id = (int)$_POST['user_id'];
        $username = sanitizeInput($_POST['username']);
        $email = sanitizeInput($_POST['email']);
        $full_name = sanitizeInput($_POST['full_name']);
        $phone = sanitizeInput($_POST['phone']);
        $role = sanitizeInput($_POST['role']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        
        // Get old data for logging
        $old_query = "SELECT * FROM users WHERE id = ?";
        $old_stmt = $db->prepare($old_query);
        $old_stmt->execute([$user_id]);
        $old_data = $old_stmt->fetch(PDO::FETCH_ASSOC);
        
        $query = "UPDATE users SET username = ?, email = ?, full_name = ?, phone = ?, role = ?, is_active = ? WHERE id = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$username, $email, $full_name, $phone, $role, $is_active, $user_id]);
        
        // Update password if provided
        if (!empty($_POST['password'])) {
            $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
            $pwd_query = "UPDATE users SET password = ? WHERE id = ?";
            $pwd_stmt = $db->prepare($pwd_query);
            $pwd_stmt->execute([$password, $user_id]);
        }
        
        // Log admin action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_data, new_data, description, ip_address) VALUES (?, 'update_user', 'user', ?, ?, ?, 'Updated user information', ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$admin_id, $user_id, json_encode($old_data), json_encode(['username' => $username, 'role' => $role, 'is_active' => $is_active]), $_SERVER['REMOTE_ADDR']]);
        
        header('Location: index.php?tab=users&success=user_updated');
        exit();
    }
    
    if (isset($_POST['delete_user'])) {
        $user_id = (int)$_POST['user_id'];
        
        // Get user data for logging
        $user_query = "SELECT * FROM users WHERE id = ?";
        $user_stmt = $db->prepare($user_query);
        $user_stmt->execute([$user_id]);
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        $query = "DELETE FROM users WHERE id = ? AND role != 'admin'";
        $stmt = $db->prepare($query);
        $stmt->execute([$user_id]);
        
        // Log admin action
        $admin_id = $_SESSION['user_id'];
        $log_query = "INSERT INTO admin_logs (admin_id, action, target_type, target_id, old_data, description, ip_address) VALUES (?, 'delete_user', 'user', ?, ?, 'Deleted user account', ?)";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$admin_id, $user_id, json_encode($user_data), $_SERVER['REMOTE_ADDR']]);
        
        header('Location: index.php?tab=users&success=user_deleted');
        exit();
    }
}

// Get current tab
$current_tab = $_GET['tab'] ?? 'dashboard';

// User search and filtering
$user_search = $_GET['user_search'] ?? '';
$role_filter = $_GET['role_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// Build user query with filters
$user_conditions = [];
$user_params = [];

if (!empty($user_search)) {
    $user_conditions[] = "(username LIKE ? OR email LIKE ? OR full_name LIKE ?)";
    $user_params[] = "%$user_search%";
    $user_params[] = "%$user_search%";
    $user_params[] = "%$user_search%";
}

if (!empty($role_filter)) {
    $user_conditions[] = "role = ?";
    $user_params[] = $role_filter;
}

if (!empty($status_filter)) {
    $user_conditions[] = "is_active = ?";
    $user_params[] = $status_filter === 'active' ? 1 : 0;
}

$user_where = !empty($user_conditions) ? 'WHERE ' . implode(' AND ', $user_conditions) : '';

// Get all users with filtering
$user_query = "SELECT *, 
    (SELECT COUNT(*) FROM user_favorites WHERE user_id = users.id) as favorite_count,
    (SELECT COUNT(*) FROM reviews WHERE user_id = users.id) as review_count
    FROM users $user_where ORDER BY created_at DESC";
$user_stmt = $db->prepare($user_query);
$user_stmt->execute($user_params);
$users = $user_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get user for editing
$edit_user = null;
if (isset($_GET['edit_user'])) {
    $edit_user_id = (int)$_GET['edit_user'];
    $edit_query = "SELECT * FROM users WHERE id = ?";
    $edit_stmt = $db->prepare($edit_query);
    $edit_stmt->execute([$edit_user_id]);
    $edit_user = $edit_stmt->fetch(PDO::FETCH_ASSOC);
}

// Get enhanced statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM points_of_interest) as total_pois,
    (SELECT COUNT(*) FROM users WHERE role = 'user') as total_users,
    (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
    (SELECT COUNT(*) FROM users WHERE is_active = 1) as active_users,
    (SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()) as new_users_today,
    (SELECT COUNT(*) FROM reviews) as total_reviews,
    (SELECT COUNT(*) FROM user_favorites) as total_favorites,
    (SELECT COUNT(*) FROM user_activities WHERE DATE(created_at) = CURDATE()) as activities_today";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
     <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@600&display=swap" rel="stylesheet">
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
            <h1 class="text-center mb-3">Admin Dashboard</h1>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <?php
                    switch($_GET['success']) {
                        case 'user_added': echo 'User successfully added!'; break;
                        case 'user_updated': echo 'User successfully updated!'; break;
                        case 'user_deleted': echo 'User successfully deleted!'; break;
                    }
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- removed places/POIs tab, now only Dashboard and Users tabs -->
            <div class="admin-tabs">
                <a href="?tab=dashboard" class="admin-tab <?php echo $current_tab === 'dashboard' ? 'active' : ''; ?>">Dashboard</a>
                <a href="?tab=users" class="admin-tab <?php echo $current_tab === 'users' ? 'active' : ''; ?>">Users</a>
            </div>
            
            <?php if ($current_tab === 'dashboard'): ?>
                <!-- Statistics Dashboard -->
                <div class="poi-grid mb-4">
                    <div class="card text-center">
                        <h3 style="color: #3498db;"><?php echo $stats['total_users']; ?></h3>
                        <p>Total Users</p>
                        <small><?php echo $stats['new_users_today']; ?> new today</small>
                    </div>
                    <div class="card text-center">
                        <h3 style="color: #27ae60;"><?php echo $stats['active_users']; ?></h3>
                        <p>Active Users</p>
                        <small><?php echo $stats['total_admins']; ?> admins</small>
                    </div>
                    <div class="card text-center">
                        <h3 style="color: #f39c12;"><?php echo $stats['total_pois']; ?></h3>
                        <p>Total Places</p>
                    </div>
                    <div class="card text-center">
                        <h3 style="color: #e74c3c;"><?php echo $stats['activities_today']; ?></h3>
                        <p>Activities Today</p>
                    </div>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
                    <div class="card">
                        <h4>Recent Users</h4>
                        <?php
                        $recent_users_query = "SELECT username, full_name, role, created_at FROM users ORDER BY created_at DESC LIMIT 5";
                        $recent_users_stmt = $db->prepare($recent_users_query);
                        $recent_users_stmt->execute();
                        $recent_users = $recent_users_stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                        <?php foreach ($recent_users as $user): ?>
                            <div style="padding: 0.5rem 0; border-bottom: 1px solid #eee;">
                                <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                <span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                                <br><small style="color: #666;">@<?php echo htmlspecialchars($user['username']); ?> • <?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="card">
                        <h4>System Overview</h4>
                        <div style="padding: 1rem 0;">
                            <div style="margin-bottom: 1rem;">
                                <strong>Reviews:</strong> <?php echo $stats['total_reviews']; ?>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Favorites:</strong> <?php echo $stats['total_favorites']; ?>
                            </div>
                            <div style="margin-bottom: 1rem;">
                                <strong>Database Status:</strong> <span style="color: #27ae60;">Connected</span>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php elseif ($current_tab === 'users'): ?>
                <div class="card mb-4">
                    <h2><?php echo $edit_user ? 'Edit User' : 'Add New User'; ?></h2>
                    <form method="POST">
                        <?php if ($edit_user): ?>
                            <input type="hidden" name="user_id" value="<?php echo $edit_user['id']; ?>">
                        <?php endif; ?>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                            <div class="form-group">
                                <label for="username">Username *</label>
                                <input type="text" id="username" name="username" class="form-control" 
                                       value="<?php echo $edit_user ? htmlspecialchars($edit_user['username']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="email">Email *</label>
                                <input type="email" id="email" name="email" class="form-control" 
                                       value="<?php echo $edit_user ? htmlspecialchars($edit_user['email']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="full_name">Full Name *</label>
                                <input type="text" id="full_name" name="full_name" class="form-control" 
                                       value="<?php echo $edit_user ? htmlspecialchars($edit_user['full_name']) : ''; ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" id="phone" name="phone" class="form-control" 
                                       value="<?php echo $edit_user ? htmlspecialchars($edit_user['phone']) : ''; ?>">
                            </div>
                            <div class="form-group">
                                <label for="role">Role *</label>
                                <select id="role" name="role" class="form-control" required>
                                    <option value="user" <?php echo ($edit_user && $edit_user['role'] === 'user') ? 'selected' : ''; ?>>User</option>
                                    <option value="admin" <?php echo ($edit_user && $edit_user['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                   
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="password">Password <?php echo $edit_user ? '(leave blank to keep current)' : '*'; ?></label>
                                <input type="password" id="password" name="password" class="form-control" <?php echo !$edit_user ? 'required' : ''; ?>>
                            </div>
                        </div>
                        
                        <?php if ($edit_user): ?>
                            <div class="form-group">
                                <label>
                                    <input type="checkbox" name="is_active" <?php echo $edit_user['is_active'] ? 'checked' : ''; ?>>
                                    Active User
                                </label>
                            </div>
                        <?php endif; ?>
                        
                        <button type="submit" name="<?php echo $edit_user ? 'update_user' : 'add_user'; ?>" class="btn btn-success">
                            <?php echo $edit_user ? 'Update User' : 'Add User'; ?>
                        </button>
                        <?php if ($edit_user): ?>
                            <a href="?tab=users" class="btn btn-secondary">Cancel</a>
                        <?php endif; ?>
                    </form>
                </div>
                
                <!-- User Filters -->
                <div class="card mb-4">
                    <h3>Filter Users</h3>
                    <form method="GET" class="user-filters">
                        <input type="hidden" name="tab" value="users">
                        <input type="text" name="user_search" placeholder="Search users..." value="<?php echo htmlspecialchars($user_search); ?>">
                        <select name="role_filter">
                            <option value="">All Roles</option>
                            <option value="user" <?php echo $role_filter === 'user' ? 'selected' : ''; ?>>User</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                            <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Super Admin</option>
                        </select>
                        <select name="status_filter">
                            <option value="">All Status</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="?tab=users" class="btn btn-secondary">Clear</a>
                    </form>
                </div>
                
                <!-- User List -->
                <div class="card">
                    <h3>Manage Users (<?php echo count($users); ?> users)</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f8f9fa;">
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">User</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Role</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Status</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Activity</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Joined</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid #ddd;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                                            <small style="color: #666;">@<?php echo htmlspecialchars($user['username']); ?></small><br>
                                            <small style="color: #666;"><?php echo htmlspecialchars($user['email']); ?></small>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <span class="role-badge <?php echo $user['role']; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <span class="user-status <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <small>
                                                <?php echo $user['favorite_count']; ?> favorites<br>
                                                <?php echo $user['review_count']; ?> reviews<br>
                                                <?php if ($user['last_login']): ?>
                                                    Last: <?php echo date('M j', strtotime($user['last_login'])); ?>
                                                <?php else: ?>
                                                    Never logged in
                                                <?php endif; ?>
                                            </small>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <?php echo date('M j, Y', strtotime($user['created_at'])); ?>
                                        </td>
                                        <td style="padding: 1rem; border-bottom: 1px solid #eee;">
                                            <a href="?tab=users&edit_user=<?php echo $user['id']; ?>" class="btn btn-primary" style="margin-right: 0.5rem;">Edit</a>
                                            <?php if ($user['role'] !== 'admin'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user?');">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                                                </form>
                                            <?php endif; ?>
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
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            const previewImg = document.getElementById(previewId + '-img');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    preview.style.display = 'block';
                };
                
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function removePreview(previewId, inputId) {
            const preview = document.getElementById(previewId);
            const input = document.getElementById(inputId);
            
            preview.style.display = 'none';
            input.value = '';
        }
    </script>
</body>
</html>

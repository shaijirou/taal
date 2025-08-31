<header>
    <div class="container">
        <div class="header-content">
            <div class="logo">
                <a href="index.php" style="color: white; text-decoration: none;">Ala Eh! 🌋</a>
            </div>
            <nav>
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="map.php">Map</a></li>
                    <li><a href="places.php">Places</a></li>
                    <li><a href="ai-guide.php">AI Guide</a></li>
                    <?php if (isLoggedIn()): ?>
                        <li><a href="profile.php">Profile</a></li>
                        <?php if (getUserRole() === 'super_admin'): ?>
                            <li><a href="admin/index.php">Administrator</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php">Logout</a></li>
                    <?php else: ?>
                        <li><a href="login.php">Login</a></li>
                        <li><a href="register.php">Register</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</header>

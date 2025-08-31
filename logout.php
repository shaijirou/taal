<?php
require_once 'config/config.php';

if (isLoggedIn()) {
    logUserActivity($_SESSION['user_id'], 'logout', null, ['logout_time' => date('Y-m-d H:i:s')]);
}

session_destroy();
header('Location: index.php');
exit();
?>

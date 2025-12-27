<?php
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

// Get logged-in user details
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$initials = 'AD';
$parts = explode(' ', $admin_name);
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
} elseif (!empty($admin_name)) {
    $initials = strtoupper(substr($admin_name, 0, 2));
}

// Fetch Unread Notifications
$unreadNotifications = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $unreadNotifications = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Silent fail
}

// Default page title if not set
if (!isset($page_title)) {
    $page_title = 'Admin Panel';
}
?>
<header class="top-header">
    <div class="header-left">
        <button id="sidebar-toggle" class="sidebar-toggle"><i class="fa-solid fa-bars"></i></button>
        <h1 class="page-title"><?php echo $page_title; ?></h1>
    </div>
    
    <div class="header-right">
        <div class="date-display"><i class="fa-regular fa-calendar"></i> <span><?php echo date('l, F j, Y'); ?></span></div>
        <div class="notification-bell">
            <a href="notifications.php" style="color: inherit; text-decoration: none;"><i class="fa-regular fa-bell"></i><?php if ($unreadNotifications > 0): ?><span class="badge"><?php echo $unreadNotifications; ?></span><?php endif; ?></a>
        </div>
        
        <div class="user-profile-header">
            <div class="user-info-text">
                <div class="user-name"><?php echo htmlspecialchars($admin_name); ?></div>
                <div class="user-role">Manager</div>
            </div>
            <div class="user-avatar-circle" onclick="openProfileModal()" style="cursor: pointer;"><?php echo htmlspecialchars($initials); ?></div>
            <a href="logout.php" title="Logout" class="logout-icon"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</header>

<!-- User Profile Modal -->
<div id="headerProfileModal" class="modal">
    <div class="modal-content" style="max-width: 400px; text-align: center;">
        <span class="close-modal" onclick="closeProfileModal()">&times;</span>
        <div style="margin-bottom: 20px; display: flex; justify-content: center;">
            <div style="width: 80px; height: 80px; background-color: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; font-weight: bold; color: #000;">
                <?php echo htmlspecialchars($initials); ?>
            </div>
        </div>
        <h2 style="margin: 0; font-size: 24px;"><?php echo htmlspecialchars($admin_name); ?></h2>
        <p style="margin: 5px 0 20px; color: #666;">Manager</p>
        
        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
            <a href="profile.php" class="btn-submit" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 20px;">Edit Profile</a>
            <a href="logout.php" class="btn-submit" style="text-decoration: none; display: inline-block; width: auto; padding: 10px 20px; background-color: #e74c3c;">Logout</a>
        </div>
    </div>
</div>

<script>
    const headerProfileModal = document.getElementById('headerProfileModal');

    function openProfileModal() {
        headerProfileModal.classList.add('show');
    }

    function closeProfileModal() {
        headerProfileModal.classList.remove('show');
    }

    // Close modal when clicking outside is handled by page-specific scripts usually, 
    // but we can add a specific listener here if needed. 
    // For now, relying on the close button or existing window.onclick handlers if they don't conflict.
</script>
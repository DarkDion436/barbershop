<?php
// Get logged-in user details
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$initials = 'AD';
$parts = explode(' ', $admin_name);
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
} elseif (!empty($admin_name)) {
    $initials = strtoupper(substr($admin_name, 0, 2));
}
?>
<!-- Sidebar Navigation -->
<aside id="sidebar" class="sidebar">
    
    <!-- 1. Brand Header -->
    <div class="sidebar-header">
        <!-- Using a scissors icon for the barbershop theme -->
        <i class="fa-solid fa-scissors logo-icon"></i>
        <div class="brand-name">
            Blade<span class="gold">&</span>Trim
        </div>
    </div>

    <!-- 2. Navigation Menu -->
    <nav class="sidebar-nav">
        <ul class="nav-list">
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <!-- Bookings (Appointments Table) -->
            <li class="nav-item">
                <a href="appointments.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-calendar-check"></i>
                    <span>Bookings</span>
                </a>
            </li>

            <!-- Services (Services Table) -->
            <li class="nav-item">
                <a href="services.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'services.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-list-ul"></i>
                    <span>Services & Pricing</span>
                </a>
            </li>

            <!-- Staff (Staff Table) -->
            <li class="nav-item">
                <a href="staff.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'staff.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i>
                    <span>Barbers & Staff</span>
                </a>
            </li>

            <!-- Gallery -->
            <li class="nav-item">
                <a href="gallery.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'gallery.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-images"></i>
                    <span>Gallery</span>
                </a>
            </li>

            <!-- Customers (Customers Table) -->
            <li class="nav-item">
                <a href="customers.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'customers.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-friends"></i>
                    <span>Customers</span>
                </a>
            </li>

            <!-- Reports (Reports Page) -->
            <li class="nav-item">
                <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                    <span>Reports</span>
                </a>
            </li>

            <!-- Notifications -->
            <li class="nav-item">
                <a href="notifications.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </li>

            <!-- Activity Log -->
            <li class="nav-item">
                <a href="view_activity_log.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'view_activity_log.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-history"></i>
                    <span>Activity Log</span>
                </a>
            </li>

            <!-- Settings (Settings Page) -->
            <li class="nav-item">
                <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>

            <!-- Profile (Profile Page) -->
            <li class="nav-item">
                <a href="profile.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-circle"></i>
                    <span>My Profile</span>
                </a>
            </li>
            
        </ul>
    </nav>

</aside>
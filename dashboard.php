<?php
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);

// Determine greeting based on time
$hour = date('H');
if ($hour < 12) {
    $greeting = "Good Morning";
} elseif ($hour < 18) {
    $greeting = "Good Afternoon";
} else {
    $greeting = "Good Evening";
}

// Get logged-in user details for header
$admin_name = $_SESSION['admin_name'] ?? 'Admin User';
$initials = 'AD';
$parts = explode(' ', $admin_name);
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
} elseif (!empty($admin_name)) {
    $initials = strtoupper(substr($admin_name, 0, 2));
}

// Initialize stats
$todaysBookings = 0;
$totalClients = 0;
$revenueToday = 0;
$activeStaff = 0;
$unreadNotifications = 0;
$recentAppointments = [];
$upcomingAppointments = [];
$stalePendingCount = 0;
$completedPay = 0;
$pendingPay = 0;
$completedBookingsCount = 0;
$cancelledBookingsCount = 0;
$unpaidCompletedCount = 0;

try {
    // 1. Today's Bookings
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE DATE(appointment_date) = CURDATE()");
    $todaysBookings = $stmt->fetchColumn();

    // 2. Total Clients (Unique emails)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT client_email) FROM appointments");
    $totalClients = $stmt->fetchColumn();

    // 3. Revenue Today
    $stmt = $pdo->query("SELECT SUM(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE DATE(a.appointment_date) = CURDATE() AND a.status != 'cancelled'");
    $revenueToday = $stmt->fetchColumn() ?: 0;

    // 4. Active Staff
    $stmt = $pdo->query("SELECT COUNT(*) FROM staff WHERE is_active = 1");
    $activeStaff = $stmt->fetchColumn();

    // 5. Unread Notifications
    $stmt = $pdo->query("SELECT COUNT(*) FROM notifications WHERE is_read = 0");
    $unreadNotifications = $stmt->fetchColumn();

    // 6. Recent Appointments
    $stmt = $pdo->query("SELECT a.*, s.name as service_name 
                         FROM appointments a 
                         JOIN services s ON a.service_id = s.id 
                         ORDER BY a.appointment_date DESC LIMIT 5");
    $recentAppointments = $stmt->fetchAll();

    // 7. Upcoming Appointments (Next 7 Days)
    $stmt = $pdo->query("SELECT a.*, s.name as service_name 
                         FROM appointments a 
                         JOIN services s ON a.service_id = s.id 
                         WHERE a.appointment_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
                         ORDER BY a.appointment_date ASC LIMIT 10");
    $upcomingAppointments = $stmt->fetchAll();

    // 8. Stale Pending Bookings (> 5 mins)
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending' AND created_at <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)");
    $stalePendingCount = $stmt->fetchColumn();

    // 9. Completed Pay (Total Revenue All Time)
    $stmt = $pdo->query("SELECT SUM(COALESCE(a.amount_paid, s.price)) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status = 'completed'");
    $completedPay = $stmt->fetchColumn() ?: 0;

    // 10. Pending Pay (Potential Revenue from Pending/Confirmed)
    $stmt = $pdo->query("SELECT SUM(s.price) FROM appointments a JOIN services s ON a.service_id = s.id WHERE a.status IN ('pending', 'confirmed')");
    $pendingPay = $stmt->fetchColumn() ?: 0;

    // 11. Completed Bookings Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'");
    $completedBookingsCount = $stmt->fetchColumn();

    // 12. Cancelled Bookings Count
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'");
    $cancelledBookingsCount = $stmt->fetchColumn();

    // 13. Completed but Unpaid Bookings
    $stmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed' AND (mpesa_code IS NULL OR mpesa_code = '' OR amount_paid <= 0)");
    $unpaidCompletedCount = $stmt->fetchColumn();

} catch (PDOException $e) {
    // Handle error silently or log
}

// Logic to show cancelled alert only once per new cancellation
$showCancelledAlert = false;
$lastSeenCount = $_SESSION['cancelled_alert_seen_count'] ?? 0;

if ($cancelledBookingsCount > $lastSeenCount) {
    $showCancelledAlert = true;
    $_SESSION['cancelled_alert_seen_count'] = $cancelledBookingsCount;
} elseif ($cancelledBookingsCount < $lastSeenCount) {
    $_SESSION['cancelled_alert_seen_count'] = $cancelledBookingsCount;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Blade & Trim</title>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .stat-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            transition: transform 0.2s;
        }
        .stat-card-link:hover {
            transform: translateY(-5px);
        }
        .stat-card-link .stat-card {
            height: 100%;
        }
    </style>
</head>
<body>

    <!-- Include Sidebar -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <main class="main-content">
        
        <?php $page_title = $greeting . ', ' . htmlspecialchars(explode(' ', $_SESSION['admin_name'] ?? 'Admin')[0]); ?>
        <?php include 'header.php'; ?>

        <!-- Dashboard Content -->
        <div class="content-container">
            
            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($stalePendingCount > 0): ?>
                <div class="alert-message" style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-triangle-exclamation" style="font-size: 20px;"></i>
                    <div style="flex: 1;">
                        <strong>Alert:</strong> There are <?php echo $stalePendingCount; ?> booking(s) pending for more than 5 minutes.
                        <a href="appointments.php?status=pending" style="color: #856404; font-weight: bold; text-decoration: underline; margin-left: 5px;">Review Pending Bookings</a>
                    </div>
                    <form method="POST" action="auto_cancel_stale.php" onsubmit="return confirm('Are you sure you want to auto-cancel (delete) all these stale bookings?');">
                        <button type="submit" style="background-color: #856404; color: #fff; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; font-weight: bold; font-size: 13px;">
                            <i class="fa-solid fa-trash-can"></i> Auto-Cancel All
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <?php if ($unpaidCompletedCount > 0): ?>
                <div class="alert-message" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-exclamation" style="font-size: 20px;"></i>
                    <div style="flex: 1;">
                        <strong>Action Required:</strong> There are <?php echo $unpaidCompletedCount; ?> completed booking(s) missing payment details.
                        <a href="appointments.php?status=completed" style="color: #721c24; font-weight: bold; text-decoration: underline; margin-left: 5px;">Review Payments</a>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($showCancelledAlert): ?>
                <div class="alert-message" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-ban" style="font-size: 20px;"></i>
                    <div style="flex: 1;">
                        <strong>Alert:</strong> There are <?php echo $cancelledBookingsCount; ?> cancelled booking(s).
                        <a href="reports.php" style="color: #721c24; font-weight: bold; text-decoration: underline; margin-left: 5px;">View in Reports</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="stats-grid">
                <a href="appointments.php?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon bg-gold">
                        <i class="fa-solid fa-calendar-check"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $todaysBookings; ?></h3>
                        <p>Today's Bookings</p>
                    </div>
                </div>
                </a>
                
                <a href="customers.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon bg-blue">
                        <i class="fa-solid fa-users"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $totalClients; ?></h3>
                        <p>Total Clients</p>
                    </div>
                </div>
                </a>

                <a href="appointments.php?start_date=<?php echo date('Y-m-d'); ?>&end_date=<?php echo date('Y-m-d'); ?>" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon bg-green">
                        <i class="fa-solid fa-dollar-sign"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($revenueToday, 2); ?></h3>
                        <p>Revenue Today</p>
                    </div>
                </div>
                </a>

                <a href="staff.php" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon bg-purple">
                        <i class="fa-solid fa-scissors"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $activeStaff; ?></h3>
                        <p>Active Staff</p>
                    </div>
                </div>
                </a>

                <!-- New Stats Cards -->
                <a href="appointments.php?status=completed" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #2ecc71; color: white;">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($completedPay, 2); ?></h3>
                        <p>Completed Pay</p>
                    </div>
                </div>
                </a>

                <a href="appointments.php?status=pending" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #f1c40f; color: white;">
                        <i class="fa-solid fa-hourglass-half"></i>
                    </div>
                    <div class="stat-info">
                        <h3>$<?php echo number_format($pendingPay, 2); ?></h3>
                        <p>Pending Pay</p>
                    </div>
                </div>
                </a>

                <a href="appointments.php?status=completed" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #3498db; color: white;">
                        <i class="fa-solid fa-check-double"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $completedBookingsCount; ?></h3>
                        <p>Completed Bookings</p>
                    </div>
                </div>
                </a>

                <a href="appointments.php?status=cancelled" class="stat-card-link">
                <div class="stat-card">
                    <div class="stat-icon" style="background-color: #e74c3c; color: white;">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div class="stat-info">
                        <h3><?php echo $cancelledBookingsCount; ?></h3>
                        <p>Cancelled Bookings</p>
                    </div>
                </div>
                </a>
            </div>

            <!-- Recent Activity Section -->
            <div style="margin-top: 30px; display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">

                <!-- Recent Activity -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        Recent Activity
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php if (empty($recentAppointments)): ?>
                            <div style="text-align: center; color: #888;">No recent activity.</div>
                        <?php else: ?>
                            <?php foreach ($recentAppointments as $appt): ?>
                                <div style="display: flex; align-items: center; gap: 15px; padding-bottom: 15px; border-bottom: 1px solid #f5f5f5;">
                                    <div style="width: 40px; height: 40px; background-color: rgba(212, 175, 55, 0.1); color: var(--accent-gold); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-calendar-check"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($appt['client_name']); ?></div>
                                        <div style="font-size: 12px; color: #666;">Booked for <?php echo htmlspecialchars($appt['service_name']); ?></div>
                                    </div>
                                    <div style="font-size: 12px; color: #888; text-align: right;">
                                        <div><?php echo date('M j', strtotime($appt['appointment_date'])); ?></div>
                                        <div><?php echo date('g:i A', strtotime($appt['appointment_date'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Upcoming Appointments -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        Upcoming (Next 7 Days)
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php if (empty($upcomingAppointments)): ?>
                            <div style="text-align: center; color: #888;">No upcoming appointments.</div>
                        <?php else: ?>
                            <?php foreach ($upcomingAppointments as $appt): ?>
                                <div style="display: flex; align-items: center; gap: 15px; padding-bottom: 15px; border-bottom: 1px solid #f5f5f5;">
                                    <div style="width: 40px; height: 40px; background-color: rgba(52, 152, 219, 0.1); color: #3498db; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fa-solid fa-clock"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="font-weight: 600; font-size: 14px;"><?php echo htmlspecialchars($appt['client_name']); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($appt['service_name']); ?></div>
                                    </div>
                                    <div style="font-size: 12px; color: #888; text-align: right;">
                                        <div><?php echo date('M j', strtotime($appt['appointment_date'])); ?></div>
                                        <div><?php echo date('g:i A', strtotime($appt['appointment_date'])); ?></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Scripts -->
    <script src="sidebar.js"></script>
</body>
</html>
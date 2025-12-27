<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20; // Items per page
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

try {
    // Get Total Count
    $whereCount = "WHERE 1=1";
    $whereMain = "WHERE 1=1";
    $params = [];
    
    if (!empty($search)) {
        $whereCount .= " AND (action LIKE ? OR details LIKE ?)";
        $whereMain .= " AND (al.action LIKE ? OR al.details LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    if (!empty($start_date)) {
        $whereCount .= " AND DATE(created_at) >= ?";
        $whereMain .= " AND DATE(al.created_at) >= ?";
        $params[] = $start_date;
    }

    if (!empty($end_date)) {
        $whereCount .= " AND DATE(created_at) <= ?";
        $whereMain .= " AND DATE(al.created_at) <= ?";
        $params[] = $end_date;
    }
    
    $countSql = "SELECT COUNT(*) FROM activity_log $whereCount";
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // Get Data
    $sql = "SELECT al.*, a.username 
            FROM activity_log al 
            LEFT JOIN admins a ON al.admin_id = a.id 
            $whereMain 
            ORDER BY al.created_at DESC 
            LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching activity log: " . $e->getMessage();
    // If table doesn't exist, fail gracefully
    if (strpos($e->getMessage(), "doesn't exist") !== false) {
        $error .= " <br>Please run <a href='setup_activity_log.php'>setup_activity_log.php</a> first.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .pagination-btn {
            padding: 5px 10px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            border-radius: 3px;
            text-decoration: none;
            color: #333;
            display: inline-block;
        }
        .pagination-btn:hover {
            background-color: #f0f0f0;
        }
        .pagination-btn.active {
            background-color: var(--accent-gold);
            color: #000;
            border-color: var(--accent-gold);
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Activity Log'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Search Filter -->
            <div style="margin-bottom: 20px;">
                <form method="GET" action="view_activity_log.php" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <input type="text" name="search" class="form-control" placeholder="Search action or details..." value="<?php echo htmlspecialchars($search); ?>" style="max-width: 300px;">
                    <input type="date" name="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date); ?>" style="max-width: 150px;" title="Start Date">
                    <span style="color: #666;">to</span>
                    <input type="date" name="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date); ?>" style="max-width: 150px;" title="End Date">
                    <button type="submit" class="btn-submit" style="width: auto; margin: 0; padding: 10px 20px;">Filter</button>
                    <?php if (!empty($search) || !empty($start_date) || !empty($end_date)): ?>
                        <a href="view_activity_log.php" style="color: #666; text-decoration: none; margin-left: 5px;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($logs)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">No activity recorded yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                                <td style="font-weight: 500;"><?php echo htmlspecialchars($log['action']); ?></td>
                                <td style="color: #666; font-size: 13px;"><?php echo htmlspecialchars($log['details'] ?? ''); ?></td>
                                <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if (isset($totalPages) && $totalPages > 1): ?>
            <div style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&start_date=<?php echo urlencode($start_date); ?>&end_date=<?php echo urlencode($end_date); ?>" class="pagination-btn <?php echo ($i == $page) ? 'active' : ''; ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <script src="sidebar.js"></script>
</body>
</html>
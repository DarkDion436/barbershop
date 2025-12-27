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

// AJAX Handler: Transactions Pagination
if (isset($_GET['ajax_type']) && $_GET['ajax_type'] === 'transactions') {
    header('Content-Type: application/json');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    try {
        // Count
        $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'");
        $totalRecords = $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // Data
        $sql = "SELECT a.*, s.name as service_name 
                FROM appointments a 
                JOIN services s ON a.service_id = s.id 
                WHERE a.status = 'completed' 
                ORDER BY a.updated_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($sql);
        $transactions = $stmt->fetchAll();

        $html = '';
        if (empty($transactions)) {
            $html .= '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">No completed transactions found.</td></tr>';
        } else {
            foreach ($transactions as $trans) {
                $html .= '<tr>';
                $html .= '<td>' . date('M j, Y g:i A', strtotime($trans['updated_at'] ?? $trans['appointment_date'])) . '</td>';
                $html .= '<td><div style="font-weight: 600;">' . htmlspecialchars($trans['client_name']) . '</div><div style="font-size: 12px; color: #666;">' . htmlspecialchars($trans['client_phone']) . '</div></td>';
                $html .= '<td>' . htmlspecialchars($trans['service_name']) . '</td>';
                $html .= '<td style="font-family: monospace; color: #2ecc71; font-weight: 600;">' . htmlspecialchars($trans['mpesa_code'] ?? 'N/A') . '</td>';
                $html .= '<td style="font-weight: bold;">$' . number_format($trans['amount_paid'] ?? 0, 2) . '</td>';
                $html .= '</tr>';
            }
        }

        $paginationHtml = '';
        if ($totalPages > 1) {
             $paginationHtml .= '<div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">';
            if ($page > 1) $paginationHtml .= '<button class="pagination-btn" onclick="loadTransactions(' . ($page - 1) . ')">&laquo; Prev</button>';
            for ($i = 1; $i <= $totalPages; $i++) {
                $activeClass = ($i == $page) ? 'active' : '';
                $style = ($i == $page) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : '';
                $paginationHtml .= '<button class="pagination-btn ' . $activeClass . '" style="' . $style . '" onclick="loadTransactions(' . $i . ')">' . $i . '</button>';
            }
            if ($page < $totalPages) $paginationHtml .= '<button class="pagination-btn" onclick="loadTransactions(' . ($page + 1) . ')">Next &raquo;</button>';
            $paginationHtml .= '</div>';
        }

        echo json_encode(['html' => $html, 'pagination' => $paginationHtml]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// AJAX Handler: Cancelled Pagination
if (isset($_GET['ajax_type']) && $_GET['ajax_type'] === 'cancelled') {
    header('Content-Type: application/json');
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = 10;
    $offset = ($page - 1) * $limit;

    try {
        // Count
        $countStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'");
        $totalRecords = $countStmt->fetchColumn();
        $totalPages = ceil($totalRecords / $limit);

        // Data
        $sql = "SELECT a.*, s.name as service_name 
                FROM appointments a 
                JOIN services s ON a.service_id = s.id 
                WHERE a.status = 'cancelled' 
                ORDER BY a.updated_at DESC LIMIT $limit OFFSET $offset";
        $stmt = $pdo->query($sql);
        $cancelledBookings = $stmt->fetchAll();

        $html = '';
        if (empty($cancelledBookings)) {
            $html .= '<tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">No cancelled bookings found.</td></tr>';
        } else {
            foreach ($cancelledBookings as $cancelled) {
                $html .= '<tr>';
                $html .= '<td>' . date('M j, Y g:i A', strtotime($cancelled['updated_at'] ?? $cancelled['appointment_date'])) . '</td>';
                $html .= '<td><div style="font-weight: 600;">' . htmlspecialchars($cancelled['client_name']) . '</div><div style="font-size: 12px; color: #666;">' . htmlspecialchars($cancelled['client_phone']) . '</div></td>';
                $html .= '<td>' . htmlspecialchars($cancelled['service_name']) . '</td>';
                $html .= '<td style="color: #666; font-style: italic;">' . htmlspecialchars($cancelled['notes'] ?: 'No reason provided') . '</td>';
                $html .= '<td><button type="button" class="btn-action" style="color: #2ecc71;" title="Restore Booking" onclick="openRestoreModal(' . $cancelled['id'] . ')"><i class="fa-solid fa-rotate-left"></i></button></td>';
                $html .= '</tr>';
            }
        }

        $paginationHtml = '';
        if ($totalPages > 1) {
             $paginationHtml .= '<div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">';
            if ($page > 1) $paginationHtml .= '<button class="pagination-btn" onclick="loadCancelled(' . ($page - 1) . ')">&laquo; Prev</button>';
            for ($i = 1; $i <= $totalPages; $i++) {
                $activeClass = ($i == $page) ? 'active' : '';
                $style = ($i == $page) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : '';
                $paginationHtml .= '<button class="pagination-btn ' . $activeClass . '" style="' . $style . '" onclick="loadCancelled(' . $i . ')">' . $i . '</button>';
            }
            if ($page < $totalPages) $paginationHtml .= '<button class="pagination-btn" onclick="loadCancelled(' . ($page + 1) . ')">Next &raquo;</button>';
            $paginationHtml .= '</div>';
        }

        echo json_encode(['html' => $html, 'pagination' => $paginationHtml]);
    } catch (PDOException $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

$message = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'restored') {
    $message = "Appointment restored successfully.";
}

// Handle Restore Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_appointment'])) {
    $id = $_POST['appointment_id'] ?? '';
    
    if (!empty($id)) {
        try {
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'pending' WHERE id = ?");
            $stmt->execute([$id]);
            header("Location: reports.php?msg=restored");
            exit;
        } catch (PDOException $e) {
            $error = "Error restoring appointment: " . $e->getMessage();
        }
    }
}

// Fetch Monthly Revenue
try {
    // We'll fetch the last 12 months of data
    // Using status != 'cancelled' to show projected/actual revenue
    $sql = "SELECT 
                DATE_FORMAT(a.appointment_date, '%Y-%m') as month_year,
                SUM(s.price) as total_revenue
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            WHERE a.status != 'cancelled'
            GROUP BY month_year
            ORDER BY month_year ASC
            LIMIT 12";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll();

    $labels = [];
    $revenue = [];

    foreach ($results as $row) {
        $date = DateTime::createFromFormat('Y-m', $row['month_year']);
        $labels[] = $date->format('M Y');
        $revenue[] = $row['total_revenue'];
    }

    // Fetch Status Counts (Completed & Cancelled)
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM appointments GROUP BY status");
    $statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $completedCount = $statusCounts['completed'] ?? 0;
    $cancelledCount = $statusCounts['cancelled'] ?? 0;

    // Fetch Recent Transactions (Completed Bookings with Payment Info) - Initial Load (Page 1)
    $transLimit = 10;
    $transCountStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'completed'");
    $totalTransRecords = $transCountStmt->fetchColumn();
    $totalTransPages = ceil($totalTransRecords / $transLimit);

    $transSql = "SELECT a.*, s.name as service_name 
                 FROM appointments a 
                 JOIN services s ON a.service_id = s.id 
                 WHERE a.status = 'completed' 
                 ORDER BY a.updated_at DESC LIMIT $transLimit";
    $transStmt = $pdo->query($transSql);
    $transactions = $transStmt->fetchAll();

    // Fetch Cancelled Bookings - Initial Load (Page 1)
    $cancLimit = 10;
    $cancCountStmt = $pdo->query("SELECT COUNT(*) FROM appointments WHERE status = 'cancelled'");
    $totalCancRecords = $cancCountStmt->fetchColumn();
    $totalCancPages = ceil($totalCancRecords / $cancLimit);

    $cancelledSql = "SELECT a.*, s.name as service_name 
                 FROM appointments a 
                 JOIN services s ON a.service_id = s.id 
                 WHERE a.status = 'cancelled' 
                 ORDER BY a.updated_at DESC LIMIT $cancLimit";
    $cancelledStmt = $pdo->query($cancelledSql);
    $cancelledBookings = $cancelledStmt->fetchAll();

} catch (PDOException $e) {
    $error = "Database Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .pagination-btn {
            padding: 5px 10px;
            border: 1px solid #ddd;
            background: #fff;
            cursor: pointer;
            border-radius: 3px;
        }
        .pagination-btn:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Analytics & Reports'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <?php if (isset($error)): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>
            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Charts Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
                
                <!-- Revenue Chart -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        Monthly Revenue
                    </h3>
                    <div style="height: 300px;">
                        <canvas id="revenueChart"></canvas>
                    </div>
                </div>

                <!-- Summary Stats -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        Performance Overview
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        <div>
                            <span style="color: #888; font-size: 14px;">Total Revenue (YTD)</span>
                            <div style="font-size: 24px; font-weight: bold; color: #333;">
                                $<?php echo number_format(array_sum($revenue), 2); ?>
                            </div>
                        </div>
                        <div>
                            <span style="color: #888; font-size: 14px;">Best Month</span>
                            <div style="font-size: 18px; font-weight: 600; color: var(--accent-gold);">
                                <?php 
                                    if (!empty($revenue)) {
                                        $maxVal = max($revenue);
                                        $maxIndex = array_search($maxVal, $revenue);
                                        echo $labels[$maxIndex] . " ($" . number_format($maxVal, 2) . ")";
                                    } else {
                                        echo "N/A";
                                    }
                                ?>
                            </div>
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 10px; padding-top: 20px; border-top: 1px solid #eee;">
                            <div>
                                <span style="color: #888; font-size: 14px;">Completed Bookings</span>
                                <div style="font-size: 20px; font-weight: 600; color: #2ecc71;">
                                    <?php echo $completedCount; ?>
                                </div>
                            </div>
                            <div>
                                <span style="color: #888; font-size: 14px;">Cancelled Bookings</span>
                                <div style="font-size: 20px; font-weight: 600; color: #e74c3c;">
                                    <?php echo $cancelledCount; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Transactions Table -->
            <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-top: 30px;">
                <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                    Recent Transactions
                </h3>
                <div class="table-responsive" style="box-shadow: none; padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date Completed</th>
                                <th>Client Details</th>
                                <th>Service</th>
                                <th>M-Pesa Code</th>
                                <th>Amount Paid</th>
                            </tr>
                        </thead>
                        <tbody id="transactionsTableBody">
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="5" style="text-align: center; padding: 20px; color: #888;">No completed transactions found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $trans): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($trans['updated_at'] ?? $trans['appointment_date'])); ?></td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($trans['client_name']); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($trans['client_phone']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($trans['service_name']); ?></td>
                                    <td style="font-family: monospace; color: #2ecc71; font-weight: 600;"><?php echo htmlspecialchars($trans['mpesa_code'] ?? 'N/A'); ?></td>
                                    <td style="font-weight: bold;">$<?php echo number_format($trans['amount_paid'] ?? 0, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Transactions Pagination -->
                <div id="transactionsPagination" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
                    <?php if ($totalTransPages > 1): ?>
                        <?php for ($i = 1; $i <= $totalTransPages; $i++): ?>
                            <?php $style = ($i == 1) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : ''; ?>
                            <button class="pagination-btn" style="<?php echo $style; ?>" onclick="loadTransactions(<?php echo $i; ?>)"><?php echo $i; ?></button>
                        <?php endfor; ?>
                        
                        <?php if (1 < $totalTransPages): ?>
                            <button class="pagination-btn" onclick="loadTransactions(2)">Next &raquo;</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Cancelled Bookings Table -->
            <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-top: 30px;">
                <h3 style="margin-top: 0; color: #e74c3c; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                    Cancelled Bookings
                </h3>
                <div class="table-responsive" style="box-shadow: none; padding: 0;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date Cancelled</th>
                                <th>Client Details</th>
                                <th>Service</th>
                                <th>Reason/Notes</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="cancelledTableBody">
                            <?php if (empty($cancelledBookings)): ?>
                                <tr><td colspan="4" style="text-align: center; padding: 20px; color: #888;">No cancelled bookings found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($cancelledBookings as $cancelled): ?>
                                <tr>
                                    <td><?php echo date('M j, Y g:i A', strtotime($cancelled['updated_at'] ?? $cancelled['appointment_date'])); ?></td>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo htmlspecialchars($cancelled['client_name']); ?></div>
                                        <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($cancelled['client_phone']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($cancelled['service_name']); ?></td>
                                    <td style="color: #666; font-style: italic;"><?php echo htmlspecialchars($cancelled['notes'] ?: 'No reason provided'); ?></td>
                                    <td>
                                        <button type="button" class="btn-action" style="color: #2ecc71;" title="Restore Booking" onclick="openRestoreModal(<?php echo $cancelled['id']; ?>)"><i class="fa-solid fa-rotate-left"></i></button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <!-- Cancelled Pagination -->
                <div id="cancelledPagination" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
                    <?php if ($totalCancPages > 1): ?>
                        <?php for ($i = 1; $i <= $totalCancPages; $i++): ?>
                            <?php $style = ($i == 1) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : ''; ?>
                            <button class="pagination-btn" style="<?php echo $style; ?>" onclick="loadCancelled(<?php echo $i; ?>)"><?php echo $i; ?></button>
                        <?php endfor; ?>
                        
                        <?php if (1 < $totalCancPages): ?>
                            <button class="pagination-btn" onclick="loadCancelled(2)">Next &raquo;</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </main>

    <!-- Restore Confirmation Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="close-modal" onclick="closeRestoreModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <i class="fa-solid fa-rotate-left" style="font-size: 48px; color: #2ecc71;"></i>
            </div>
            <h3 style="margin-top: 0; margin-bottom: 10px;">Restore Booking?</h3>
            <p style="color: #666; margin-bottom: 25px;">Are you sure you want to restore this booking to Pending status?</p>
            
            <form method="POST" action="reports.php">
                <input type="hidden" name="restore_appointment" value="1">
                <input type="hidden" name="appointment_id" id="restore_appointment_id">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeRestoreModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: #fff; border-radius: 5px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-submit" style="width: auto; margin-top: 0; background-color: #2ecc71; border-color: #2ecc71;">Restore</button>
                </div>
            </form>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        const restoreModal = document.getElementById('restoreModal');
        const restoreInput = document.getElementById('restore_appointment_id');

        function openRestoreModal(id) {
            restoreInput.value = id;
            restoreModal.classList.add('show');
        }

        function closeRestoreModal() {
            restoreModal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == restoreModal) {
                closeRestoreModal();
            }
        }

        // Chart Configuration
        const ctx = document.getElementById('revenueChart').getContext('2d');
        
        // Data from PHP
        const labels = <?php echo json_encode($labels); ?>;
        const data = <?php echo json_encode($revenue); ?>;

        const revenueChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Revenue ($)',
                    data: data,
                    backgroundColor: '#d4af37',
                    borderColor: '#b5952f',
                    borderWidth: 1,
                    borderRadius: 4,
                    barPercentage: 0.6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: function(value) { return '$' + value; } } },
                    x: { grid: { display: false } }
                }
            }
        });

        // Pagination Functions
        function loadTransactions(page) {
            fetch(`reports.php?ajax_type=transactions&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('transactionsTableBody').innerHTML = data.html;
                    document.getElementById('transactionsPagination').innerHTML = data.pagination;
                })
                .catch(error => console.error('Error:', error));
        }

        function loadCancelled(page) {
            fetch(`reports.php?ajax_type=cancelled&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('cancelledTableBody').innerHTML = data.html;
                    document.getElementById('cancelledPagination').innerHTML = data.pagination;
                })
                .catch(error => console.error('Error:', error));
        }
    </script>
</body>
</html>
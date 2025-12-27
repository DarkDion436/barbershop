<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include database connection
require_once 'db_connect.php';

// Ensure payment columns exist (Auto-migration)
try {
    $pdo->query("SELECT mpesa_code FROM appointments LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN mpesa_code VARCHAR(50) DEFAULT NULL, ADD COLUMN amount_paid DECIMAL(10,2) DEFAULT NULL");
}

// Ensure updated_at column exists (Auto-migration for tracking status changes)
try {
    $pdo->query("SELECT updated_at FROM appointments LIMIT 1");
} catch (PDOException $e) {
    $pdo->exec("ALTER TABLE appointments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
}

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);

// Retrieve form data if available (for repopulating form on error)
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

// Handle Cancel Appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $id = $_POST['appointment_id'] ?? '';
    $reason = trim($_POST['cancellation_reason'] ?? '');

    if (!empty($id)) {
        try {
            // Append reason to notes and update status
            $stmt = $pdo->prepare("UPDATE appointments SET status = 'cancelled', notes = CONCAT(COALESCE(notes, ''), ?) WHERE id = ?");
            $reason_text = empty($reason) ? "" : "\n[Cancelled: " . $reason . "]";
            $stmt->execute([$reason_text, $id]);
            $_SESSION['message'] = "Appointment cancelled successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error cancelling appointment: " . $e->getMessage();
        }
    }
    header("Location: appointments.php");
    exit;
}

// Handle Complete Appointment (Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['complete_appointment'])) {
    $id = $_POST['appointment_id'] ?? '';
    $mpesa_code = trim($_POST['mpesa_code'] ?? '');
    $amount_paid = $_POST['amount_paid'] ?? 0;

    if (!empty($id) && !empty($mpesa_code) && !empty($amount_paid)) {
        try {
            // Check if M-Pesa code already exists
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM appointments WHERE mpesa_code = ? AND id != ?");
            $checkStmt->execute([$mpesa_code, $id]);
            
            if ($checkStmt->fetchColumn() > 0) {
                $_SESSION['error'] = "Error: This M-Pesa code has already been recorded for another transaction.";
            } else {
                $stmt = $pdo->prepare("UPDATE appointments SET status = 'completed', mpesa_code = ?, amount_paid = ? WHERE id = ?");
                $stmt->execute([$mpesa_code, $amount_paid, $id]);
                $_SESSION['message'] = "Appointment completed and payment recorded successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating appointment: " . $e->getMessage();
        }
    }
    header("Location: appointments.php");
    exit;
}

// Handle New Appointment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_appointment'])) {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $service_id = $_POST['service_id'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';

    if (empty($client_name) || empty($service_id) || empty($staff_id) || empty($appointment_date)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        $_SESSION['form_data'] = $_POST;
    } else {
        try {
            // Check if the staff member is already booked within 1 hour
            $checkSql = "SELECT COUNT(*) FROM appointments 
                         WHERE staff_id = ? 
                         AND status != 'cancelled' 
                         AND ABS(TIMESTAMPDIFF(MINUTE, appointment_date, ?)) < 60";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$staff_id, $appointment_date]);

            if ($checkStmt->fetchColumn() > 0) {
                $_SESSION['error'] = "This staff member is already booked within the hour. Please select a different staff member.";
                $_SESSION['form_data'] = $_POST;
            } else {
                $sql = "INSERT INTO appointments (client_name, client_email, client_phone, service_id, staff_id, appointment_date, status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed')";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$client_name, $client_email, $client_phone, $service_id, $staff_id, $appointment_date]);
                $_SESSION['message'] = "New appointment created successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error creating appointment: " . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
    }
    // Redirect
    header("Location: appointments.php");
    exit;
}

// Fetch Services & Staff for Modal
$services = [];
$staff_members = [];
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY name ASC")->fetchAll();
    $staff_members = $pdo->query("SELECT * FROM staff ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    // Silent fail for dropdowns, main error will be caught below if DB is down
}

// Filter Logic
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

try {
    // 1. Get Total Count for Pagination
    $countSql = "SELECT COUNT(*) FROM appointments a WHERE 1=1";
    $params = [];
    
    // Exclude fully completed/paid appointments AND cancelled appointments (Archived to Reports)
    $countSql .= " AND NOT (a.status = 'completed' AND a.mpesa_code IS NOT NULL AND a.mpesa_code != '' AND a.amount_paid > 0) AND a.status != 'cancelled'";
    
    if (!empty($search)) {
        $countSql .= " AND a.client_name LIKE ?";
        $params[] = "%$search%";
    }
    
    if (!empty($status_filter)) {
        $countSql .= " AND a.status = ?";
        $params[] = $status_filter;
    }

    if (!empty($start_date)) {
        $countSql .= " AND DATE(a.appointment_date) >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $countSql .= " AND DATE(a.appointment_date) <= ?";
        $params[] = $end_date;
    }
    
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // 2. Get Data with Limit/Offset
    $sql = "SELECT a.*, s.name AS service_name, s.price, st.name AS staff_name 
            FROM appointments a 
            JOIN services s ON a.service_id = s.id 
            JOIN staff st ON a.staff_id = st.id 
            WHERE 1=1";
            
    // Exclude fully completed/paid appointments AND cancelled appointments (Archived to Reports)
    $sql .= " AND NOT (a.status = 'completed' AND a.mpesa_code IS NOT NULL AND a.mpesa_code != '' AND a.amount_paid > 0) AND a.status != 'cancelled'";
            
    if (!empty($search)) $sql .= " AND a.client_name LIKE ?";
    if (!empty($status_filter)) $sql .= " AND a.status = ?";
    if (!empty($start_date)) $sql .= " AND DATE(a.appointment_date) >= ?";
    if (!empty($end_date)) $sql .= " AND DATE(a.appointment_date) <= ?";
    
    $sql .= " ORDER BY a.appointment_date DESC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $appointments = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching appointments: " . $e->getMessage();
}

// AJAX Handler for Automatic Filtering
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    $html = '';
    if (!empty($appointments)) {
        foreach ($appointments as $appt) {
            $date = new DateTime($appt['appointment_date']);
            $statusClass = 'status-' . strtolower($appt['status']);
            
            // Check for stale pending (created > 5 mins ago)
            $createdAt = new DateTime($appt['created_at']);
            $diff = time() - $createdAt->getTimestamp();
            $isStale = ($appt['status'] === 'pending' && $diff > 300);
            
            // Check for locked cancelled (cancelled > 10 mins ago)
            $updatedAt = new DateTime($appt['updated_at'] ?? $appt['created_at']);
            $diffUpdate = time() - $updatedAt->getTimestamp();
            $isLockedCancelled = ($appt['status'] === 'cancelled' && $diffUpdate > 600);
            
            // Check for missing payment on completed bookings
            $paymentMissing = ($appt['status'] === 'completed' && (empty($appt['mpesa_code']) || $appt['amount_paid'] <= 0));
            
            $html .= '<tr>';
            $html .= '<td><div style="font-weight: 600;">' . $date->format('M j, Y') . '</div><div style="font-size: 13px; color: #888;">' . $date->format('g:i A') . '</div></td>';
            $html .= '<td><div style="font-weight: 500;">' . htmlspecialchars($appt['client_name']) . '</div><div style="font-size: 12px; color: #888;">' . htmlspecialchars($appt['client_phone']) . '</div></td>';
            $html .= '<td>' . htmlspecialchars($appt['service_name']) . '</td>';
            $html .= '<td>' . htmlspecialchars($appt['staff_name']) . '</td>';
            $html .= '<td>$' . number_format($appt['price'], 2) . '</td>';
            $html .= '<td><span class="status-badge ' . $statusClass . '">' . ucfirst($appt['status']) . '</span>';
            if ($isStale) {
                $html .= '<div style="color: #e74c3c; font-size: 11px; margin-top: 3px; font-weight: bold;"><i class="fa-solid fa-clock"></i> Overdue</div>';
            }
            if ($paymentMissing) {
                $html .= '<div style="color: #e74c3c; font-size: 11px; margin-top: 3px; font-weight: bold;"><i class="fa-solid fa-triangle-exclamation"></i> No Payment Code</div>';
            }
            $html .= '</td>';
            $html .= '<td>';
            if (($appt['status'] !== 'completed' && $appt['status'] !== 'cancelled') || $paymentMissing) {
                $btnColor = $paymentMissing ? "#f39c12" : "#2ecc71";
                $btnTitle = $paymentMissing ? "Add Payment Code" : "Complete & Pay";
                $btnIcon = $paymentMissing ? "fa-file-invoice-dollar" : "fa-check-circle";
                $html .= '<button type="button" class="btn-action" style="color: ' . $btnColor . ';" title="' . $btnTitle . '" onclick="openPaymentModal(' . $appt['id'] . ', ' . $appt['price'] . ')"><i class="fa-solid ' . $btnIcon . '"></i></button> ';
            }
            if ($appt['status'] !== 'completed' && $appt['status'] !== 'cancelled') {
                $html .= '<button type="button" class="btn-action" style="color: #e67e22;" title="Cancel Booking" onclick="openCancelModal(' . $appt['id'] . ')"><i class="fa-solid fa-ban"></i></button> ';
            }
            if ($appt['status'] === 'completed') {
                $html .= '<a href="generate_receipt.php?id=' . $appt['id'] . '" target="_blank" class="btn-action" style="color: #3498db;" title="Print Receipt"><i class="fa-solid fa-print"></i></a> ';
            }
            if ($appt['status'] !== 'completed' && !$isLockedCancelled) {
                $html .= '<a href="edit_appointment.php?id=' . $appt['id'] . '" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a> ';
            }
            if (!$isLockedCancelled) {
                $html .= '<button type="button" class="btn-action btn-delete" title="Delete" onclick="openDeleteModal(' . $appt['id'] . ')"><i class="fa-solid fa-trash"></i></button>';
            }
            $html .= '</td>';
            $html .= '</tr>';
        }
    } else {
        $html = '<tr><td colspan="7" style="text-align: center; padding: 30px; color: #888;">No appointments found.</td></tr>';
    }
    
    // Build Pagination HTML
    $paginationHtml = '';
    if ($totalPages > 1) {
        $paginationHtml .= '<div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">';
        if ($page > 1) $paginationHtml .= '<button class="pagination-btn" onclick="loadPage(' . ($page - 1) . ')">&laquo; Prev</button>';
        for ($i = 1; $i <= $totalPages; $i++) {
            $activeClass = ($i == $page) ? 'active' : '';
            $style = ($i == $page) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : '';
            $paginationHtml .= '<button class="pagination-btn ' . $activeClass . '" style="' . $style . '" onclick="loadPage(' . $i . ')">' . $i . '</button>';
        }
        if ($page < $totalPages) $paginationHtml .= '<button class="pagination-btn" onclick="loadPage(' . ($page + 1) . ')">Next &raquo;</button>';
        $paginationHtml .= '</div>';
    }
    
    echo json_encode(['html' => $html, 'pagination' => $paginationHtml]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bookings - Blade & Trim Admin</title>
    
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
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
        
        <?php $page_title = 'Bookings'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Actions Toolbar -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                    <div class="search-box">
                        <input type="text" id="searchInput" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search client..." style="padding: 10px; border: 1px solid #ddd; border-radius: 5px; width: 250px;">
                    </div>
                    <select id="statusFilter" name="status" style="padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="completed" <?php echo $status_filter == 'completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                    <input type="date" id="startDate" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 5px;" title="Start Date">
                    <span style="color: #666;">to</span>
                    <input type="date" id="endDate" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" style="padding: 10px; border: 1px solid #ddd; border-radius: 5px;" title="End Date">
                </div>
                <button onclick="openModal()" style="background-color: var(--accent-gold); border: none; padding: 10px 20px; color: #000; font-weight: bold; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> New Appointment
                </button>
            </div>

            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Appointments Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Client</th>
                            <th>Service</th>
                            <th>Barber/Staff</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="appointmentsTableBody">
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appt): ?>
                                <?php 
                                    $date = new DateTime($appt['appointment_date']);
                                    $statusClass = 'status-' . strtolower($appt['status']);
                                    
                                    // Check for stale pending
                                    $createdAt = new DateTime($appt['created_at']);
                                    $diff = time() - $createdAt->getTimestamp();
                                    $isStale = ($appt['status'] === 'pending' && $diff > 300);
                                    
                                    // Check for locked cancelled (cancelled > 10 mins ago)
                                    $updatedAt = new DateTime($appt['updated_at'] ?? $appt['created_at']);
                                    $diffUpdate = time() - $updatedAt->getTimestamp();
                                    $isLockedCancelled = ($appt['status'] === 'cancelled' && $diffUpdate > 600);
                                    
                                    // Check for missing payment on completed bookings
                                    $paymentMissing = ($appt['status'] === 'completed' && (empty($appt['mpesa_code']) || $appt['amount_paid'] <= 0));
                                ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600;"><?php echo $date->format('M j, Y'); ?></div>
                                        <div style="font-size: 13px; color: #888;"><?php echo $date->format('g:i A'); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500;"><?php echo htmlspecialchars($appt['client_name']); ?></div>
                                        <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($appt['client_phone']); ?></div>
                                    </td>
                                    <td><?php echo htmlspecialchars($appt['service_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['staff_name']); ?></td>
                                    <td>$<?php echo number_format($appt['price'], 2); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($appt['status']); ?>
                                        </span>
                                        <?php if ($isStale): ?>
                                            <div style="color: #e74c3c; font-size: 11px; margin-top: 3px; font-weight: bold;"><i class="fa-solid fa-clock"></i> Overdue</div>
                                        <?php endif; ?>
                                        <?php if ($paymentMissing): ?>
                                            <div style="color: #e74c3c; font-size: 11px; margin-top: 3px; font-weight: bold;"><i class="fa-solid fa-triangle-exclamation"></i> No Payment Code</div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (($appt['status'] !== 'completed' && $appt['status'] !== 'cancelled') || $paymentMissing): ?>
                                            <?php 
                                                $btnColor = $paymentMissing ? "#f39c12" : "#2ecc71";
                                                $btnTitle = $paymentMissing ? "Add Payment Code" : "Complete & Pay";
                                                $btnIcon = $paymentMissing ? "fa-file-invoice-dollar" : "fa-check-circle";
                                            ?>
                                            <button type="button" class="btn-action" style="color: <?php echo $btnColor; ?>;" title="<?php echo $btnTitle; ?>" onclick="openPaymentModal(<?php echo $appt['id']; ?>, <?php echo $appt['price']; ?>)"><i class="fa-solid <?php echo $btnIcon; ?>"></i></button>
                                        <?php endif; ?>
                                        <?php if ($appt['status'] !== 'completed' && $appt['status'] !== 'cancelled'): ?>
                                            <button type="button" class="btn-action" style="color: #e67e22;" title="Cancel Booking" onclick="openCancelModal(<?php echo $appt['id']; ?>)"><i class="fa-solid fa-ban"></i></button>
                                        <?php endif; ?>
                                        <?php if ($appt['status'] === 'completed'): ?>
                                            <a href="generate_receipt.php?id=<?php echo $appt['id']; ?>" target="_blank" class="btn-action" style="color: #3498db;" title="Print Receipt"><i class="fa-solid fa-print"></i></a>
                                        <?php endif; ?>
                                        <?php if ($appt['status'] !== 'completed' && !$isLockedCancelled): ?>
                                            <a href="edit_appointment.php?id=<?php echo $appt['id']; ?>" class="btn-action btn-edit" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                        <?php endif; ?>
                                        <?php if (!$isLockedCancelled): ?>
                                            <button type="button" class="btn-action btn-delete" title="Delete" onclick="openDeleteModal(<?php echo $appt['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 30px; color: #888;">No appointments found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination Container -->
            <div id="paginationContainer" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">
                <?php if ($totalPages > 1): ?>
                    <?php if ($page > 1): ?>
                        <button class="pagination-btn" onclick="loadPage(<?php echo $page - 1; ?>)">&laquo; Prev</button>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <?php $style = ($i == $page) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : ''; ?>
                        <button class="pagination-btn" style="<?php echo $style; ?>" onclick="loadPage(<?php echo $i; ?>)"><?php echo $i; ?></button>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <button class="pagination-btn" onclick="loadPage(<?php echo $page + 1; ?>)">Next &raquo;</button>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- New Appointment Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px;">New Appointment</h2>
            
            <form method="POST" action="appointments.php">
                <input type="hidden" name="create_appointment" value="1">
                
                <h4 style="margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Client Info</h4>
                <div class="form-group">
                    <label>Name</label>
                    <input type="text" name="client_name" class="form-control" value="<?php echo htmlspecialchars($form_data['client_name'] ?? ''); ?>" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="client_email" class="form-control" value="<?php echo htmlspecialchars($form_data['client_email'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" name="client_phone" class="form-control" value="<?php echo htmlspecialchars($form_data['client_phone'] ?? ''); ?>" required>
                    </div>
                </div>

                <h4 style="margin-bottom: 10px; margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 5px;">Booking Details</h4>
                <div class="form-group">
                    <label>Service</label>
                    <select name="service_id" class="form-control" required>
                        <option value="">Select Service</option>
                        <?php foreach ($services as $svc): ?>
                            <option value="<?php echo $svc['id']; ?>" <?php echo (isset($form_data['service_id']) && $form_data['service_id'] == $svc['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($svc['name']); ?> ($<?php echo $svc['price']; ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Staff</label>
                    <select name="staff_id" class="form-control" required>
                        <option value="">Select Staff</option>
                        <?php foreach ($staff_members as $staff): ?>
                            <option value="<?php echo $staff['id']; ?>" <?php echo (isset($form_data['staff_id']) && $form_data['staff_id'] == $staff['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($staff['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="appointment_date" class="form-control" value="<?php echo htmlspecialchars($form_data['appointment_date'] ?? ''); ?>" required>
                </div>
                
                <button type="submit" class="btn-submit">Create Booking</button>
            </form>
        </div>
    </div>

    <!-- Cancel Appointment Modal -->
    <div id="cancelModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close-modal" onclick="closeCancelModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px; color: #e67e22;"><i class="fa-solid fa-ban"></i> Cancel Booking</h2>
            <p style="color: #666; margin-bottom: 20px;">Please provide a reason for cancellation.</p>
            
            <form method="POST" action="appointments.php">
                <input type="hidden" name="cancel_appointment" value="1">
                <input type="hidden" name="appointment_id" id="cancel_appointment_id">
                
                <div class="form-group">
                    <label>Reason</label>
                    <textarea name="cancellation_reason" class="form-control" rows="3" placeholder="e.g. Client requested, Staff unavailable..." required></textarea>
                </div>
                
                <button type="submit" class="btn-submit" style="background-color: #e67e22; border-color: #e67e22;">Confirm Cancellation</button>
            </form>
        </div>
    </div>

    <!-- Payment/Completion Modal -->
    <div id="paymentModal" class="modal">
        <div class="modal-content" style="max-width: 400px;">
            <span class="close-modal" onclick="closePaymentModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px; color: #2ecc71;"><i class="fa-solid fa-check-circle"></i> Complete Booking</h2>
            <p style="color: #666; margin-bottom: 20px;">Enter payment details to complete this appointment.</p>
            
            <form method="POST" action="appointments.php">
                <input type="hidden" name="complete_appointment" value="1">
                <input type="hidden" name="appointment_id" id="payment_appointment_id">
                
                <div class="form-group">
                    <label>Amount Paid ($)</label>
                    <input type="number" step="0.01" name="amount_paid" id="amount_paid" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>M-Pesa Code</label>
                    <input type="text" name="mpesa_code" id="mpesa_code" class="form-control" placeholder="e.g. QH123456" required>
                </div>
                
                <button type="submit" class="btn-submit" style="background-color: #2ecc71; border-color: #2ecc71;">Confirm Payment</button>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="close-modal" onclick="closeDeleteModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-exclamation" style="font-size: 48px; color: #e74c3c;"></i>
            </div>
            <h3 style="margin-top: 0; margin-bottom: 10px;">Delete Appointment?</h3>
            <p style="color: #666; margin-bottom: 25px;">Are you sure you want to delete this appointment? This action cannot be undone.</p>
            
            <form method="POST" action="delete_appointment.php">
                <input type="hidden" name="id" id="delete_appointment_id">
                <div style="display: flex; gap: 10px; justify-content: center;">
                    <button type="button" onclick="closeDeleteModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: #fff; border-radius: 5px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-submit" style="width: auto; margin-top: 0; background-color: #e74c3c; border-color: #e74c3c;">Delete</button>
                </div>
            </form>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        const modal = document.getElementById('appointmentModal');
        const paymentModal = document.getElementById('paymentModal');
        const cancelModal = document.getElementById('cancelModal');
        const deleteModal = document.getElementById('deleteModal');
        const deleteInput = document.getElementById('delete_appointment_id');
        
        function openModal() {
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        function openPaymentModal(id, price) {
            document.getElementById('payment_appointment_id').value = id;
            document.getElementById('amount_paid').value = price;
            paymentModal.classList.add('show');
        }

        function closePaymentModal() {
            paymentModal.classList.remove('show');
        }

        function openCancelModal(id) {
            document.getElementById('cancel_appointment_id').value = id;
            cancelModal.classList.add('show');
        }

        function closeCancelModal() {
            cancelModal.classList.remove('show');
        }

        function openDeleteModal(id) {
            deleteInput.value = id;
            deleteModal.classList.add('show');
        }

        function closeDeleteModal() {
            deleteModal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == paymentModal) {
                closePaymentModal();
            }
            if (event.target == cancelModal) {
                closeCancelModal();
            }
            if (event.target == deleteModal) {
                closeDeleteModal();
            }
        }

        <?php if (!empty($form_data)): ?>
            openModal();
        <?php endif; ?>
    </script>
    <script>
        // Automatic Filtering Logic
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const tableBody = document.getElementById('appointmentsTableBody');
        const paginationContainer = document.getElementById('paginationContainer');

        function loadPage(page = 1) {
            const search = searchInput.value;
            const status = statusFilter.value;
            const startDate = startDateInput.value;
            const endDate = endDateInput.value;

            fetch(`appointments.php?ajax=1&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}&start_date=${encodeURIComponent(startDate)}&end_date=${encodeURIComponent(endDate)}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.html;
                    paginationContainer.innerHTML = data.pagination;
                })
                .catch(error => console.error('Error:', error));
        }

        searchInput.addEventListener('input', () => loadPage(1));
        statusFilter.addEventListener('change', () => loadPage(1));
        startDateInput.addEventListener('change', () => loadPage(1));
        endDateInput.addEventListener('change', () => loadPage(1));
    </script>
</body>
</html>
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

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);

// Retrieve form data if available (for repopulating form on error)
$form_data = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$id = $_GET['id'] ?? null;

if (!$id) {
    header("Location: appointments.php");
    exit;
}

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $service_id = $_POST['service_id'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';
    $status = $_POST['status'] ?? 'pending';
    $notes = trim($_POST['notes'] ?? '');
    $mpesa_code = trim($_POST['mpesa_code'] ?? '');
    $amount_paid = $_POST['amount_paid'] ?? 0;

    if (empty($client_name) || empty($service_id) || empty($staff_id) || empty($appointment_date)) {
        $_SESSION['error'] = "Please fill in all required fields.";
        $_SESSION['form_data'] = $_POST;
    } elseif ($status === 'completed' && (empty($mpesa_code) || empty($amount_paid) || $amount_paid <= 0)) {
        $_SESSION['error'] = "To mark as Completed, Payment Amount and M-Pesa Code are required.";
        $_SESSION['form_data'] = $_POST;
    } else {
        try {
            // Check if the staff member is already booked within 1 hour (excluding current appointment)
            $checkSql = "SELECT COUNT(*) FROM appointments 
                         WHERE staff_id = ? 
                         AND status != 'cancelled' 
                         AND ABS(TIMESTAMPDIFF(MINUTE, appointment_date, ?)) < 60
                         AND id != ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$staff_id, $appointment_date, $id]);

            if ($checkStmt->fetchColumn() > 0) {
                $_SESSION['error'] = "This staff member is already booked within the hour. Please select a different staff member.";
                $_SESSION['form_data'] = $_POST;
            } else {
                $sql = "UPDATE appointments SET 
                        client_name = ?, 
                        client_email = ?, 
                        client_phone = ?, 
                        service_id = ?, 
                        staff_id = ?, 
                        appointment_date = ?, 
                        status = ?, 
                        notes = ?,
                        mpesa_code = ?,
                        amount_paid = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    $client_name, 
                    $client_email, 
                    $client_phone, 
                    $service_id, 
                    $staff_id, 
                    $appointment_date, 
                    $status, 
                    $notes, 
                    $mpesa_code,
                    $amount_paid,
                    $id
                ]);
                $_SESSION['message'] = "Appointment updated successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
            $_SESSION['form_data'] = $_POST;
        }
    }
    // Redirect
    header("Location: edit_appointment.php?id=" . $id);
    exit;
}

// Fetch Data
try {
    // Get Appointment
    $stmt = $pdo->prepare("SELECT * FROM appointments WHERE id = ?");
    $stmt->execute([$id]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        die("Appointment not found.");
    }

    // Override with form data if available (preserves inputs on error)
    if (!empty($form_data)) {
        $appointment = array_merge($appointment, $form_data);
    }

    // Get Services
    $stmt = $pdo->query("SELECT * FROM services ORDER BY name ASC");
    $services = $stmt->fetchAll();

    // Get Staff
    $stmt = $pdo->query("SELECT * FROM staff ORDER BY name ASC");
    $staff_members = $stmt->fetchAll();

} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Booking - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Edit Booking #' . htmlspecialchars($id); ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <div style="margin-bottom: 20px;">
                <a href="appointments.php" style="text-decoration: none; color: var(--text-secondary);"><i class="fa-solid fa-arrow-left"></i> Back to Bookings</a>
            </div>

            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div style="background: #fff; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); max-width: 800px;">
                <form method="POST" action="edit_appointment.php?id=<?php echo htmlspecialchars($id); ?>">
                    
                    <h3 style="margin-top: 0; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Client Details</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Client Name</label>
                            <input type="text" name="client_name" class="form-control" value="<?php echo htmlspecialchars($appointment['client_name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" name="client_phone" class="form-control" value="<?php echo htmlspecialchars($appointment['client_phone'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="client_email" class="form-control" value="<?php echo htmlspecialchars($appointment['client_email'] ?? ''); ?>" required>
                    </div>

                    <h3 style="margin-top: 20px; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Appointment Details</h3>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Service</label>
                            <select name="service_id" class="form-control" required>
                                <?php foreach ($services as $svc): ?>
                                    <option value="<?php echo $svc['id']; ?>" <?php echo $svc['id'] == $appointment['service_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($svc['name'] ?? ''); ?> ($<?php echo number_format($svc['price'], 2); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Staff Member</label>
                            <select name="staff_id" class="form-control" required>
                                <?php foreach ($staff_members as $staff): ?>
                                    <option value="<?php echo $staff['id']; ?>" <?php echo $staff['id'] == $appointment['staff_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($staff['name'] ?? ''); ?> (<?php echo htmlspecialchars($staff['role'] ?? ''); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form-group">
                            <label>Date & Time</label>
                            <!-- Format for datetime-local input is YYYY-MM-DDTHH:MM -->
                            <input type="datetime-local" name="appointment_date" class="form-control" value="<?php echo date('Y-m-d\TH:i', strtotime($appointment['appointment_date'])); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="pending" <?php echo $appointment['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $appointment['status'] == 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="completed" <?php echo $appointment['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $appointment['status'] == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                    </div>
                
                <h3 style="margin-top: 20px; margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px;">Payment Details</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form-group">
                        <label>Amount Paid ($)</label>
                        <input type="number" step="0.01" name="amount_paid" class="form-control" value="<?php echo htmlspecialchars($appointment['amount_paid'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label>M-Pesa Code</label>
                        <input type="text" name="mpesa_code" class="form-control" value="<?php echo htmlspecialchars($appointment['mpesa_code'] ?? ''); ?>" placeholder="Required if Completed">
                    </div>
                </div>

                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($appointment['notes'] ?? ''); ?></textarea>
                    </div>

                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-submit">Update Booking</button>
                    </div>
                </form>
            </div>

        </div>
    </main>

    <script src="sidebar.js"></script>
</body>
</html>
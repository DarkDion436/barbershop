<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

$id = $_GET['id'] ?? null;

if (!$id) {
    die("Invalid Receipt ID.");
}

try {
    // Fetch Appointment Details
    $stmt = $pdo->prepare("
        SELECT a.*, s.name AS service_name, s.price, st.name AS staff_name 
        FROM appointments a 
        JOIN services s ON a.service_id = s.id 
        JOIN staff st ON a.staff_id = st.id 
        WHERE a.id = ?
    ");
    $stmt->execute([$id]);
    $appt = $stmt->fetch();

    if (!$appt) {
        die("Appointment not found.");
    }

    // Fetch Shop Settings
    $settings_file = 'shop_settings.json';
    if (file_exists($settings_file)) {
        $shop_settings = json_decode(file_get_contents($settings_file), true);
    } else {
        $shop_settings = [
            'shop_name' => 'Blade & Trim',
            'address' => '123 Barber Street, Cityville',
            'phone' => '(555) 123-4567',
            'email' => 'contact@bladeandtrim.com'
        ];
    }

} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?php echo $appt['id']; ?></title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            background-color: #f4f4f4;
            padding: 20px;
        }
        .receipt {
            max-width: 300px;
            margin: 0 auto;
            background: #fff;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-bottom: 1px dashed #000; margin: 10px 0; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 5px; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2em; margin-top: 10px; }
        .footer { font-size: 12px; margin-top: 20px; text-align: center; color: #666; }
        
        @media print {
            body { background: none; padding: 0; }
            .receipt { box-shadow: none; width: 100%; max-width: 100%; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>

    <div class="receipt">
        <div class="text-center">
            <h2 style="margin: 0;"><?php echo htmlspecialchars($shop_settings['shop_name']); ?></h2>
            <p style="margin: 5px 0; font-size: 12px;"><?php echo htmlspecialchars($shop_settings['address']); ?></p>
            <p style="margin: 0; font-size: 12px;"><?php echo htmlspecialchars($shop_settings['phone']); ?></p>
        </div>

        <div class="line"></div>

        <div class="item-row">
            <span>Receipt #:</span>
            <span><?php echo str_pad($appt['id'], 6, '0', STR_PAD_LEFT); ?></span>
        </div>
        <div class="item-row">
            <span>Date:</span>
            <span><?php echo date('d/m/Y H:i', strtotime($appt['appointment_date'])); ?></span>
        </div>
        <div class="item-row">
            <span>Client:</span>
            <span><?php echo htmlspecialchars($appt['client_name']); ?></span>
        </div>
        <div class="item-row">
            <span>Staff:</span>
            <span><?php echo htmlspecialchars($appt['staff_name']); ?></span>
        </div>

        <div class="line"></div>

        <div class="item-row bold">
            <span>Service</span>
            <span>Price</span>
        </div>
        <div class="item-row">
            <span><?php echo htmlspecialchars($appt['service_name']); ?></span>
            <span>$<?php echo number_format($appt['price'], 2); ?></span>
        </div>

        <div class="line"></div>

        <div class="total-row">
            <span>TOTAL PAID:</span>
            <span>$<?php echo number_format($appt['amount_paid'] ?? $appt['price'], 2); ?></span>
        </div>
        
        <?php if (!empty($appt['mpesa_code'])): ?>
        <div class="item-row" style="margin-top: 5px; font-size: 12px;">
            <span>M-Pesa Code:</span>
            <span><?php echo htmlspecialchars($appt['mpesa_code'] ?? ''); ?></span>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Thank you for your business!</p>
        </div>

        <div class="text-center no-print" style="margin-top: 20px;">
            <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer; background: #333; color: #fff; border: none; border-radius: 4px;">Print Receipt</button>
        </div>
    </div>

</body>
</html>
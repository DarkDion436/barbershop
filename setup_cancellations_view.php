<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'db_connect.php';

try {
    // Create a View for Cancelled Bookings based on website logic
    $sql = "CREATE OR REPLACE VIEW view_cancelled_bookings AS
            SELECT 
                a.id AS appointment_id,
                a.client_name,
                a.client_email,
                a.client_phone,
                s.name AS service_name,
                s.price AS service_price,
                st.name AS staff_name,
                a.appointment_date AS scheduled_date,
                COALESCE(a.updated_at, a.appointment_date) AS cancellation_date,
                a.notes AS cancellation_reason
            FROM appointments a
            JOIN services s ON a.service_id = s.id
            JOIN staff st ON a.staff_id = st.id
            WHERE a.status = 'cancelled'";
            
    $pdo->exec($sql);
    
    echo "<h3>SQL View 'view_cancelled_bookings' created successfully!</h3>";
    echo "<p>You can now query cancelled bookings directly using: <code>SELECT * FROM view_cancelled_bookings</code></p>";
    echo "<p><a href='reports.php'>Back to Reports</a></p>";

} catch (PDOException $e) {
    echo "Error creating view: " . $e->getMessage();
}
?>
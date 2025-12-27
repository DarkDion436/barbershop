<?php
// index.php
session_start();

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'db_connect.php';

$booking_message = $_SESSION['booking_message'] ?? '';
$booking_error = $_SESSION['booking_error'] ?? '';

// Clear session messages after displaying
unset($_SESSION['booking_message']);
unset($_SESSION['booking_error']);

// Flag to reopen modal if there was an error or success
$reopen_modal = !empty($booking_message) || !empty($booking_error);

// Handle Booking Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $client_name = trim($_POST['client_name'] ?? '');
    $client_email = trim($_POST['client_email'] ?? '');
    $client_phone = trim($_POST['client_phone'] ?? '');
    $service_id = $_POST['service_id'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $appointment_date = $_POST['appointment_date'] ?? '';

    if (empty($client_name) || empty($client_email) || empty($client_phone) || empty($service_id) || empty($staff_id) || empty($appointment_date)) {
        $_SESSION['booking_error'] = "All fields are required.";
    } elseif (!filter_var($client_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['booking_error'] = "Please enter a valid email address.";
    } else {
        try {
            // Check if the staff member is already booked within 1 hour (same hour)
            $checkSql = "SELECT COUNT(*) FROM appointments 
                         WHERE staff_id = ? 
                         AND status != 'cancelled' 
                         AND ABS(TIMESTAMPDIFF(MINUTE, appointment_date, ?)) < 60";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$staff_id, $appointment_date]);
            $is_conflict = $checkStmt->fetchColumn() > 0;

            // 1. Insert the appointment (Accepted regardless of conflict)
            $sql = "INSERT INTO appointments (client_name, client_email, client_phone, service_id, staff_id, appointment_date, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$client_name, $client_email, $client_phone, $service_id, $staff_id, $appointment_date]);
            
            // 2. Create notification based on conflict status
            if ($is_conflict) {
                $notification_message = "CONFLICT ALERT: Booking for " . htmlspecialchars($client_name) . " overlaps with existing appointment. Please reassign staff.";
                $notifStmt = $pdo->prepare("INSERT INTO notifications (type, message) VALUES ('warning', ?)");
                $notifStmt->execute([$notification_message]);
                $_SESSION['booking_message'] = "Your booking request has been sent. Note: The selected time is busy, we will contact you to confirm details.";
            } else {
                $notification_message = "New booking from " . htmlspecialchars($client_name) . " for " . date('M j, Y g:i A', strtotime($appointment_date));
                $notifStmt = $pdo->prepare("INSERT INTO notifications (type, message) VALUES ('info', ?)");
                $notifStmt->execute([$notification_message]);
                $_SESSION['booking_message'] = "Thank you! Your booking request has been sent. We will contact you shortly to confirm.";
            }

        } catch (PDOException $e) {
            $_SESSION['booking_error'] = "Sorry, there was an error with your booking. Please try again later.";
            // Log the detailed error for the admin
            error_log("Booking Error: " . $e->getMessage());
        }
    }
    // Redirect to prevent resubmission
    header("Location: index.php");
    exit;
}


// Fetch data for the page
try {
    // Fetch active services
    $services = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY category, name")->fetchAll();

    // Fetch active staff
    $staff = $pdo->query("SELECT * FROM staff WHERE is_active = 1 ORDER BY name")->fetchAll();

    // Fetch shop settings
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
    // If DB fails, we can still show a basic page
    $services = [];
    $staff = [];
    $shop_settings = [];
    // You might want to log this error
    error_log("Landing Page DB Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($shop_settings['shop_name'] ?? 'Blade & Trim'); ?> - Premium Barber & Spa</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style.css"> <!-- New public stylesheet -->
</head>
<body>

    <!-- Header -->
    <header class="main-header">
        <div class="container">
            <a href="#hero" class="brand-logo">
                Blade<span class="gold">&</span>Trim
            </a>
            <nav class="main-nav">
                <ul>
                    <li><a href="#services">Services</a></li>
                    <li><a href="#about">About</a></li>
                    <li><a href="#contact">Contact</a></li>
                    <li><a href="login.php">Admin Login</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section id="hero">
        <div class="hero-content">
            <h1>Experience the Art of Grooming</h1>
            <p>Precision cuts, classic shaves, and modern spa treatments.</p>
            <a href="javascript:void(0)" class="btn" onclick="openBookingModal()">Book Now</a>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" class="section">
        <div class="container">
            <h2 class="section-title">Our Services</h2>
            <div class="services-grid">
                <?php if (empty($services)): ?>
                    <p style="text-align: center; grid-column: 1 / -1;">Services information is currently unavailable.</p>
                <?php else: ?>
                    <?php foreach ($services as $service): ?>
                        <div class="service-card">
                            <h3><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p><?php echo htmlspecialchars($service['description'] ?? ''); ?></p>
                            <div class="service-price">$<?php echo number_format($service['price'], 2); ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" class="section">
        <div class="container">
            <h2 class="section-title">About Us</h2>
            <p>
                Founded on the principles of classic barbering and modern luxury, Blade & Trim offers a premier grooming experience for the discerning gentleman. Our master barbers and skilled estheticians are dedicated to their craft, providing exceptional service in a relaxed and sophisticated atmosphere. From precision haircuts and traditional hot towel shaves to rejuvenating spa treatments, we are committed to helping you look and feel your absolute best.
            </p>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" class="section">
        <div class="container">
            <h2 class="section-title">Get In Touch</h2>
            <div class="contact-grid">
                <div class="contact-info">
                    <h3>Contact Details</h3>
                    <p><i class="fa-solid fa-location-dot"></i> <?php echo htmlspecialchars($shop_settings['address'] ?? 'N/A'); ?></p>
                    <p><i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($shop_settings['phone'] ?? 'N/A'); ?></p>
                    <p><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($shop_settings['email'] ?? 'N/A'); ?></p>
                    <h3>Opening Hours</h3>
                    <p>Mon - Fri: 9:00 AM - 7:00 PM</p>
                    <p>Saturday: 9:00 AM - 5:00 PM</p>
                    <p>Sunday: Closed</p>
                </div>
                <div class="contact-form">
                    <h3>Send Us a Message</h3>
                    <form action="">
                        <div class="form-group">
                            <input type="text" placeholder="Your Name">
                        </div>
                        <div class="form-group">
                            <input type="email" placeholder="Your Email">
                        </div>
                        <div class="form-group">
                            <textarea rows="5" placeholder="Your Message"></textarea>
                        </div>
                        <button type="submit" class="btn">Send Message</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="main-footer">
        <div class="container">
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook-f"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
            </div>
            <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($shop_settings['shop_name'] ?? 'Blade & Trim'); ?>. All Rights Reserved.</p>
        </div>
    </footer>

    <!-- Booking Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeBookingModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px;">Book an Appointment</h2>

            <?php if ($booking_message): ?>
                <div style="background-color: rgba(46, 204, 113, 0.1); color: #2ecc71; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                    <?php echo $booking_message; ?>
                </div>
            <?php elseif ($booking_error): ?>
                 <div style="background-color: rgba(255, 77, 77, 0.1); color: #ff4d4d; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">
                    <?php echo $booking_error; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="index.php">
                <input type="hidden" name="book_appointment" value="1">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group" style="grid-column: span 2;">
                        <label>Full Name</label>
                        <input type="text" name="client_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="client_email" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="client_phone" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Service</label>
                    <select name="service_id" class="form-control" required>
                        <option value="">-- Select a Service --</option>
                        <?php foreach ($services as $service): ?>
                            <option value="<?php echo $service['id']; ?>"><?php echo htmlspecialchars($service['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Preferred Staff</label>
                    <select name="staff_id" class="form-control" required>
                        <option value="">-- Select Staff --</option>
                        <?php foreach ($staff as $s): ?>
                            <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date & Time</label>
                    <input type="datetime-local" name="appointment_date" class="form-control" required>
                </div>
                
                <button type="submit" class="btn">Request Booking</button>
            </form>
        </div>
    </div>

    <script>
        const bookingModal = document.getElementById('bookingModal');

        function openBookingModal() {
            bookingModal.classList.add('show');
        }

        function closeBookingModal() {
            bookingModal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == bookingModal) {
                closeBookingModal();
            }
        }

        // If there was a form submission, keep the modal open to show the message
        <?php if ($reopen_modal): ?>
        document.addEventListener('DOMContentLoaded', function() {
            openBookingModal();
        });
        <?php endif; ?>
    </script>

</body>
</html>
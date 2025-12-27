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

// Mock Session for Admin ID (Default to 1 if not set, for demonstration)
$admin_id = $_SESSION['admin_id'];

$message = $_SESSION['message'] ?? '';
$error = $_SESSION['error'] ?? '';
unset($_SESSION['message']);
unset($_SESSION['error']);

// Handle Add Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $new_username = trim($_POST['new_username'] ?? '');
    $new_email = trim($_POST['new_email'] ?? '');
    $new_fullname = trim($_POST['new_fullname'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if (empty($new_username) || empty($new_email) || empty($new_password)) {
        $_SESSION['error'] = "Username, Email, and Password are required for new admin.";
    } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } else {
        try {
            // Check if username or email exists
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE username = ? OR email = ?");
            $stmt->execute([$new_username, $new_email]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "Username or Email already exists.";
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, email, password, full_name) VALUES (?, ?, ?, ?)");
                $stmt->execute([$new_username, $new_email, $hashed_password, $new_fullname]);
                $_SESSION['message'] = "New admin added successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: settings.php");
    exit;
}

// Handle Edit Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_admin'])) {
    $edit_id = $_POST['edit_id'] ?? '';
    $edit_username = trim($_POST['edit_username'] ?? '');
    $edit_email = trim($_POST['edit_email'] ?? '');
    $edit_fullname = trim($_POST['edit_fullname'] ?? '');

    if (empty($edit_id) || empty($edit_username) || empty($edit_email)) {
        $_SESSION['error'] = "Username and Email are required.";
    } elseif (!filter_var($edit_email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['error'] = "Invalid email format.";
    } else {
        try {
            // Check if username or email exists for OTHER users
            $stmt = $pdo->prepare("SELECT id FROM admins WHERE (username = ? OR email = ?) AND id != ?");
            $stmt->execute([$edit_username, $edit_email, $edit_id]);
            if ($stmt->rowCount() > 0) {
                $_SESSION['error'] = "Username or Email already taken by another admin.";
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET username = ?, email = ?, full_name = ? WHERE id = ?");
                $stmt->execute([$edit_username, $edit_email, $edit_fullname, $edit_id]);
                $_SESSION['message'] = "Admin details updated successfully.";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: settings.php");
    exit;
}

// Check for deletion messages
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Admin account deleted successfully.";
}
if (isset($_GET['msg']) && $_GET['msg'] === 'restored') {
    $message = "Database restored successfully.";
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
}

// Fetch Admins List
try {
    $stmt = $pdo->query("SELECT * FROM admins ORDER BY id ASC");
    $adminList = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching admins: " . $e->getMessage();
}

// --- Logic: Shop Settings (JSON Storage) ---
$settings_file = 'shop_settings.json';
$default_settings = [
    'shop_name' => 'Blade & Trim',
    'address' => '123 Barber Street, Cityville',
    'phone' => '(555) 123-4567',
    'email' => 'contact@bladeandtrim.com'
];

// Load existing settings or defaults
if (file_exists($settings_file)) {
    $current_settings = json_decode(file_get_contents($settings_file), true);
    $shop_settings = array_merge($default_settings, is_array($current_settings) ? $current_settings : []);
} else {
    $shop_settings = $default_settings;
}

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. Update Shop Settings
    if (isset($_POST['update_shop'])) {
        $new_settings = [
            'shop_name' => $_POST['shop_name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        
        if (file_put_contents($settings_file, json_encode($new_settings, JSON_PRETTY_PRINT))) {
            $shop_settings = $new_settings;
            $_SESSION['message'] = "Shop details updated successfully.";
        } else {
            $_SESSION['error'] = "Failed to save shop settings. Check file permissions.";
        }
    }

    // 2. Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $_SESSION['error'] = "All password fields are required.";
        } elseif ($new_password !== $confirm_password) {
            $_SESSION['error'] = "New passwords do not match.";
        } else {
            try {
                // Fetch current hash
                $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
                $stmt->execute([$admin_id]);
                $admin = $stmt->fetch();

                if ($admin && password_verify($current_password, $admin['password'])) {
                    // Update to new hashed password
                    $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                    $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                    $updateStmt->execute([$new_hash, $admin_id]);
                    $_SESSION['message'] = "Password changed successfully.";
                } else {
                    $_SESSION['error'] = "Incorrect current password.";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        }
    }
    // Redirect
    header("Location: settings.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Settings'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 30px;">
                
                <!-- Shop Details Card -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <i class="fa-solid fa-store" style="color: var(--accent-gold); margin-right: 10px;"></i> Shop Details
                    </h3>
                    <form method="POST" action="settings.php">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group">
                                <label>Shop Name</label>
                                <input type="text" name="shop_name" class="form-control" value="<?php echo htmlspecialchars($shop_settings['shop_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Address</label>
                                <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($shop_settings['address']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($shop_settings['phone']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($shop_settings['email']); ?>">
                            </div>
                        </div>
                        <button type="submit" name="update_shop" class="btn-submit">Save Shop Details</button>
                    </form>
                </div>
                
                <!-- Database Backup Card -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); height: fit-content;">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <i class="fa-solid fa-database" style="color: var(--accent-gold); margin-right: 10px;"></i> Database Backup
                    </h3>
                    <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Download a full SQL dump of the database for safekeeping.</p>
                    <a href="backup_database.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none;">Download Backup</a>
                </div>

                <!-- Database Restore Card -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); height: fit-content;">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <i class="fa-solid fa-upload" style="color: var(--accent-gold); margin-right: 10px;"></i> Restore Database
                    </h3>
                    <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Upload a SQL file to restore the database. <strong>Warning: This will overwrite existing data.</strong></p>
                    <form method="POST" action="restore_database.php" enctype="multipart/form-data" onsubmit="return confirm('Are you sure you want to restore the database? This will overwrite all current data.');">
                        <input type="file" name="backup_file" class="form-control" accept=".sql" required style="margin-bottom: 15px;">
                        <button type="submit" class="btn-submit" style="background-color: #e74c3c;">Restore Database</button>
                    </form>
                </div>

                <!-- Security Card -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); height: fit-content;">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <i class="fa-solid fa-lock" style="color: var(--accent-gold); margin-right: 10px;"></i> Change Password
                    </h3>
                    <form method="POST" action="settings.php">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div class="form-group" style="grid-column: span 2;">
                                <label>Current Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="current_password" class="form-control" required>
                                    <i class="fa-solid fa-eye toggle-password"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="new_password" class="form-control" required>
                                    <i class="fa-solid fa-eye toggle-password"></i>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Confirm New Password</label>
                                <div class="password-wrapper">
                                    <input type="password" name="confirm_password" class="form-control" required>
                                    <i class="fa-solid fa-eye toggle-password"></i>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="update_password" class="btn-submit">Update Password</button>
                    </form>
                </div>

                <!-- Manage Admins Card -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <i class="fa-solid fa-user-plus" style="color: var(--accent-gold); margin-right: 10px;"></i> Add New Admin
                    </h3>
                    
                    <!-- Add Admin Form -->
                    <div style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #eee;">
                        <h4 style="margin-top: 0; margin-bottom: 15px; font-size: 16px;">Add New Admin</h4>
                        <form method="POST" action="settings.php" id="addAdminForm">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Username</label>
                                    <input type="text" name="new_username" class="form-control" required>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Full Name</label>
                                    <input type="text" name="new_fullname" class="form-control">
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Email</label>
                                    <input type="email" name="new_email" class="form-control" required>
                                </div>
                                <div>
                                    <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Password</label>
                                    <input type="password" name="new_password" class="form-control" required>
                                </div>
                            </div>
                            <input type="hidden" name="add_admin" value="1">
                            <button type="button" onclick="confirmAddAdmin()" class="btn-submit" style="width: auto;">Add Admin</button>
                        </form>
                    </div>
                </div>

                <!-- Admin List Card -->
                <div style="background: #fff; padding: 25px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03);">
                    <h3 style="margin-top: 0; color: #333; font-size: 18px; border-bottom: 1px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">
                        <i class="fa-solid fa-users" style="color: var(--accent-gold); margin-right: 10px;"></i> Admin List
                    </h3>
                    <div class="table-responsive" style="padding: 0; box-shadow: none;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminList as $adminItem): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($adminItem['username']); ?></td>
                                    <td><?php echo htmlspecialchars($adminItem['full_name'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($adminItem['id'] != $_SESSION['admin_id'] && $adminItem['id'] != 1): ?>
                                        <button type="button" class="btn-action btn-edit" onclick='editAdmin(<?php echo json_encode($adminItem); ?>)' title="Edit">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" action="delete_admin.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this admin?');">
                                            <input type="hidden" name="id" value="<?php echo $adminItem['id']; ?>">
                                            <button type="submit" class="btn-action btn-delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                        <?php elseif ($adminItem['id'] == 1): ?>
                                            <span style="font-size: 12px; color: #888;">Main</span>
                                        <?php else: ?>
                                            <span style="font-size: 12px; color: #888;">You</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Confirmation Modal -->
    <div id="confirmModal" class="modal">
        <div class="modal-content" style="max-width: 400px; text-align: center;">
            <span class="close-modal" onclick="closeConfirmModal()">&times;</span>
            <div style="margin-bottom: 20px;">
                <i class="fa-solid fa-circle-question" style="font-size: 48px; color: var(--accent-gold);"></i>
            </div>
            <h3 style="margin-top: 0; margin-bottom: 10px;">Confirm Action</h3>
            <p style="color: #666; margin-bottom: 25px;">Are you sure you want to add this new admin user?</p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button onclick="closeConfirmModal()" style="padding: 10px 20px; border: 1px solid #ddd; background: #fff; border-radius: 5px; cursor: pointer;">Cancel</button>
                <button onclick="submitAddAdmin()" class="btn-submit" style="width: auto; margin-top: 0;">Yes, Add Admin</button>
            </div>
        </div>
    </div>

    <!-- Edit Admin Modal -->
    <div id="editAdminModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeEditModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px;">Edit Admin Details</h2>
            <form method="POST" action="settings.php">
                <input type="hidden" name="edit_admin" value="1">
                <input type="hidden" name="edit_id" id="edit_id">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="edit_username" id="edit_username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="edit_fullname" id="edit_fullname" class="form-control">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                </div>
                <button type="submit" class="btn-submit">Update Admin</button>
            </form>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        document.querySelectorAll('.toggle-password').forEach(icon => {
            icon.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
                input.setAttribute('type', type);
                this.classList.toggle('fa-eye');
                this.classList.toggle('fa-eye-slash');
            });
        });

        const confirmModal = document.getElementById('confirmModal');
        
        function confirmAddAdmin() {
            confirmModal.classList.add('show');
        }

        function closeConfirmModal() {
            confirmModal.classList.remove('show');
        }

        function submitAddAdmin() {
            document.getElementById('addAdminForm').submit();
        }

        const editAdminModal = document.getElementById('editAdminModal');

        function editAdmin(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_username').value = data.username;
            document.getElementById('edit_fullname').value = data.full_name;
            document.getElementById('edit_email').value = data.email;
            editAdminModal.classList.add('show');
        }

        function closeEditModal() {
            editAdminModal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == confirmModal) {
                closeConfirmModal();
            }
            if (event.target == editAdminModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>
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

// Check for deletion messages
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Staff member deleted successfully.";
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $role = $_POST['role'] ?? 'Barber';
    $bio = $_POST['bio'] ?? '';
    $staff_id = $_POST['staff_id'] ?? '';
    $profile_image_path = null;

    // Handle File Upload
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/staff/';
        // Create directory if it doesn't exist
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileTmpPath = $_FILES['profile_image']['tmp_name'];
        $fileName = $_FILES['profile_image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            // Generate unique filename to prevent overwrites
            $newFileName = time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                $profile_image_path = $destPath;
            } else {
                $_SESSION['error'] = "There was an error moving the uploaded file.";
            }
        } else {
            $_SESSION['error'] = "Upload failed. Allowed file types: " . implode(',', $allowedExtensions);
        }
    }

    if (empty($name)) {
        $_SESSION['error'] = "Staff Name is required.";
    } elseif (empty($_SESSION['error'])) {
        try {
            if (!empty($staff_id)) {
                // Update existing staff
                $sql = "UPDATE staff SET name=?, role=?, bio=?";
                $params = [$name, $role, $bio];

                // Only update image if a new one was uploaded
                if ($profile_image_path) {
                    $sql .= ", profile_image=?";
                    $params[] = $profile_image_path;
                }

                $sql .= " WHERE id=?";
                $params[] = $staff_id;

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $_SESSION['message'] = "Staff member updated successfully!";
            } else {
                // Insert new staff
                $sql = "INSERT INTO staff (name, role, bio, profile_image) VALUES (?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $role, $bio, $profile_image_path]);
                $_SESSION['message'] = "New staff member added successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: staff.php");
    exit;
}

// Handle Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = $_POST['id'] ?? '';
    $current_status = $_POST['current_status'] ?? 0;
    $new_status = $current_status ? 0 : 1;

    if (!empty($id)) {
        try {
            $stmt = $pdo->prepare("UPDATE staff SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            $_SESSION['message'] = "Staff status updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: staff.php");
    exit;
}

// Fetch Staff List
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Items per page
$offset = ($page - 1) * $limit;

try {
    // 1. Get Total Count for Pagination
    $countSql = "SELECT COUNT(*) FROM staff WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $countSql .= " AND (name LIKE ? OR role LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // 2. Get Data with Limit/Offset
    $sql = "SELECT * FROM staff WHERE 1=1";
    $params = []; // Reset params for select query
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR role LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    $sql .= " ORDER BY name ASC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $staffMembers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching staff: " . $e->getMessage();
}

// AJAX Handler for Table Search
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $html = '';
    if (!empty($staffMembers)) {
        foreach ($staffMembers as $staff) {
            $html .= '<tr>';
            $html .= '<td>';
            if (!empty($staff['profile_image']) && file_exists($staff['profile_image'])) {
                $html .= '<img src="' . htmlspecialchars($staff['profile_image'] ?? '') . '" alt="Profile" class="staff-avatar">';
            } else {
                $html .= '<div class="staff-initials">' . substr($staff['name'], 0, 1) . '</div>';
            }
            $html .= '</td>';
            $html .= '<td><div style="font-weight: 600;">' . htmlspecialchars($staff['name']) . '</div><div style="font-size: 13px; color: #666;">' . htmlspecialchars($staff['role']) . '</div></td>';
            $html .= '<td style="max-width: 300px; color: #666; font-size: 13px;">' . htmlspecialchars(substr($staff['bio'] ?? '', 0, 100)) . (strlen($staff['bio'] ?? '') > 100 ? '...' : '') . '</td>';
            $statusClass = $staff['is_active'] ? 'status-confirmed' : 'status-cancelled';
            $statusText = $staff['is_active'] ? 'Active' : 'Inactive';
            $html .= '<td><span class="status-badge ' . $statusClass . '">' . $statusText . '</span></td>';
            $html .= '<td><form method="POST" action="staff.php" style="display:inline;">';
            $html .= '<input type="hidden" name="toggle_status" value="1"><input type="hidden" name="id" value="' . $staff['id'] . '"><input type="hidden" name="current_status" value="' . $staff['is_active'] . '">';
            $html .= '<button type="submit" class="btn-action" title="Toggle Status"><i class="fa-solid fa-toggle-' . ($staff['is_active'] ? 'on' : 'off') . '"></i></button>';
            $html .= '</form></td>';
            $html .= '<td>';
            $html .= '<button class="btn-action btn-edit" onclick=\'editStaff(' . json_encode($staff) . ')\'><i class="fa-solid fa-pen-to-square"></i></button>';
            $html .= '<form method="POST" action="delete_staff.php" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this staff member?\');"><input type="hidden" name="id" value="' . $staff['id'] . '"><button type="submit" class="btn-action btn-delete"><i class="fa-solid fa-trash"></i></button></form>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    } else {
        $html = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: #888;">No staff members found.</td></tr>';
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
    <title>Staff - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        /* Page specific styles for avatars */
        .staff-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--accent-gold);
        }
        .staff-initials {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: #333;
            color: var(--accent-gold);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
            border: 2px solid var(--accent-gold);
        }
        .preview-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            margin-top: 10px;
            display: none;
            border: 1px solid #ddd;
        }
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
        <?php $page_title = 'Barbers & Staff'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Toolbar -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search staff..." class="form-control" style="width: 250px;" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button onclick="openModal()" style="background-color: var(--accent-gold); border: none; padding: 10px 20px; color: #000; font-weight: bold; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> Add Staff
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Staff Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Image</th>
                            <th>Name & Role</th>
                            <th>Bio</th>
                            <th>Status</th>
                            <th>Toggle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="staffTableBody">
                        <?php foreach ($staffMembers as $staff): ?>
                        <tr>
                            <td>
                                <?php if (!empty($staff['profile_image']) && file_exists($staff['profile_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($staff['profile_image'] ?? ''); ?>" alt="Profile" class="staff-avatar">
                                <?php else: ?>
                                    <div class="staff-initials"><?php echo substr($staff['name'], 0, 1); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($staff['name']); ?></div>
                                <div style="font-size: 13px; color: #666;"><?php echo htmlspecialchars($staff['role']); ?></div>
                            </td>
                            <td style="max-width: 300px; color: #666; font-size: 13px;">
                                <?php echo htmlspecialchars(substr($staff['bio'] ?? '', 0, 100)) . (strlen($staff['bio'] ?? '') > 100 ? '...' : ''); ?>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $staff['is_active'] ? 'status-confirmed' : 'status-cancelled'; ?>">
                                    <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </td>
                            <td>
                                <form method="POST" action="staff.php" style="display:inline;">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $staff['is_active']; ?>">
                                    <button type="submit" class="btn-action" title="Toggle Status"><i class="fa-solid fa-toggle-<?php echo $staff['is_active'] ? 'on' : 'off'; ?>"></i></button>
                                </form>
                            </td>
                            <td>
                                <button class="btn-action btn-edit" onclick='editStaff(<?php echo json_encode($staff); ?>)'>
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="delete_staff.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                                    <input type="hidden" name="id" value="<?php echo $staff['id']; ?>">
                                    <button type="submit" class="btn-action btn-delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
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

    <!-- Add/Edit Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle" style="margin-top: 0; margin-bottom: 20px;">Add New Staff</h2>
            
            <form method="POST" action="staff.php" enctype="multipart/form-data">
                <input type="hidden" name="staff_id" id="staff_id">
                
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>

                <div class="form-group">
                    <label>Role</label>
                    <select name="role" id="role" class="form-control">
                        <option value="Master Barber">Master Barber</option>
                        <option value="Barber">Barber</option>
                        <option value="Esthetician">Esthetician</option>
                        <option value="Massage Therapist">Massage Therapist</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Profile Image</label>
                    <input type="file" name="profile_image" id="profile_image" class="form-control" accept="image/*">
                    <img id="imagePreview" class="preview-image" src="" alt="Preview">
                </div>

                <div class="form-group">
                    <label>Bio</label>
                    <textarea name="bio" id="bio" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-submit">Save Staff Member</button>
            </form>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        const modal = document.getElementById('staffModal');
        const imagePreview = document.getElementById('imagePreview');
        
        function openModal() {
            document.getElementById('modalTitle').innerText = 'Add New Staff';
            document.getElementById('staff_id').value = '';
            document.querySelector('form').reset();
            imagePreview.style.display = 'none';
            imagePreview.src = '';
            modal.classList.add('show');
        }

        function editStaff(data) {
            document.getElementById('modalTitle').innerText = 'Edit Staff Member';
            document.getElementById('staff_id').value = data.id;
            document.getElementById('name').value = data.name;
            document.getElementById('role').value = data.role;
            document.getElementById('bio').value = data.bio;
            
            if (data.profile_image) {
                imagePreview.src = data.profile_image;
                imagePreview.style.display = 'block';
            } else {
                imagePreview.style.display = 'none';
            }
            
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Simple image preview on file select
        document.getElementById('profile_image').onchange = function (evt) {
            const [file] = this.files;
            if (file) {
                imagePreview.src = URL.createObjectURL(file);
                imagePreview.style.display = 'block';
            }
        };

        // Automatic Search Logic
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('staffTableBody');
        const paginationContainer = document.getElementById('paginationContainer');

        function loadPage(page = 1) {
            const search = this.value;
            fetch(`staff.php?ajax_search=1&search=${encodeURIComponent(search)}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.html;
                    paginationContainer.innerHTML = data.pagination;
                })
                .catch(error => console.error('Error:', error));
        }

        searchInput.addEventListener('input', () => loadPage(1));
    </script>
</body>
</html>
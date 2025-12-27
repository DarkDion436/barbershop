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
    $message = "Service deleted successfully.";
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
}

// Handle Form Submission (Add or Edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? 'Barber';
    $subcategory = $_POST['subcategory'] ?? '';
    $price = $_POST['price'] ?? 0;
    $duration = $_POST['duration'] ?? 30;
    $description = $_POST['description'] ?? '';
    $service_id = $_POST['service_id'] ?? '';

    if (empty($name) || empty($price)) {
        $_SESSION['error'] = "Service Name and Price are required.";
    } else {
        try {
            if (!empty($service_id)) {
                // Update existing service
                $sql = "UPDATE services SET name=?, category=?, subcategory=?, price=?, duration_minutes=?, description=? WHERE id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $category, $subcategory, $price, $duration, $description, $service_id]);
                $_SESSION['message'] = "Service updated successfully!";
            } else {
                // Insert new service
                $sql = "INSERT INTO services (name, category, subcategory, price, duration_minutes, description) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$name, $category, $subcategory, $price, $duration, $description]);
                $_SESSION['message'] = "New service added successfully!";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: services.php");
    exit;
}

// Handle Toggle Status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $id = $_POST['id'] ?? '';
    $current_status = $_POST['current_status'] ?? 0;
    $new_status = $current_status ? 0 : 1;

    if (!empty($id)) {
        try {
            $stmt = $pdo->prepare("UPDATE services SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            $_SESSION['message'] = "Service status updated successfully.";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: services.php");
    exit;
}

// Fetch Services List
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 5; // Items per page
$offset = ($page - 1) * $limit;
$search = $_GET['search'] ?? '';

try {
    // 1. Get Total Count for Pagination
    $countSql = "SELECT COUNT(*) FROM services WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $countSql .= " AND (name LIKE ? OR category LIKE ? OR subcategory LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // 2. Get Data with Limit/Offset
    $sql = "SELECT * FROM services WHERE 1=1";
    if (!empty($search)) {
        $sql .= " AND (name LIKE ? OR category LIKE ? OR subcategory LIKE ?)";
    }
    $sql .= " ORDER BY category DESC, name ASC LIMIT $limit OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $services = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching services: " . $e->getMessage();
}

// AJAX Handler for Table Search
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $html = '';
    if (!empty($services)) {
        foreach ($services as $svc) {
            $html .= '<tr>';
            $html .= '<td><div style="font-weight: 600;">' . htmlspecialchars($svc['name']) . '</div><div style="font-size: 12px; color: #888;">' . htmlspecialchars($svc['description'] ?? '') . '</div></td>';
            $html .= '<td><span class="status-badge" style="background-color: #eee; color: #333;">' . htmlspecialchars($svc['category']) . '</span><small style="color: #666; margin-left: 5px;">' . htmlspecialchars($svc['subcategory']) . '</small></td>';
            $html .= '<td>' . $svc['duration_minutes'] . ' min</td>';
            $html .= '<td>$' . number_format($svc['price'], 2) . '</td>';
            $html .= '<td><form method="POST" action="services.php" style="display:inline;">';
            $html .= '<input type="hidden" name="toggle_status" value="1"><input type="hidden" name="id" value="' . $svc['id'] . '"><input type="hidden" name="current_status" value="' . $svc['is_active'] . '">';
            $html .= '<button type="submit" class="btn-action" title="Toggle Status"><i class="fa-solid fa-toggle-' . ($svc['is_active'] ? 'on' : 'off') . '"></i></button>';
            $html .= '</form></td>';
            $html .= '<td>';
            $html .= '<button class="btn-action btn-edit" onclick=\'editService(' . json_encode($svc) . ')\'><i class="fa-solid fa-pen-to-square"></i></button>';
            $html .= '<form method="POST" action="delete_service.php" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this service?\');"><input type="hidden" name="id" value="' . $svc['id'] . '"><button type="submit" class="btn-action btn-delete"><i class="fa-solid fa-trash"></i></button></form>';
            $html .= '</td>';
            $html .= '</tr>';
        }
    } else {
        $html = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: #888;">No services found.</td></tr>';
    }
    
    // Build Pagination HTML
    $paginationHtml = '';
    if ($totalPages > 1) {
        $paginationHtml .= '<div class="pagination-container" style="margin-top: 20px; display: flex; justify-content: center; gap: 5px;">';
        
        // Previous
        if ($page > 1) {
            $paginationHtml .= '<button class="pagination-btn" onclick="loadPage(' . ($page - 1) . ')">&laquo; Prev</button>';
        }
        
        // Page Numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            $activeClass = ($i == $page) ? 'active' : '';
            $style = ($i == $page) ? 'background-color: var(--accent-gold); color: #000; border-color: var(--accent-gold);' : '';
            $paginationHtml .= '<button class="pagination-btn ' . $activeClass . '" style="' . $style . '" onclick="loadPage(' . $i . ')">' . $i . '</button>';
        }
        
        // Next
        if ($page < $totalPages) {
            $paginationHtml .= '<button class="pagination-btn" onclick="loadPage(' . ($page + 1) . ')">Next &raquo;</button>';
        }
        
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
    <title>Services - Blade & Trim Admin</title>
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
        }
        .pagination-btn:hover {
            background-color: #f0f0f0;
        }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Services & Pricing'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Toolbar -->
            <div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search services..." class="form-control" style="width: 250px;" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <button onclick="openModal()" style="background-color: var(--accent-gold); border: none; padding: 10px 20px; color: #000; font-weight: bold; border-radius: 5px; cursor: pointer;">
                    <i class="fa-solid fa-plus"></i> Add Service
                </button>
            </div>

            <!-- Alerts -->
            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Services Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Category</th>
                            <th>Duration</th>
                            <th>Price</th>
                            <th>Toggle</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="servicesTableBody">
                        <?php foreach ($services as $svc): ?>
                        <tr>
                            <td>
                                <div style="font-weight: 600;"><?php echo htmlspecialchars($svc['name']); ?></div>
                                <div style="font-size: 12px; color: #888;"><?php echo htmlspecialchars($svc['description'] ?? ''); ?></div>
                            </td>
                            <td>
                                <span class="status-badge" style="background-color: #eee; color: #333;">
                                    <?php echo htmlspecialchars($svc['category']); ?>
                                </span>
                                <small style="color: #666; margin-left: 5px;"><?php echo htmlspecialchars($svc['subcategory']); ?></small>
                            </td>
                            <td><?php echo $svc['duration_minutes']; ?> min</td>
                            <td>$<?php echo number_format($svc['price'], 2); ?></td>
                            <td>
                                <form method="POST" action="services.php" style="display:inline;">
                                    <input type="hidden" name="toggle_status" value="1">
                                    <input type="hidden" name="id" value="<?php echo $svc['id']; ?>">
                                    <input type="hidden" name="current_status" value="<?php echo $svc['is_active']; ?>">
                                    <button type="submit" class="btn-action" title="Toggle Status"><i class="fa-solid fa-toggle-<?php echo $svc['is_active'] ? 'on' : 'off'; ?>"></i></button>
                                </form>
                            </td>
                            <td>
                                <button class="btn-action btn-edit" onclick='editService(<?php echo json_encode($svc); ?>)'>
                                    <i class="fa-solid fa-pen-to-square"></i>
                                </button>
                                <form method="POST" action="delete_service.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                    <input type="hidden" name="id" value="<?php echo $svc['id']; ?>">
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
    <div id="serviceModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeModal()">&times;</span>
            <h2 id="modalTitle" style="margin-top: 0; margin-bottom: 20px;">Add New Service</h2>
            
            <form method="POST" action="services.php">
                <input type="hidden" name="service_id" id="service_id">
                
                <div class="form-group">
                    <label>Service Name</label>
                    <input type="text" name="name" id="name" class="form-control" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Category</label>
                        <select name="category" id="category" class="form-control">
                            <option value="Barber">Barber</option>
                            <option value="Spa">Spa</option>
                            <option value="Wellness">Wellness</option>
                            <option value="Packages">Packages</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Subcategory</label>
                        <input type="text" name="subcategory" id="subcategory" class="form-control" placeholder="e.g. Haircuts">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" step="0.01" name="price" id="price" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Duration (min)</label>
                        <input type="number" name="duration" id="duration" class="form-control" value="30">
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn-submit">Save Service</button>
            </form>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        const modal = document.getElementById('serviceModal');
        
        function openModal() {
            document.getElementById('modalTitle').innerText = 'Add New Service';
            document.getElementById('service_id').value = '';
            document.querySelector('form').reset();
            modal.classList.add('show');
        }

        function editService(data) {
            document.getElementById('modalTitle').innerText = 'Edit Service';
            document.getElementById('service_id').value = data.id;
            document.getElementById('name').value = data.name;
            document.getElementById('category').value = data.category;
            document.getElementById('subcategory').value = data.subcategory;
            document.getElementById('price').value = data.price;
            document.getElementById('duration').value = data.duration_minutes;
            document.getElementById('description').value = data.description;
            modal.classList.add('show');
        }

        function closeModal() {
            modal.classList.remove('show');
        }

        // Close if clicked outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Automatic Search Logic
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('servicesTableBody');
        const paginationContainer = document.getElementById('paginationContainer');

        function loadPage(page = 1) {
            const search = searchInput.value;
            fetch(`services.php?ajax_search=1&search=${encodeURIComponent(search)}&page=${page}`)
                .then(response => response.json())
                .then(data => {
                    tableBody.innerHTML = data.html;
                    paginationContainer.innerHTML = data.pagination;
                })
                .catch(error => console.error('Error:', error));
        }

        searchInput.addEventListener('input', function() {
            loadPage(1); // Reset to page 1 on search
        });
    </script>
</body>
</html>
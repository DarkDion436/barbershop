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

// Check for deletion messages
if (isset($_GET['msg']) && $_GET['msg'] === 'deleted') {
    $message = "Customer history deleted successfully.";
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
}

// AJAX Handler: Fetch Booking History
if (isset($_GET['action']) && $_GET['action'] === 'get_history' && isset($_GET['email'])) {
    header('Content-Type: application/json');
    $email = $_GET['email'];
    
    try {
        $sql = "SELECT a.*, s.name as service_name, st.name as staff_name 
                FROM appointments a
                JOIN services s ON a.service_id = s.id
                JOIN staff st ON a.staff_id = st.id
                WHERE a.client_email = ?
                ORDER BY a.appointment_date DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$email]);
        $history = $stmt->fetchAll();
        
        echo json_encode($history);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit; // Stop execution for AJAX requests
}

// Main Page: Fetch Unique Customers Summary
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Items per page
$offset = ($page - 1) * $limit;

try {
    // 1. Get Total Count for Pagination
    $countSql = "SELECT COUNT(DISTINCT client_email) FROM appointments WHERE 1=1";
    $params = [];
    if (!empty($search)) {
        $countSql .= " AND (client_name LIKE ? OR client_email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    $stmt = $pdo->prepare($countSql);
    $stmt->execute($params);
    $totalRecords = $stmt->fetchColumn();
    $totalPages = ceil($totalRecords / $limit);

    // 2. Get Data with Limit/Offset
    $sql = "SELECT 
                client_email, 
                MAX(client_name) as client_name, 
                MAX(client_phone) as client_phone, 
                COUNT(id) as total_bookings, 
                MAX(appointment_date) as last_visit 
            FROM appointments 
            WHERE 1=1";

    $params = [];
    if (!empty($search)) {
        $sql .= " AND (client_name LIKE ? OR client_email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
            
    $sql .= " GROUP BY client_email ORDER BY last_visit DESC LIMIT $limit OFFSET $offset";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching customers: " . $e->getMessage();
}

// AJAX Handler for Table Search
if (isset($_GET['ajax_search'])) {
    header('Content-Type: application/json');
    $html = '';
    if (!empty($customers)) {
        foreach ($customers as $client) {
            $html .= '<tr>';
            $html .= '<td style="font-weight: 600;">' . htmlspecialchars($client['client_name']) . '</td>';
            $html .= '<td><div><i class="fa-regular fa-envelope" style="width: 20px; color: #888;"></i> ' . htmlspecialchars($client['client_email']) . '</div><div style="margin-top: 4px; font-size: 13px; color: #666;"><i class="fa-solid fa-phone" style="width: 20px; color: #888;"></i> ' . htmlspecialchars($client['client_phone']) . '</div></td>';
            $html .= '<td>' . $client['total_bookings'] . '</td>';
            $html .= '<td>' . date('M j, Y', strtotime($client['last_visit'])) . '</td>';
            $html .= '<td><button class="btn-action btn-edit" onclick="viewHistory(\'' . $client['client_email'] . '\', \'' . htmlspecialchars($client['client_name']) . '\')" title="View History"><i class="fa-solid fa-clock-rotate-left"></i> History</button>';
            $html .= '<form method="POST" action="delete_customer.php" style="display:inline;" onsubmit="return confirm(\'Are you sure you want to delete this customer and all their booking history?\');"><input type="hidden" name="email" value="' . htmlspecialchars($client['client_email']) . '"><button type="submit" class="btn-action btn-delete" title="Delete"><i class="fa-solid fa-trash"></i></button></form></td>';
            $html .= '</tr>';
        }
    } else {
        $html = '<tr><td colspan="5" style="text-align: center; padding: 30px; color: #888;">No customers found.</td></tr>';
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
    <title>Customers - Blade & Trim Admin</title>
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
        <?php $page_title = 'Customers'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Toolbar -->
            <div style="margin-bottom: 20px;">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search customers..." class="form-control" style="width: 250px;" value="<?php echo htmlspecialchars($search); ?>">
                </div>
            </div>

            <?php if (isset($message)): ?>
                <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Customers Table -->
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Client Name</th>
                            <th>Contact Info</th>
                            <th>Total Bookings</th>
                            <th>Last Visit</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="customersTableBody">
                        <?php foreach ($customers as $client): ?>
                        <tr>
                            <td style="font-weight: 600;"><?php echo htmlspecialchars($client['client_name']); ?></td>
                            <td>
                                <div><i class="fa-regular fa-envelope" style="width: 20px; color: #888;"></i> <?php echo htmlspecialchars($client['client_email']); ?></div>
                                <div style="margin-top: 4px; font-size: 13px; color: #666;"><i class="fa-solid fa-phone" style="width: 20px; color: #888;"></i> <?php echo htmlspecialchars($client['client_phone']); ?></div>
                            </td>
                            <td><?php echo $client['total_bookings']; ?></td>
                            <td><?php echo date('M j, Y', strtotime($client['last_visit'])); ?></td>
                            <td>
                                <button class="btn-action btn-edit" onclick="viewHistory('<?php echo $client['client_email']; ?>', '<?php echo htmlspecialchars($client['client_name']); ?>')" title="View History">
                                    <i class="fa-solid fa-clock-rotate-left"></i> History
                                </button>
                                <form method="POST" action="delete_customer.php" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this customer and all their booking history?');">
                                    <input type="hidden" name="email" value="<?php echo htmlspecialchars($client['client_email']); ?>">
                                    <button type="submit" class="btn-action btn-delete" title="Delete"><i class="fa-solid fa-trash"></i></button>
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

    <!-- History Modal -->
    <div id="historyModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <span class="close-modal" onclick="document.getElementById('historyModal').classList.remove('show')">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px;">Booking History: <span id="modalClientName" style="color: var(--accent-gold);"></span></h2>
            
            <div class="table-responsive" style="padding: 0; box-shadow: none;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Service</th>
                            <th>Staff</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        <!-- Populated by JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        function viewHistory(email, name) {
            const modal = document.getElementById('historyModal');
            const tbody = document.getElementById('historyTableBody');
            const nameSpan = document.getElementById('modalClientName');
            
            nameSpan.textContent = name;
            tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">Loading...</td></tr>';
            modal.classList.add('show');

            fetch(`customers.php?action=get_history&email=${encodeURIComponent(email)}`)
                .then(response => response.json())
                .then(data => {
                    tbody.innerHTML = '';
                    if (data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No history found.</td></tr>';
                        return;
                    }

                    data.forEach(appt => {
                        const date = new Date(appt.appointment_date);
                        const dateStr = date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                        const timeStr = date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                        
                        const statusClass = 'status-' + appt.status.toLowerCase();
                        
                        const row = `
                            <tr>
                                <td>
                                    <div style="font-weight: 600;">${dateStr}</div>
                                    <div style="font-size: 12px; color: #888;">${timeStr}</div>
                                </td>
                                <td>${appt.service_name}</td>
                                <td>${appt.staff_name}</td>
                                <td><span class="status-badge ${statusClass}">${appt.status}</span></td>
                            </tr>
                        `;
                        tbody.innerHTML += row;
                    });
                })
                .catch(err => {
                    console.error(err);
                    tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red;">Error loading history.</td></tr>';
                });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('historyModal');
            if (event.target == modal) {
                modal.classList.remove('show');
            }
        }

        // Automatic Search Logic
        const searchInput = document.getElementById('searchInput');
        const tableBody = document.getElementById('customersTableBody');
        const paginationContainer = document.getElementById('paginationContainer');

        function loadPage(page = 1) {
            const search = searchInput.value;
            fetch(`customers.php?ajax_search=1&search=${encodeURIComponent(search)}&page=${page}`)
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
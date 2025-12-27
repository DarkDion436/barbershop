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
    $message = "Image deleted successfully.";
}
if (isset($_GET['err'])) {
    $error = htmlspecialchars($_GET['err']);
}

// Handle Image Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $caption = trim($_POST['caption'] ?? '');
    
    if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/gallery/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileTmpPath = $_FILES['image']['tmp_name'];
        $fileName = $_FILES['image']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = time() . '_' . uniqid() . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $destPath)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO gallery (image_path, caption) VALUES (?, ?)");
                    $stmt->execute([$destPath, $caption]);
                    $_SESSION['message'] = "Image uploaded successfully!";
                } catch (PDOException $e) {
                    $_SESSION['error'] = "Database Error: " . $e->getMessage();
                    unlink($destPath); // Clean up file if DB insert fails
                }
            } else {
                $_SESSION['error'] = "Error moving uploaded file.";
            }
        } else {
            $_SESSION['error'] = "Invalid file type. Allowed: " . implode(', ', $allowedExtensions);
        }
    } else {
        $_SESSION['error'] = "Please select a valid image file.";
    }
    // Redirect
    header("Location: gallery.php");
    exit;
}

// Handle Caption Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_caption'])) {
    $id = $_POST['image_id'] ?? '';
    $caption = trim($_POST['caption'] ?? '');
    
    if (!empty($id)) {
        try {
            $stmt = $pdo->prepare("UPDATE gallery SET caption = ? WHERE id = ?");
            $stmt->execute([$caption, $id]);
            $_SESSION['message'] = "Caption updated successfully!";
        } catch (PDOException $e) {
            $_SESSION['error'] = "Database Error: " . $e->getMessage();
        }
    }
    // Redirect
    header("Location: gallery.php");
    exit;
}

// Fetch Gallery Images
try {
    $stmt = $pdo->query("SELECT * FROM gallery ORDER BY created_at DESC");
    $images = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching images: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gallery - Blade & Trim Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="sidebar.css">
    <link rel="stylesheet" href="dashboard.css">
    <style>
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        .gallery-item {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            position: relative;
            transition: transform 0.2s;
        }
        .gallery-item:hover {
            transform: translateY(-3px);
        }
        .gallery-img {
            width: 100%;
            height: 150px;
            object-fit: cover;
            display: block;
        }
        .gallery-caption {
            padding: 10px;
            font-size: 13px;
            color: #333;
            border-top: 1px solid #eee;
        }
        .delete-overlay {
            position: absolute;
            top: 10px;
            right: 10px;
            display: flex;
        }
        .btn-delete-img {
            background: rgba(231, 76, 60, 0.9);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        .btn-delete-img:hover { background: #c0392b; }
        .btn-edit-img {
            background: rgba(52, 152, 219, 0.9);
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            margin-right: 5px;
        }
        .btn-edit-img:hover { background: #2980b9; }
    </style>
</head>
<body>

    <?php include 'sidebar.php'; ?>

    <main class="main-content">
        <?php $page_title = 'Hairstyle Gallery'; ?>
        <?php include 'header.php'; ?>

        <div class="content-container">
            
            <!-- Upload Section -->
            <div style="background: #fff; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.03); margin-bottom: 30px;">
                <h3 style="margin-top: 0; margin-bottom: 15px; font-size: 16px;">Upload New Image</h3>
                <form method="POST" action="gallery.php" enctype="multipart/form-data" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                    <div style="flex: 1; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Image File</label>
                        <input type="file" name="image" class="form-control" accept="image/*" required>
                    </div>
                    <div style="flex: 2; min-width: 200px;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #666;">Caption (Optional)</label>
                        <input type="text" name="caption" class="form-control" placeholder="e.g. Modern Fade">
                    </div>
                    <button type="submit" class="btn-submit" style="width: auto; margin-top: 0;"><i class="fa-solid fa-cloud-arrow-up"></i> Upload</button>
                </form>
            </div>

            <?php if ($message): ?>
                <div class="alert-message success"><?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert-message error"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Gallery Grid -->
            <div class="gallery-grid">
                <?php if (empty($images)): ?>
                    <div style="grid-column: 1 / -1; text-align: center; color: #888; padding: 40px;">No images uploaded yet.</div>
                <?php else: ?>
                    <?php foreach ($images as $img): ?>
                        <div class="gallery-item">
                            <img src="<?php echo htmlspecialchars($img['image_path']); ?>" alt="Gallery Image" class="gallery-img">
                            <?php if (!empty($img['caption'])): ?>
                                <div class="gallery-caption"><?php echo htmlspecialchars($img['caption'] ?? ''); ?></div>
                            <?php endif; ?>
                            
                            <div class="delete-overlay">
                                <button type="button" class="btn-edit-img" onclick='editCaption(<?php echo json_encode($img); ?>)' title="Edit Caption"><i class="fa-solid fa-pen"></i></button>
                                <form method="POST" action="delete_gallery.php" onsubmit="return confirm('Delete this image?');">
                                    <input type="hidden" name="id" value="<?php echo $img['id']; ?>">
                                    <button type="submit" class="btn-delete-img" title="Delete"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>

    <!-- Edit Caption Modal -->
    <div id="captionModal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeCaptionModal()">&times;</span>
            <h2 style="margin-top: 0; margin-bottom: 20px;">Edit Caption</h2>
            <form method="POST" action="gallery.php">
                <input type="hidden" name="update_caption" value="1">
                <input type="hidden" name="image_id" id="edit_image_id">
                <div class="form-group">
                    <label>Caption</label>
                    <input type="text" name="caption" id="edit_caption" class="form-control">
                </div>
                <button type="submit" class="btn-submit">Update Caption</button>
            </form>
        </div>
    </div>

    <script src="sidebar.js"></script>
    <script>
        const captionModal = document.getElementById('captionModal');
        function editCaption(data) {
            document.getElementById('edit_image_id').value = data.id;
            document.getElementById('edit_caption').value = data.caption;
            captionModal.classList.add('show');
        }
        function closeCaptionModal() {
            captionModal.classList.remove('show');
        }
        window.onclick = function(event) {
            if (event.target == captionModal) {
                closeCaptionModal();
            }
        }
    </script>
</body>
</html>
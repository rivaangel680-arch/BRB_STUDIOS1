<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Admin']);

// Image path helper: external URLs stay as-is, local files live in the root folder
function picPath($path) {
    $path = $path ?: 'uploads/profile_default.png';
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$errors = [];

// ---------- Save username / password ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $username = trim($_POST['username'] ?? '');
    $newPass  = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($username === '') {
        $errors[] = 'Username cannot be empty.';
    } elseif ($username !== $admin['username']) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $_SESSION['user_id']]);
        if ($check->fetch()) {
            $errors[] = 'Username is already taken.';
        }
    }

    if ($newPass !== '' || $confirm !== '') {
        if ($newPass !== $confirm) {
            $errors[] = 'Passwords do not match.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $newPass)) {
            $errors[] = 'Password must be at least 8 characters with uppercase, lowercase, and a number.';
        }
    }

    if (empty($errors)) {
        if ($newPass !== '') {
            $stmt = $conn->prepare("UPDATE users SET username = ?, password = ? WHERE id = ?");
            $stmt->execute([$username, password_hash($newPass, PASSWORD_DEFAULT), $_SESSION['user_id']]);
        } else {
            $stmt = $conn->prepare("UPDATE users SET username = ? WHERE id = ?");
            $stmt->execute([$username, $_SESSION['user_id']]);
        }
        $_SESSION['username'] = $username;
        header("Location: admin_profile.php?success=" . urlencode('Profile updated successfully!'));
        exit;
    }
}

// ---------- Upload profile picture ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_picture'])) {
    $file = $_FILES['profile_pic'] ?? null;

    // Image types accepted (detected from the file contents, not the file name)
    $typeMap = [
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
        IMAGETYPE_BMP  => 'bmp',
    ];
    if (defined('IMAGETYPE_AVIF')) $typeMap[IMAGETYPE_AVIF] = 'avif';
    $maxSize = 10 * 1024 * 1024; // 10 MB

    if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Please choose a picture first.';
    } elseif ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        $errors[] = 'The picture is larger than the server allows. Choose a smaller photo or raise upload_max_filesize in php.ini.';
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Upload failed. Please try again.';
    } else {
        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !isset($typeMap[$info[2]])) {
            $errors[] = 'Please choose a valid photo (JPG, PNG, GIF, WEBP, BMP).';
        } elseif ($file['size'] > $maxSize) {
            $errors[] = 'The picture must be 10 MB or smaller.';
        } else {
            $ext = $typeMap[$info[2]];
            $uploadDir = __DIR__ . '/../uploads/';   // real folder on disk (root/uploads)
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;

            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $dbPath = 'uploads/' . $filename;   // path saved in the database
                $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->execute([$dbPath, $_SESSION['user_id']]);
                $_SESSION['profile_pic'] = $dbPath;
                header("Location: admin_profile.php?success=" . urlencode('Profile picture updated!'));
                exit;
            }
            $errors[] = 'Could not save the picture. Check that the uploads folder is writable.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin Profile — BRB Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Times New Roman', serif; font-size: 18px; }
        body { 
            background: linear-gradient(135deg, #ffffff 0%, #e5e5e5 100%);
            min-height: 100vh; 
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(16px); 
            border-radius: 20px; 
            border: 1px solid #d4d4d4;
        }
        .sidebar-bg { 
            background: linear-gradient(180deg, #000000 0%, #171717 100%);
            backdrop-filter: blur(12px); 
        }
        .active-nav-item { 
            background: rgba(255, 255, 255, 0.1); 
            border-radius: 8px; 
        }
        .btn-black { 
            background: #000000; 
            color: white; 
            transition: all 0.2s ease;
        }
        .btn-black:hover { background: #404040; }
        h1 { font-size: 36px; font-weight: bold; }
        h2 { font-size: 26px; font-weight: 600; }
        label { font-weight: 500; }
    </style>
</head>
<body class="flex">

<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer active-nav-item" onclick="window.location.href='admin_profile.php'">
            <img src="<?= htmlspecialchars(picPath($admin['profile_pic'] ?? '')) ?>" 
                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-400" alt="Admin Profile">
            <div>
                <p class="font-bold text-white text-lg"><?= htmlspecialchars($admin['username']) ?></p>
                <p class="text-base text-gray-400">ADMIN</p>
            </div>
        </div>
        <a href="admin_dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-gauge w-5 text-center text-gray-300"></i><span>Dashboard</span>
        </a>
        <a href="admin_clients.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-users w-5 text-center text-gray-400"></i><span>Client Management</span>
        </a>
        <a href="admin_services.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-scissors w-5 text-center text-gray-400"></i><span>Service Management</span>
        </a>
        <a href="admin_charts.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-chart-simple w-5 text-center text-gray-400"></i><span>Client Chart</span>
        </a>
        <a href="admin_appointments.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-check w-5 text-center text-gray-400"></i><span>Appointment & Booking</span>
        </a>
        <a href="admin_calendar.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-days w-5 text-center text-gray-400"></i><span>Calendar</span>
        </a>
        <a href="admin_records.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-folder-open w-5 text-center text-gray-400"></i><span>Records & Audit</span>
        </a>
        <a href="admin_attendance.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-clipboard-check w-5 text-center text-gray-400"></i><span>Attendance</span>
        </a>
        <a href="admin_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-star w-5 text-center text-gray-400"></i><span>Report</span>
        </a>
        <a href="../logout.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-gray-300 mt-4 text-lg">
            <i class="fa-solid fa-right-from-bracket w-5 text-center text-gray-400"></i><span>Logout</span>
        </a>
    </div>
    <div class="p-4 border-t border-gray-800 mt-4">
        <div class="text-4xl font-extrabold text-white">BRB</div>
        <div class="text-base text-gray-500 uppercase tracking-wider">STUDIOS</div>
    </div>
</aside>

<main class="flex-1 p-6 md:p-10 overflow-y-auto flex items-start justify-center">
    <div class="w-full max-w-lg">
        <h1 class="text-center mb-8">Edit Admin Profile</h1>

        <?php if (!empty($errors)): ?>
        <div class="glass-card p-6 mb-6 bg-red-50 border-red-200">
            <?php foreach ($errors as $err): ?>
            <p class="text-red-600 mb-1">• <?= htmlspecialchars($err) ?></p>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Profile Picture -->
        <div class="glass-card p-8 mb-8" id="picture">
            <h2 class="mb-4">Profile Picture</h2>
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <div class="flex items-center gap-6">
                    <img src="<?= htmlspecialchars(picPath($admin['profile_pic'] ?? '')) ?>"
                         class="w-28 h-28 rounded-full object-cover border-2 border-gray-300" alt="Current Picture">
                    <div class="flex-1">
                        <input type="file" name="profile_pic" accept="image/*" class="w-full text-base" required>
                        <p class="text-sm text-gray-500 mt-2">Any photo: JPG, PNG, GIF, WEBP or BMP. Max 10 MB.</p>
                    </div>
                </div>
                <button type="submit" name="upload_picture" class="btn-black px-6 py-2 rounded-lg">
                    <i class="fa-solid fa-upload mr-2"></i> Upload Picture
                </button>
            </form>
        </div>

        <!-- Username & Password -->
        <div class="glass-card p-8">
            <h2 class="mb-4">Username & Password</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block mb-2">Username</label>
                    <input type="text" name="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? $admin['username']) ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500" required>
                </div>
                <div>
                    <label class="block mb-2">New Password</label>
                    <input type="password" name="new_password" placeholder="Leave blank to keep current password"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <div>
                    <label class="block mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" placeholder="Re-enter new password"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <div class="flex gap-4 pt-2">
                    <a href="admin_profile.php" class="px-6 py-2 border border-black rounded-lg">Cancel</a>
                    <button type="submit" name="update_profile" class="btn-black px-6 py-2 rounded-lg">
                        <i class="fa-solid fa-save mr-2"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</main>

</body>
</html>
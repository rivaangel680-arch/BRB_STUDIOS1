<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Staff']);

$msg = '';

// Handle profile update
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['change_picture'])) {
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg','jpeg','png','gif'];
            $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed)) {
                $uploadDir = __DIR__ . '/../uploads/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = 'staff_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
                move_uploaded_file($_FILES['profile_pic']['tmp_name'], $uploadDir . $filename);
                
                $stmt = $conn->prepare("UPDATE users SET profile_pic = ? WHERE id = ?");
                $stmt->execute(['uploads/' . $filename, $_SESSION['user_id']]);
                $msg = 'Picture updated successfully.';
            }
        }
    }
    
    if (isset($_POST['update_profile'])) {
        $first = trim($_POST['first_name'] ?? '');
        $last = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($username === '' || $email === '') {
            $msg = 'Username and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $msg = 'Please enter a valid email address.';
        } else {
            $check = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id <> ?");
            $check->execute([$username, $email, $_SESSION['user_id']]);
            if ($check->fetch()) {
                $msg = 'Username or email is already in use.';
            } else {
                $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ? WHERE id = ?");
                $stmt->execute([$first, $last, $username, $email, $_SESSION['user_id']]);
                $msg = 'Profile updated successfully.';
            }
        }
    }

    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
        if (!password_verify($current, $user['password'])) {
            $msg = 'Current password is incorrect.';
        } elseif ($new !== $confirm) {
            $msg = 'New passwords do not match.';
        } elseif (strlen($new) < 8) {
            $msg = 'Password must be at least 8 characters.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $_SESSION['user_id']]);
            $msg = 'Password changed successfully.';
        }
    }
}

// Staff info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Profile — BRB Studio</title>
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

<!-- Sidebar -->
<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer active-nav-item" onclick="window.location.href='staff_profile.php'">
            <img src="<?= htmlspecialchars('../' . ($staff['profile_pic'] ?? 'uploads/profile_default.png')) ?>" 
                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-400" alt="Staff Profile">
            <div>
                <p class="font-bold text-white text-lg"><?= htmlspecialchars($staff['username']) ?></p>
                <p class="text-base text-gray-400">STAFF</p>
            </div>
        </div>
        <a href="staff_dashboard.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-gauge w-5 text-center text-gray-300"></i><span>Dashboard</span>
        </a>
        <a href="staff_clients.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-users w-5 text-center text-gray-400"></i><span>Client Management</span>
        </a>
        <a href="staff_calendar.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-days w-5 text-center text-gray-400"></i><span>Calendar</span>
        </a>
        <a href="staff_appointments.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-check w-5 text-center text-gray-400"></i><span>Appointment & Booking</span>
        </a>
        <a href="staff_attendance.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-clipboard-check w-5 text-center text-gray-400"></i><span>Attendance</span>
        </a>
        <a href="staff_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
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

<!-- Main Content -->
<main class="flex-1 p-6 md:p-10 overflow-y-auto flex items-start justify-center">
    <div class="w-full max-w-lg">
        <h1 class="text-center mb-8">Staff Profile</h1>
        
        <?php if ($msg): ?>
        <div class="glass-card p-4 mb-6 text-center <?= strpos($msg, 'successfully') !== false ? 'text-green-600' : 'text-red-600' ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>
        
        <div class="glass-card p-8">
            <!-- Profile Picture — Nasa Taas at Gitna -->
            <div class="text-center mb-8">
                <img src="<?= htmlspecialchars('../' . ($staff['profile_pic'] ?? 'uploads/profile_default.png')) ?>" 
                     class="w-40 h-40 rounded-full object-cover border-4 border-gray-300 mx-auto mb-4" alt="Staff Picture">
                <h2 class="text-2xl font-bold"><?= htmlspecialchars($staff['username']) ?></h2>
                <p class="text-gray-500">Staff Member</p>
            </div>

            <!-- Divider -->
            <hr class="border-gray-200 mb-8">

            <!-- Edit Section -->
            <h3 class="text-xl font-semibold mb-6">Edit Profile</h3>
            
            <!-- Edit Personal Details -->
            <form method="POST" class="space-y-4 mb-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block mb-2">First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($staff['first_name'] ?? '') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                    </div>
                    <div>
                        <label class="block mb-2">Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($staff['last_name'] ?? '') ?>"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                    </div>
                </div>
                <div>
                    <label class="block mb-2">Username</label>
                    <input type="text" name="username" required value="<?= htmlspecialchars($staff['username'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <div>
                    <label class="block mb-2">Email</label>
                    <input type="email" name="email" required value="<?= htmlspecialchars($staff['email'] ?? '') ?>"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <button type="submit" name="update_profile" class="btn-black px-6 py-2 rounded-lg mt-2">
                    <i class="fa-solid fa-floppy-disk mr-2"></i> Save Changes
                </button>
            </form>

            <!-- Divider -->
            <hr class="border-gray-200 mb-8">

            <!-- Change Password -->
            <form method="POST" class="space-y-4 mb-8">
                <div>
                    <label class="block mb-2">Current Password</label>
                    <input type="password" name="current_password" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <div>
                    <label class="block mb-2">New Password</label>
                    <input type="password" name="new_password" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <div>
                    <label class="block mb-2">Confirm Password</label>
                    <input type="password" name="confirm_password" required 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>
                <button type="submit" name="change_password" class="btn-black px-6 py-2 rounded-lg mt-2">
                    <i class="fa-solid fa-lock mr-2"></i> Change Password
                </button>
            </form>

            <!-- Divider -->
            <hr class="border-gray-200 mb-6">

            <!-- Change Profile Picture -->
            <form method="POST" enctype="multipart/form-data" class="space-y-3">
                <div>
                    <label class="block mb-2">Change Picture</label>
                    <input type="file" name="profile_pic" accept="image/*" required 
                           class="w-full text-sm">
                </div>
                <button type="submit" name="change_picture" class="border border-black px-6 py-2 rounded-lg inline-flex items-center justify-center gap-2 w-full hover:bg-gray-50">
                    <i class="fa-solid fa-image"></i> Upload New Picture
                </button>
            </form>
        </div>
    </div>
</main>

</body>
</html>
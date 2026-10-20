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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile — BRB Studio</title>
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

<!-- Sidebar — Walang binago -->
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

<!-- Main Content -->
<main class="flex-1 p-6 md:p-10 overflow-y-auto flex items-start justify-center">
    <div class="w-full max-w-lg">
        <h1 class="text-center mb-8">Admin Profile</h1>
        
        <div class="glass-card p-8">
            <!-- Profile Picture — Nasa Taas at Gitna -->
            <div class="text-center mb-8">
                <img src="<?= htmlspecialchars(picPath($admin['profile_pic'] ?? '')) ?>" 
                     class="w-40 h-40 rounded-full object-cover border-4 border-gray-300 mx-auto mb-4" alt="Admin Picture">
                <h2 class="text-2xl font-bold"><?= htmlspecialchars($admin['username']) ?></h2>
                <p class="text-gray-500">Administrator</p>
            </div>

            <!-- Divider -->
            <hr class="border-gray-200 mb-8">

            <!-- Edit Section -->
            <h3 class="text-xl font-semibold mb-6">Edit Profile</h3>
            
            <form method="POST" action="admin_edit_profile.php" class="space-y-4 mb-8">
                <!-- Username -->
                <div>
                    <label class="block mb-2">Enter your Username</label>
                    <input type="text" name="username" 
                           value="<?= htmlspecialchars($admin['username']) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>

                <!-- Change Password -->
                <div>
                    <label class="block mb-2">Change Password</label>
                    <input type="password" name="new_password" placeholder="Enter new password"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>

                <!-- Confirm Password -->
                <div>
                    <label class="block mb-2">Confirm your password</label>
                    <input type="password" name="confirm_password" placeholder="Confirm new password"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-gray-500">
                </div>

                <button type="submit" name="update_profile" class="btn-black px-6 py-2 rounded-lg mt-2">
                    <i class="fa-solid fa-save mr-2"></i> Save Changes
                </button>
            </form>

            <!-- Quick Action: Change Profile Picture -->
            <hr class="border-gray-200 mb-6">
            <a href="admin_edit_profile.php#picture" class="border border-black px-6 py-3 rounded-lg inline-flex items-center justify-center gap-2 w-full hover:bg-gray-50">
                <i class="fa-solid fa-image"></i> Change Profile Picture
            </a>
        </div>
    </div>
</main>

</body>
</html>
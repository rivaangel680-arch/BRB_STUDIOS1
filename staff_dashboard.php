<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Staff']);

// Image path helper: external URLs stay as-is, local files live in the root folder
function picPath($path) {
    $path = $path ?: 'uploads/profile_default.png';
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
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
    <title>Staff Dashboard — BRB Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Times New Roman', serif; font-size: 18px; }
        body { 
            background: linear-gradient(135deg, #ffffff 0%, #e5e5e5 100%);
            min-height: 100vh; 
        }
        .sidebar-bg { 
            background: linear-gradient(180deg, #000000 0%, #171717 100%);
            backdrop-filter: blur(12px); 
        }
        .active-nav-item { 
            background: rgba(255, 255, 255, 0.1); 
            border-radius: 8px; 
        }
    </style>
</head>
<body class="flex">

<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="window.location.href='staff_profile.php'">
            <img src="<?= htmlspecialchars(picPath($staff['profile_pic'] ?? '')) ?>" 
                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-400" alt="Staff Profile">
            <div>
                <p class="font-bold text-white text-lg"><?= htmlspecialchars($staff['username']) ?></p>
                <p class="text-base text-gray-400">STAFF</p>
            </div>
        </div>
        <a href="staff_dashboard.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto flex items-center justify-center min-h-screen">
    <div class="text-center">
        <div class="w-24 h-0.5 bg-black mx-auto mb-8"></div>
        <h1 class="text-5xl font-bold text-gray-900 tracking-wider mb-6">Staff Dashboard</h1>
        <p class="text-xl text-gray-600 italic tracking-wide">Manage Your Work. Deliver Quality. Grow With Us.</p>
        <div class="w-24 h-0.5 bg-black mx-auto mt-8"></div>
    </div>
</main>

</body>
</html>
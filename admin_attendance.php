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

$search     = trim($_GET['search'] ?? '');
$dateFilter = $_GET['date'] ?? date('Y-m-d');   // empty date = show all dates

// Attendance records (matches the `attendance` table in the database)
$where  = "WHERE 1=1";
$params = [];
if ($search !== '') {
    $where .= " AND (a.client_name LIKE ? OR a.email LIKE ? OR a.service_name LIKE ?)";
    $params = array_merge($params, array_fill(0, 3, "%$search%"));
}
if ($dateFilter !== '') {
    $where .= " AND DATE(a.attendance_date) = ?";
    $params[] = $dateFilter;
}

$stmt = $conn->prepare("
    SELECT a.*, m.username AS marked_by_name
    FROM attendance a
    LEFT JOIN users m ON a.marked_by = m.id
    $where
    ORDER BY a.attendance_date DESC
");
$stmt->execute($params);
$attendances = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance — BRB Studio</title>
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
    </style>
</head>
<body class="flex">

<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="window.location.href='admin_profile.php'">
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
        <a href="admin_attendance.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <h1>Attendance</h1>
        <div class="flex flex-col md:flex-row gap-4">
            <form method="GET" class="flex gap-2">
                <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg">
                <input type="text" name="search" placeholder="Search client or service..." value="<?= htmlspecialchars($search) ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-lg">
                <button type="submit" class="btn-black px-4 py-2 rounded-lg"><i class="fa-solid fa-search"></i></button>
                <a href="admin_attendance.php?date=" class="px-4 py-2 border border-black rounded-lg">All dates</a>
            </form>
        </div>
    </div>

    <?php if (empty($attendances)): ?>
    <div class="glass-card p-12 text-center">
        <i class="fa-solid fa-clipboard-check text-5xl text-gray-300 mb-4"></i>
        <p class="text-xl text-gray-500">No attendance records found.</p>
    </div>
    <?php else: ?>
    <p class="text-gray-600 mb-4"><?= count($attendances) ?> record<?= count($attendances) === 1 ? '' : 's' ?></p>
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Service</th>
                        <th class="px-4 py-3 text-left">Date & Time</th>
                        <th class="px-4 py-3 text-left">Marked By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attendances as $a): ?>
                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium"><?= htmlspecialchars($a['client_name']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($a['email']) ?></p>
                        </td>
                        <td class="px-4 py-3"><?= htmlspecialchars($a['service_name']) ?></td>
                        <td class="px-4 py-3">
                            <?= date('M j, Y', strtotime($a['attendance_date'])) ?>
                            <span class="text-gray-500 text-sm"><?= date('g:i A', strtotime($a['attendance_date'])) ?></span>
                        </td>
                        <td class="px-4 py-3 text-gray-600">
                            <?= $a['marked_by_name'] ? '@' . htmlspecialchars($a['marked_by_name']) : '—' ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</main>

</body>
</html>
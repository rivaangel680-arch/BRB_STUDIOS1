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


$search = $_GET['search'] ?? '';

// Get completed/cancelled appointments
$where = "WHERE a.status IN ('Completed', 'Cancelled')";
$params = [];
if (!empty($search)) {
    $where .= " AND (s.name LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR a.appointment_date LIKE ?)";
    $params = array_fill(0, 4, "%$search%");
}

$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, u.first_name, u.last_name, u.username, u.email
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    $where
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
    LIMIT 50
");
$stmt->execute($params);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <title>Records & Audit — BRB Studio</title>
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
        <a href="admin_records.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <h1>Records & Audit</h1>
        <form method="GET" class="flex">
            <input type="text" name="search" placeholder="Search records..." value="<?= htmlspecialchars($search) ?>" 
                   class="px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none">
            <button type="submit" class="btn-black px-4 py-2 rounded-r-lg"><i class="fa-solid fa-search"></i></button>
        </form>
    </div>

    <?php if (empty($records)): ?>
    <div class="glass-card p-12 text-center">
        <i class="fa-solid fa-folder-open text-5xl text-gray-300 mb-4"></i>
        <p class="text-xl text-gray-500">No records found.</p>
    </div>
    <?php else: ?>
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Service</th>
                        <th class="px-4 py-3 text-left">Date & Time</th>
                        <th class="px-4 py-3 text-left">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <p class="font-medium"><?= htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: $r['username']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($r['email']) ?></p>
                        </td>
                        <td class="px-4 py-3"><?= htmlspecialchars($r['service_name']) ?></td>
                        <td class="px-4 py-3">
                            <?= date('M j, Y', strtotime($r['appointment_date'])) ?>
                            <span class="text-gray-500 text-sm"><?= date('g:i A', strtotime($r['appointment_time'])) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium <?= $r['status'] === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= htmlspecialchars($r['status']) ?>
                            </span>
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
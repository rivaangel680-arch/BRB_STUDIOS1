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

// ---------- Activate / Deactivate a client ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $clientId = (int) ($_POST['client_id'] ?? 0);
    $stmt = $conn->prepare("UPDATE users
        SET status = IF(status = 'Active', 'Inactive', 'Active')
        WHERE id = ? AND role = 'Client'");
    $stmt->execute([$clientId]);
    $msg = $stmt->rowCount() > 0 ? 'Client status updated.' : 'Client not found.';
    header("Location: admin_clients.php?msg=" . urlencode($msg) . "&search=" . urlencode($_POST['search'] ?? ''));
    exit;
}

$search = trim($_GET['search'] ?? '');

$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM appointments a WHERE a.user_id = u.id) AS total_appointments
        FROM users u
        WHERE u.role = 'Client'";
$params = [];
if ($search !== '') {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.contact_number LIKE ?)";
    $params = array_fill(0, 5, "%$search%");
}
$sql .= " ORDER BY u.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin info (sidebar)
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Management — BRB Studio</title>
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
        <a href="admin_clients.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <h1>Client Management</h1>
        <form method="GET" class="flex">
            <input type="text" name="search" placeholder="Search clients..." value="<?= htmlspecialchars($search) ?>"
                   class="px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none">
            <button type="submit" class="btn-black px-4 py-2 rounded-r-lg"><i class="fa-solid fa-search"></i></button>
        </form>
    </div>

    <?php if (!empty($_GET['msg'])): ?>
    <div class="glass-card p-4 mb-6 bg-green-50 border-green-200 text-center">
        <p class="text-green-700"><?= htmlspecialchars($_GET['msg']) ?></p>
    </div>
    <?php endif; ?>

    <?php if (empty($clients)): ?>
    <div class="glass-card p-12 text-center">
        <i class="fa-solid fa-users text-5xl text-gray-300 mb-4"></i>
        <p class="text-xl text-gray-500">No clients found.</p>
    </div>
    <?php else: ?>
    <p class="text-gray-600 mb-4"><?= count($clients) ?> client<?= count($clients) === 1 ? '' : 's' ?></p>
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left">Client</th>
                        <th class="px-4 py-3 text-left">Contact</th>
                        <th class="px-4 py-3 text-left">Joined</th>
                        <th class="px-4 py-3 text-center">Appointments</th>
                        <th class="px-4 py-3 text-left">Status</th>
                        <th class="px-4 py-3 text-right">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($clients as $c): ?>
                    <?php $isActive = ($c['status'] ?? 'Active') === 'Active'; ?>
                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <img src="<?= htmlspecialchars(picPath($c['profile_pic'] ?? '')) ?>"
                                     class="w-10 h-10 rounded-full object-cover border border-gray-300" alt="">
                                <div>
                                    <p class="font-medium"><?= htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: $c['username']) ?></p>
                                    <p class="text-sm text-gray-500">@<?= htmlspecialchars($c['username']) ?></p>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3">
                            <p><?= htmlspecialchars($c['email']) ?></p>
                            <p class="text-sm text-gray-500"><?= htmlspecialchars($c['contact_number'] ?: 'No number') ?></p>
                        </td>
                        <td class="px-4 py-3 text-gray-600"><?= date('M j, Y', strtotime($c['created_at'])) ?></td>
                        <td class="px-4 py-3 text-center"><?= (int) $c['total_appointments'] ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-medium <?= $isActive ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                                <?= $isActive ? 'Active' : 'Inactive' ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST"
                                  onsubmit="return confirm('<?= $isActive ? 'Deactivate' : 'Activate' ?> this client account?')">
                                <input type="hidden" name="client_id" value="<?= (int) $c['id'] ?>">
                                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
                                <button type="submit" name="toggle_status"
                                        class="px-4 py-1 rounded-lg border text-sm <?= $isActive ? 'border-red-500 text-red-600 hover:bg-red-50' : 'border-green-600 text-green-700 hover:bg-green-50' ?>">
                                    <?= $isActive ? 'Deactivate' : 'Activate' ?>
                                </button>
                            </form>
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
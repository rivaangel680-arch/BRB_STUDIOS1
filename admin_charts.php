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

// Admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

// Monthly data for Line Chart
$months = ['Oct', 'Nov', 'Dec', 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'];
$monthlyData = [];
foreach ($months as $m) { $monthlyData[$m] = 0; }

$stmt = $conn->prepare("
    SELECT DATE_FORMAT(appointment_date, '%b') as month, COUNT(*) as total 
    FROM appointments 
    WHERE status = 'Approved' 
    AND appointment_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(appointment_date, '%Y-%m'), DATE_FORMAT(appointment_date, '%b')
    ORDER BY MIN(appointment_date) ASC
");
$stmt->execute();
foreach ($stmt as $row) {
    if (isset($monthlyData[$row['month']])) {
        $monthlyData[$row['month']] = $row['total'];
    }
}

// Service data for Pie Chart
$stmt = $conn->prepare("
    SELECT s.name, COUNT(*) as count 
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.status = 'Approved'
    GROUP BY a.service_id, s.name
    ORDER BY count DESC
");
$stmt->execute();
$serviceStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
$totalAppointments = array_sum(array_column($serviceStats, 'count'));

// Top 3 Services
$topServices = array_slice($serviceStats, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Charts — BRB Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
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
        <a href="admin_charts.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
    <h1 class="text-center mb-10">Client Charts & Statistics</h1>

    <!-- Line Chart -->
    <div class="glass-card p-6 mb-8">
        <h2 class="mb-6">Monthly Appointment Trends</h2>
        <p class="text-gray-500 mb-4">Number of approved appointments per month</p>
        <div style="height: 300px;">
            <canvas id="lineChart"></canvas>
        </div>
    </div>

    <!-- Pie Chart + Top Services -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <div class="glass-card p-6 lg:col-span-2">
            <h2 class="mb-6">Most Booked Services</h2>
            <p class="text-gray-500 mb-4">Distribution of appointments by service type</p>
            <div style="height: 350px;">
                <canvas id="pieChart"></canvas>
            </div>
        </div>
        
        <!-- Top 3 Services -->
        <div class="glass-card p-6">
            <h2 class="mb-6">Top 3 Services</h2>
            <p class="text-gray-500 mb-6">Most popular among clients</p>
            <div class="space-y-4">
                <?php 
                $colors = ['bg-yellow-400', 'bg-gray-300', 'bg-amber-600'];
                if (empty($topServices)):
                ?>
                <p class="text-gray-500 text-center py-4">No data yet.</p>
                <?php else: foreach ($topServices as $i => $svc): 
                    $percent = $totalAppointments > 0 ? round(($svc['count'] / $totalAppointments) * 100, 1) : 0;
                ?>
                <div class="flex items-center gap-4">
                    <div class="w-10 h-10 flex items-center justify-center rounded-full <?= $colors[$i] ?> text-black font-bold text-lg">
                        <?= $i + 1 ?>
                    </div>
                    <div class="flex-1">
                        <p class="font-semibold"><?= htmlspecialchars($svc['name']) ?></p>
                        <p class="text-sm text-gray-500"><?= $svc['count'] ?> bookings (<?= $percent ?>%)</p>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</main>

<script>
// Line Chart
const lineCtx = document.getElementById('lineChart').getContext('2d');
new Chart(lineCtx, {
    type: 'line',
    data: {
        labels: <?= json_encode(array_keys($monthlyData)) ?>,
        datasets: [{
            label: 'Appointments',
            data: <?= json_encode(array_values($monthlyData)) ?>,
            borderColor: '#000000',
            backgroundColor: 'rgba(0,0,0,0.05)',
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#000000',
            pointBorderColor: '#ffffff',
            pointBorderWidth: 2,
            pointRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { grid: { display: false } }
        }
    }
});

// Pie Chart
const pieLabels = <?= json_encode(array_column($serviceStats, 'name')) ?>;
const pieData = <?= json_encode(array_column($serviceStats, 'count')) ?>;
const pieColors = ['#4B0082', '#F97316', '#EF4444', '#8B5CF6', '#EC4899', '#10B981', '#3B82F6'];

const pieCtx = document.getElementById('pieChart').getContext('2d');
new Chart(pieCtx, {
    type: 'doughnut',
    data: {
        labels: pieLabels,
        datasets: [{
            data: pieData,
            backgroundColor: pieColors.slice(0, pieLabels.length),
            borderWidth: 2,
            borderColor: '#ffffff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});
</script>

</body>
</html>
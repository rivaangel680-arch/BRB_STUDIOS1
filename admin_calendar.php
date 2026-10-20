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

// Handle unavailable dates
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['add_unavailable'])) {
        $date = $_POST['date'];
        $reason = trim($_POST['reason'] ?? '') ?: 'Unavailable';
        $stmt = $conn->prepare("INSERT INTO unavailable_dates (date, reason, created_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE reason = ?");
        $stmt->execute([$date, $reason, $_SESSION['user_id'], $reason]);
        header("Location: admin_calendar.php");
        exit;
    }
    if (isset($_POST['remove_unavailable'])) {
        $date = $_POST['date'];
        $stmt = $conn->prepare("DELETE FROM unavailable_dates WHERE date = ?");
        $stmt->execute([$date]);
        header("Location: admin_calendar.php");
        exit;
    }
}

// Get current month/year
$month = max(1, min(12, (int) ($_GET['month'] ?? date('n'))));
$year  = max(2000, min(2100, (int) ($_GET['year'] ?? date('Y'))));

// Navigation
$prevMonth = $month - 1;
$prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1;
$nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Get first/last day of month
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$lastDay = mktime(0, 0, 0, $month + 1, 0, $year);
$daysInMonth = date('t', $firstDay);
$startDayOfWeek = date('w', $firstDay);

// Get appointments for this month
$startDate = date('Y-m-01', $firstDay);
$endDate = date('Y-m-t', $firstDay);
$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, u.first_name, u.last_name, u.username
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.appointment_date BETWEEN ? AND ? AND a.status IN ('Approved', 'Rescheduled')
    ORDER BY a.appointment_date, a.appointment_time
");
$stmt->execute([$startDate, $endDate]);
$appointments = [];
foreach ($stmt as $a) {
    $appointments[$a['appointment_date']][] = $a;
}

// Get unavailable dates
$stmt = $conn->query("SELECT * FROM unavailable_dates ORDER BY date");
$unavailable = [];
foreach ($stmt as $u) {
    $unavailable[$u['date']] = $u['reason'];
}

// Admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar — BRB Studio</title>
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
        .calendar-day { min-height: 90px; transition: background 0.2s; }
        .calendar-day:hover { background: #f3f4f6; }
        .unavailable-day { background: #fee2e2; }
        .event-dot { width: 6px; height: 6px; border-radius: 50%; display: inline-block; }
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
        <a href="admin_calendar.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
    <!-- Calendar Header -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <h1><?= $months[$month - 1] ?> <?= $year ?></h1>
        <div class="flex items-center gap-4">
            <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn-black px-4 py-2 rounded-lg">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <select onchange="window.location.href='?month='+this.value+'&year=<?= $year ?>'" class="px-4 py-2 border border-gray-300 rounded-lg">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m == $month ? 'selected' : '' ?>><?= $months[$m - 1] ?></option>
                <?php endfor; ?>
            </select>
            <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn-black px-4 py-2 rounded-lg">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <a href="admin_calendar.php" class="border border-black px-4 py-2 rounded-lg">Today</a>
        </div>
    </div>

    <!-- Add Unavailable Date -->
    <div class="glass-card p-4 mb-6">
        <form method="POST" class="flex flex-col md:flex-row gap-4 items-end">
            <div>
                <label class="block mb-2 text-sm font-medium">Mark Unavailable Date</label>
                <input type="date" name="date" required class="px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="flex-1">
                <label class="block mb-2 text-sm font-medium">Reason (Optional)</label>
                <input type="text" name="reason" placeholder="e.g., Holiday" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <button type="submit" name="add_unavailable" class="btn-black px-4 py-2 rounded-lg">Set Unavailable</button>
        </form>
    </div>

    <!-- Calendar Grid -->
    <div class="glass-card p-6">
        <div class="grid grid-cols-7 mb-2">
            <?php $days = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat']; ?>
            <?php foreach ($days as $d): ?>
            <div class="text-center font-semibold py-2 text-gray-600"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7 gap-1 border-t border-l border-gray-200">
            <?php
            // Empty cells before first day
            for ($i = 0; $i < $startDayOfWeek; $i++) {
                echo '<div class="border-b border-r border-gray-200 p-2 bg-gray-50"></div>';
            }
            // Days of month
            for ($day = 1; $day <= $daysInMonth; $day++) {
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isUnavailable = isset($unavailable[$dateStr]);
                $hasAppointments = isset($appointments[$dateStr]);
                $todayClass = ($dateStr === date('Y-m-d')) ? 'ring-2 ring-black' : '';
                
                echo "<div class='border-b border-r border-gray-200 p-2 calendar-day $todayClass " . ($isUnavailable ? 'unavailable-day' : '') . "'>";
                echo "<div class='font-semibold text-sm mb-1'>$day</div>";
                
                if ($isUnavailable) {
                    echo "<div class='text-xs text-red-600 font-medium' title='" . htmlspecialchars($unavailable[$dateStr]) . "'>" . htmlspecialchars($unavailable[$dateStr]) . "</div>";
                    echo "<form method='POST' class='inline-block mt-1'>
                            <input type='hidden' name='date' value='$dateStr'>
                            <button type='submit' name='remove_unavailable' class='text-xs text-gray-500 hover:text-red-600'>Remove</button>
                          </form>";
                }
                
                if ($hasAppointments) {
                    foreach ($appointments[$dateStr] as $apt) {
                        $clientName = trim(($apt['first_name'] ?? '') . ' ' . ($apt['last_name'] ?? '')) ?: $apt['username'];
                        echo "<div class='text-xs bg-black text-white px-1 py-0.5 rounded mb-1 truncate' title='" . htmlspecialchars($clientName) . "'>" 
                            . date('g:i A', strtotime($apt['appointment_time'])) . " — " . htmlspecialchars($apt['service_name']) . "</div>";
                    }
                }
                
                echo "</div>";
            }
            ?>
        </div>
    </div>
</main>

</body>
</html>
<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Staff']);

$search = $_GET['search'] ?? '';
$dateFilter = $_GET['date'] ?? date('Y-m-d');

// Mark Attendance
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['mark_attendance'])) {
    $appointmentId = $_POST['appointment_id'];
    
    // Get appointment and client info
    $stmt = $conn->prepare("
        SELECT a.*, s.name AS service_name, u.first_name, u.last_name, u.email 
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.user_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();
    
    if ($appointment) {
        // Check if already marked
        $check = $conn->prepare("SELECT id FROM attendance WHERE appointment_id = ?");
        $check->execute([$appointmentId]);
        if (!$check->fetch()) {
            $insert = $conn->prepare("
                INSERT INTO attendance (appointment_id, user_id, client_name, email, service_name, attendance_date)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $clientName = trim(($appointment['first_name'] ?? '') . ' ' . ($appointment['last_name'] ?? ''));
            $insert->execute([
                $appointmentId,
                $appointment['user_id'],
                $clientName,
                $appointment['email'],
                $appointment['service_name'],
                date('Y-m-d H:i:s')
            ]);
            
            // Mark appointment as completed
            $update = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
            $update->execute([$appointmentId]);
        }
    }
    header("Location: staff_attendance.php?date=" . urlencode($dateFilter) . "&search=" . urlencode($search));
    exit;
}

// Get today's approved appointments for marking
$todayAppts = [];
if (!empty($dateFilter)) {
    $stmt = $conn->prepare("
        SELECT a.*, s.name AS service_name, u.first_name, u.last_name, u.email, u.age
        FROM appointments a
        JOIN services s ON a.service_id = s.id
        JOIN users u ON a.user_id = u.id
        WHERE a.appointment_date = ? AND a.status = 'Approved'
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute([$dateFilter]);
    $todayAppts = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get attendance records
$where = "WHERE 1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND client_name LIKE ?";
    $params[] = "%$search%";
}
if (!empty($dateFilter)) {
    $where .= " AND DATE(attendance_date) = ?";
    $params[] = $dateFilter;
}

try {
    $stmt = $conn->prepare("
        SELECT * FROM attendance $where ORDER BY attendance_date DESC
    ");
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $records = [];
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();
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
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="window.location.href='staff_profile.php'">
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
        <a href="staff_attendance.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <h1>Client Attendance</h1>
        <form method="GET" class="flex flex-col md:flex-row gap-2">
            <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" class="px-4 py-2 border border-gray-300 rounded-lg">
            <input type="text" name="search" placeholder="Search client..." value="<?= htmlspecialchars($search) ?>" class="px-4 py-2 border border-gray-300 rounded-lg">
            <button type="submit" class="btn-black px-4 py-2 rounded-lg">Filter</button>
        </form>
    </div>

    <!-- Mark Attendance Section -->
    <?php if (!empty($todayAppts)): ?>
    <h2 class="mb-4 text-xl">Mark Attendance — <?= date('F j, Y', strtotime($dateFilter)) ?></h2>
    <div class="glass-card p-6 mb-10">
        <div class="space-y-4">
            <?php foreach ($todayAppts as $apt): ?>
            <div class="flex flex-col md:flex-row md:justify-between md:items-center p-4 border border-gray-200 rounded-lg">
                <div>
                    <p class="font-semibold text-lg"><?= htmlspecialchars(trim(($apt['first_name'] ?? '') . ' ' . ($apt['last_name'] ?? '')) ?: 'Client') ?></p>
                    <p class="text-sm text-gray-500">
                        Email: <?= htmlspecialchars($apt['email'] ?? 'N/A') ?> | 
                        Age: <?= htmlspecialchars($apt['age'] ?? 'N/A') ?>
                    </p>
                    <p class="text-sm mt-1">
                        Service: <?= htmlspecialchars($apt['service_name']) ?> | 
                        Time: <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                    </p>
                </div>
                <form method="POST" class="mt-3 md:mt-0">
                    <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                    <button type="submit" name="mark_attendance" class="btn-black px-5 py-2 rounded-lg">
                        <i class="fa-solid fa-check mr-1"></i> Mark Present
                    </button>
                </form>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Attendance Records -->
    <h2 class="mb-4 text-xl">Attendance Records</h2>
    <?php if (empty($records)): ?>
    <div class="glass-card p-8 text-center">
        <i class="fa-solid fa-clipboard-list text-4xl text-gray-300 mb-4"></i>
        <p class="text-gray-500">No attendance records found.</p>
    </div>
    <?php else: ?>
    <div class="glass-card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="px-4 py-3 text-left">Client Name</th>
                        <th class="px-4 py-3 text-left">Email</th>
                        <th class="px-4 py-3 text-left">Service</th>
                        <th class="px-4 py-3 text-left">Date & Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <tr class="border-t border-gray-200 hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium"><?= htmlspecialchars($r['client_name']) ?></td>
                        <td class="px-4 py-3 text-sm"><?= htmlspecialchars($r['email']) ?></td>
                        <td class="px-4 py-3"><?= htmlspecialchars($r['service_name']) ?></td>
                        <td class="px-4 py-3 text-sm">
                            <?= date('M j, Y g:i A', strtotime($r['attendance_date'])) ?>
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
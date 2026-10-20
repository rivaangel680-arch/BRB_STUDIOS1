<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Staff']);

$today = date('Y-m-d');

// Get approved appointments - stays until end of appointment day
$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, u.first_name, u.last_name, u.username, u.email
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'Approved'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Mark completed if date has passed
foreach ($appointments as $apt) {
    if ($apt['appointment_date'] < $today && $apt['status'] === 'Approved') {
        $update = $conn->prepare("UPDATE appointments SET status = 'Completed' WHERE id = ?");
        $update->execute([$apt['id']]);
    }
}

// Refresh list after update
$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, u.first_name, u.last_name, u.username, u.email
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'Approved'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$staff = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment & Booking — BRB Studio</title>
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
        <a href="staff_appointments.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto">
    <h1 class="mb-8">Approved Appointments</h1>

    <?php if (empty($appointments)): ?>
    <div class="glass-card p-12 text-center">
        <i class="fa-solid fa-calendar-check text-5xl text-gray-300 mb-4"></i>
        <p class="text-xl text-gray-500">No approved appointments yet.</p>
    </div>
    <?php else: ?>
    <div class="space-y-6">
        <?php foreach ($appointments as $apt): ?>
        <div class="glass-card p-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4">
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-3">
                        <h2 class="text-xl font-semibold"><?= htmlspecialchars($apt['service_name']) ?></h2>
                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">Approved</span>
                    </div>
                    <p class="text-gray-600 mb-1">
                        <i class="fa-solid fa-user mr-2"></i>
                        <?= htmlspecialchars(trim(($apt['first_name'] ?? '') . ' ' . ($apt['last_name'] ?? '')) ?: $apt['username']) ?>
                    </p>
                    <p class="text-gray-600 mb-1">
                        <i class="fa-solid fa-envelope mr-2"></i><?= htmlspecialchars($apt['email']) ?>
                    </p>
                    <p class="text-gray-600 mb-1">
                        <i class="fa-solid fa-calendar mr-2"></i><?= date('F j, Y', strtotime($apt['appointment_date'])) ?>
                        at <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                    </p>
                    <?php if (!empty($apt['notes'])): ?>
                    <p class="text-gray-500 text-sm mt-2">
                        <i class="fa-solid fa-note-sticky mr-2"></i>Note: <?= htmlspecialchars($apt['notes']) ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

</body>
</html>
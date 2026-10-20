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

// Handle actions
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['approve'])) {
        $id = (int) $_POST['appointment_id'];
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Approved' WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_appointments.php");
        exit;
    }
    
    if (isset($_POST['reschedule'])) {
        $id = (int) $_POST['appointment_id'];
        $newDate = trim($_POST['new_date']);
        $newTime = trim($_POST['new_time']);
        $reason = trim($_POST['reason'] ?? '');
        
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Rescheduled', appointment_date = ?, appointment_time = ?, notes = CONCAT(IFNULL(notes,''), '\nRescheduled: ', ?) WHERE id = ?");
        $stmt->execute([$newDate, $newTime, $reason, $id]);
        header("Location: admin_appointments.php");
        exit;
    }
    
    if (isset($_POST['cancel'])) {
        $id = (int) $_POST['appointment_id'];
        $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_appointments.php");
        exit;
    }
}

// Get pending appointments
$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, u.first_name, u.last_name, u.username, u.email
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'Pending'
    ORDER BY a.appointment_date ASC, a.appointment_time ASC
");
$stmt->execute();
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        .btn-outline { 
            border: 1px solid #000000; 
            color: #000000; 
            transition: all 0.2s ease;
        }
        .btn-outline:hover { background: #f3f3f3; }
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-approved { background: #dcfce7; color: #166534; }
        .status-rescheduled { background: #dbeafe; color: #1e40af; }
        .status-cancelled { background: #f3f4f6; color: #4b5563; }
        h1 { font-size: 36px; font-weight: bold; }
        h2 { font-size: 22px; font-weight: 600; }
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
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
        <a href="admin_appointments.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
    <h1 class="mb-8">Appointment & Booking Requests</h1>

    <?php if (empty($appointments)): ?>
    <div class="glass-card p-12 text-center">
        <i class="fa-solid fa-calendar-check text-5xl text-gray-300 mb-4"></i>
        <p class="text-xl text-gray-500">No pending appointment requests.</p>
    </div>
    <?php else: ?>
    <div class="space-y-4">
        <?php foreach ($appointments as $apt): ?>
        <div class="glass-card p-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <!-- Appointment Info -->
                <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                        <h2><?= htmlspecialchars($apt['service_name']) ?></h2>
                        <span class="px-3 py-1 rounded-full text-sm font-medium status-pending">Pending</span>
                    </div>
                    <p class="text-gray-600 text-sm mb-1">
                        <strong>Client:</strong> 
                        <?= htmlspecialchars(trim(($apt['first_name'] ?? '') . ' ' . ($apt['last_name'] ?? '')) ?: $apt['username']) ?>
                        — <?= htmlspecialchars($apt['email']) ?>
                    </p>
                    <p class="text-gray-600 text-sm">
                        <strong>Date:</strong> <?= date('F j, Y', strtotime($apt['appointment_date'])) ?>
                        at <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                    </p>
                    <?php if (!empty($apt['notes'])): ?>
                    <p class="text-gray-500 text-sm mt-1">
                        <strong>Note:</strong> <?= htmlspecialchars($apt['notes']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-2 min-w-[280px]">
                    <form method="POST" class="flex-1">
                        <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                        <button type="submit" name="approve" class="btn-black px-4 py-2 rounded-lg w-full">
                            <i class="fa-solid fa-check mr-1"></i> Approve
                        </button>
                    </form>
                    <button onclick="openRescheduleModal(<?= $apt['id'] ?>, '<?= date('Y-m-d', strtotime($apt['appointment_date'])) ?>', '<?= date('H:i', strtotime($apt['appointment_time'])) ?>')" 
                            class="btn-outline px-4 py-2 rounded-lg flex-1">
                        <i class="fa-solid fa-clock mr-1"></i> Reschedule
                    </button>
                    <form method="POST" class="flex-1" onsubmit="return confirm('Cancel this appointment?')">
                        <input type="hidden" name="appointment_id" value="<?= $apt['id'] ?>">
                        <button type="submit" name="cancel" class="btn-outline px-4 py-2 rounded-lg w-full">
                            <i class="fa-solid fa-xmark mr-1"></i> Cancel
                        </button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Reschedule Modal -->
<div id="rescheduleModal" class="modal">
    <div class="glass-card p-6 w-full max-w-md m-4">
        <h2 class="mb-6">Reschedule Appointment</h2>
        <form method="POST" class="space-y-4">
            <input type="hidden" name="appointment_id" id="rs_appointment_id">
            <div>
                <label class="block mb-2">New Date</label>
                <input type="date" name="new_date" id="rs_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">New Time</label>
                <input type="time" name="new_time" id="rs_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">Reason (Optional)</label>
                <textarea name="reason" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Reason for rescheduling..."></textarea>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeRescheduleModal()" class="px-4 py-2 border border-gray-300 rounded-lg">Cancel</button>
                <button type="submit" name="reschedule" class="btn-black px-4 py-2 rounded-lg">Reschedule</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRescheduleModal(id, date, time) {
    document.getElementById('rs_appointment_id').value = id;
    document.getElementById('rs_date').value = date;
    document.getElementById('rs_time').value = time;
    document.getElementById('rescheduleModal').classList.add('active');
}
function closeRescheduleModal() {
    document.getElementById('rescheduleModal').classList.remove('active');
}
window.onclick = function(e) {
    if (e.target.id === 'rescheduleModal') e.target.classList.remove('active');
}
</script>

</body>
</html>
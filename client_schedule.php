<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Client']);

// Default profile picture for new clients who haven't uploaded their own yet.
// (To use a local copy instead, save the image as uploads/profile_default.png and
//  change this to 'uploads/profile_default.png' — the helper below handles both.)
if (!defined('DEFAULT_PROFILE_PIC')) {
    define('DEFAULT_PROFILE_PIC', 'https://i.pinimg.com/originals/d9/7b/bb/d97bbb08017ac2309307f0822e63d082.jpg');
}
if (!function_exists('profilePicUrl')) {
    function profilePicUrl($path) {
        $path = trim((string)$path);
        if ($path === '' || $path === 'uploads/profile_default.png') {
            $path = DEFAULT_PROFILE_PIC;
        }
        if (preg_match('#^(https?:)?//#', $path)) {
            return $path;               // external URL stays as-is
        }
        return '../' . ltrim($path, '/');   // local file lives in the root folder
    }
}

// Get user's appointments
$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, s.price, s.duration_minutes 
    FROM appointments a 
    JOIN services s ON a.service_id = s.id 
    WHERE a.user_id = ? 
    ORDER BY a.appointment_date DESC, a.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle cancel request
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['cancel_appointment_id'])) {
    $cancelId = $_POST['cancel_appointment_id'];
    $stmt = $conn->prepare("UPDATE appointments SET status = 'Cancelled' WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $stmt->execute([$cancelId, $_SESSION['user_id']]);

    if (function_exists('logActivity')) {
        logActivity('Cancelled Appointment', "Appointment #$cancelId");
    }

    header("Location: client_schedule.php");
    exit;
}

// Status colors
function getStatusStyle($status) {
    switch ($status) {
        case 'Pending': return 'bg-yellow-100 text-yellow-800 border-yellow-300';
        case 'Approved': return 'bg-green-100 text-green-800 border-green-300';
        case 'Rescheduled': return 'bg-blue-100 text-blue-800 border-blue-300';
        case 'Completed': return 'bg-gray-100 text-gray-800 border-gray-300';
        case 'Cancelled': return 'bg-red-100 text-red-800 border-red-300';
        case 'Declined': return 'bg-red-100 text-red-800 border-red-300';
        default: return 'bg-gray-100 text-gray-800 border-gray-300';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule — BRB Studio</title>
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
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="window.location.href='client_profile.php'">
            <img src="<?= htmlspecialchars(profilePicUrl($_SESSION['profile_pic'] ?? '')) ?>" 
                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-400" alt="Profile">
            <div>
                <p class="font-bold text-white text-lg"><?= htmlspecialchars($_SESSION['username']) ?></p>
                <p class="text-base text-gray-400">CLIENT</p>
            </div>
        </div>
        <a href="client_home.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-home w-5 text-center text-gray-300"></i><span>Home</span>
        </a>
        <a href="client_services.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-scissors w-5 text-center text-gray-400"></i><span>Services</span>
        </a>
        <a href="client_schedule.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
            <i class="fa-solid fa-calendar-check w-5 text-center text-gray-400"></i><span>Schedule</span>
        </a>
        <a href="client_calendar.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-days w-5 text-center text-gray-400"></i><span>Calendar</span>
        </a>
        <a href="client_contact.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-envelope w-5 text-center text-gray-400"></i><span>Contact</span>
        </a>
        <a href="client_report.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-star w-5 text-center text-gray-400"></i><span>Feedback</span>
        </a>
        <a href="client_support.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-circle-question w-5 text-center text-gray-400"></i><span>Support</span>
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
    <h1 class="text-center mb-10">My Appointments</h1>

    <?php if (empty($appointments)): ?>
    <div class="glass-card p-12 text-center max-w-lg mx-auto">
        <i class="fa-solid fa-calendar-day text-6xl text-gray-300 mb-6"></i>
        <p class="text-xl text-gray-500 mb-6">You have no appointments yet.</p>
        <a href="client_services.php" class="btn-black px-8 py-3 rounded-lg inline-block">Book Your First Appointment</a>
    </div>
    <?php else: ?>
    <div class="space-y-6 max-w-4xl mx-auto">
        <?php foreach ($appointments as $apt): ?>
        <div class="glass-card p-6">
            <div class="flex flex-col md:flex-row md:justify-between md:items-start gap-4 mb-4">
                <div>
                    <h2 class="text-xl font-semibold mb-1"><?= htmlspecialchars($apt['service_name']) ?></h2>
                    <p class="text-gray-600">
                        <i class="fa-solid fa-calendar mr-2"></i>
                        <?= date('l, F j, Y', strtotime($apt['appointment_date'])) ?>
                    </p>
                    <?php if ($apt['appointment_time']): ?>
                    <p class="text-gray-600">
                        <i class="fa-solid fa-clock mr-2"></i>
                        <?= date('g:i A', strtotime($apt['appointment_time'])) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <span class="px-4 py-2 rounded-full text-base font-medium border whitespace-nowrap <?= getStatusStyle($apt['status']) ?>">
                    <?= htmlspecialchars($apt['status']) ?>
                </span>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-base">
                <div>
                    <p class="text-gray-500">Price</p>
                    <p class="font-semibold">₱<?= number_format($apt['price'], 2) ?> • <?= $apt['duration_minutes'] ?> mins</p>
                </div>
                <?php if ($apt['admin_notes']): ?>
                <div>
                    <p class="text-gray-500">Message from BRB Studios</p>
                    <p class="italic text-gray-700"><?= htmlspecialchars($apt['admin_notes']) ?></p>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($apt['status'] === 'Pending'): ?>
            <div class="mt-4 pt-4 border-t flex justify-end">
                <form method="POST" onsubmit="return confirm('Are you sure you want to cancel this appointment?')">
                    <input type="hidden" name="cancel_appointment_id" value="<?= $apt['id'] ?>">
                    <button type="submit" class="px-6 py-2 border border-red-500 text-red-600 rounded-lg hover:bg-red-50">
                        Cancel Appointment
                    </button>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($apt['status'] === 'Rescheduled'): ?>
            <div class="mt-4 pt-4 border-t">
                <a href="client_appointment.php?reschedule_id=<?= $apt['id'] ?>" class="btn-black px-6 py-2 rounded-lg inline-block">
                    Select New Date
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

</body>
</html>
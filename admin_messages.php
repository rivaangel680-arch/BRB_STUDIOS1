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

// Mark as read
if (isset($_GET['mark_read'])) {
    $id = (int) $_GET['mark_read'];
    $stmt = $conn->prepare("UPDATE contact_messages SET is_read = 1 WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_messages.php");
    exit;
}

// Delete message
if (isset($_GET['delete'])) {
    $id = (int) $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM contact_messages WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin_messages.php");
    exit;
}

// Get all messages
$stmt = $conn->prepare("
    SELECT m.*, u.username 
    FROM contact_messages m
    JOIN users u ON m.user_id = u.id
    ORDER BY m.is_read ASC, m.created_at DESC
");
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();

$unreadCount = $conn->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages — BRB Studio</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { font-family: 'Times New Roman', serif; font-size: 17px; }
        body { 
            background: linear-gradient(135deg, #ffffff 0%, #e5e5e5 100%);
            min-height: 100vh; 
        }
        .glass-card { 
            background: rgba(255, 255, 255, 0.95); 
            backdrop-filter: blur(16px); 
            border-radius: 16px; 
            border: 1px solid #d4d4d4;
        }
        .sidebar-bg { 
            background: linear-gradient(180deg, #000000 0%, #171717 100%);
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
        .unread { border-left: 4px solid #000; background: #f9fafb; }
        .read { border-left: 4px solid #d1d5db; opacity: 0.85; }
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
        <a href="admin_attendance.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-clipboard-check w-5 text-center text-gray-400"></i><span>Attendance</span>
        </a>
        <a href="admin_reports.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
            <i class="fa-solid fa-star w-5 text-center text-gray-400"></i><span>Report</span>
            <?php if (!empty($unreadCount)): ?>
            <span class="bg-red-600 text-white text-xs rounded-full px-2 py-0.5 ml-auto"><?= (int) $unreadCount ?></span>
            <?php endif; ?>
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
    <div class="max-w-4xl mx-auto mb-8 flex items-center justify-between">
        <h1 class="text-3xl font-bold">Messages from Clients</h1>
        <a href="admin_reports.php" class="text-gray-600 hover:text-black"><i class="fa-solid fa-arrow-left mr-1"></i> Back to Report</a>
    </div>

    <?php if (empty($messages)): ?>
    <div class="glass-card p-10 text-center">
        <i class="fa-solid fa-inbox text-5xl text-gray-300 mb-4"></i>
        <p class="text-gray-500 text-lg">No messages yet.</p>
    </div>
    <?php else: ?>
    
    <div class="space-y-4 max-w-4xl mx-auto">
        <?php foreach ($messages as $msg): ?>
        <div class="glass-card p-5 <?= $msg['is_read'] ? 'read' : 'unread' ?>">
            <div class="flex justify-between items-start mb-3">
                <div>
                    <h3 class="font-bold text-xl"><?= htmlspecialchars($msg['subject']) ?></h3>
                    <p class="text-gray-600 text-sm">
                        <strong>From:</strong> <?= htmlspecialchars($msg['name']) ?> 
                        (<?= htmlspecialchars($msg['email']) ?>) — @<?= htmlspecialchars($msg['username']) ?>
                    </p>
                    <p class="text-gray-500 text-xs">
                        <i class="fa-solid fa-clock mr-1"></i> 
                        <?= date('F j, Y g:i A', strtotime($msg['created_at'])) ?>
                    </p>
                </div>
                <div class="flex gap-2 ml-4">
                    <?php if (!$msg['is_read']): ?>
                    <a href="admin_messages.php?mark_read=<?= $msg['id'] ?>" 
                       class="text-sm text-blue-600 hover:text-blue-800">
                        <i class="fa-solid fa-check mr-1"></i> Mark Read
                    </a>
                    <?php else: ?>
                    <span class="text-sm text-gray-400">
                        <i class="fa-solid fa-check-double mr-1"></i> Read
                    </span>
                    <?php endif; ?>
                    <a href="admin_messages.php?delete=<?= $msg['id'] ?>" 
                       class="text-sm text-red-500 hover:text-red-700 ml-3"
                       onclick="return confirm('Delete this message?')">
                        <i class="fa-solid fa-trash mr-1"></i> Delete
                    </a>
                </div>
            </div>
            <p class="text-gray-800 whitespace-pre-wrap bg-gray-50 p-4 rounded-lg">
                <?= htmlspecialchars($msg['message']) ?>
            </p>
        </div>
        <?php endforeach; ?>
    </div>
    
    <?php endif; ?>
</main>

</body>
</html>
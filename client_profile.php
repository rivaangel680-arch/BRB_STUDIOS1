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

// Image path helper: external URLs stay as-is, local files live in the root folder
function picPath($path, $fallback = DEFAULT_PROFILE_PIC) {
    $path = ($path && $path !== 'uploads/profile_default.png') ? $path : $fallback;
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}


// Get user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get completed appointments for history
$search = $_GET['search'] ?? '';
$historyQuery = "
    SELECT a.*, s.name as service_name 
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    WHERE a.user_id = ? AND a.status = 'Completed'
";
$params = [$_SESSION['user_id']];
if (!empty($search)) {
    $historyQuery .= " AND (s.name LIKE ? OR a.appointment_date LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$historyQuery .= " ORDER BY a.appointment_date DESC";
$stmt = $conn->prepare($historyQuery);
$stmt->execute($params);
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — BRB Studio</title>
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
        
        /* Flip Card Styles */
        .flip-card { 
            perspective: 1000px; 
            width: 100%; 
            max-width: 420px; 
            height: 580px; 
            margin: 0 auto;
            cursor: pointer;
        }
        .flip-card-inner { 
            position: relative; 
            width: 100%; 
            height: 100%; 
            text-align: center; 
            transition: transform 0.6s cubic-bezier(0.4, 0, 0.2, 1); 
            transform-style: preserve-3d;
        }
        .flip-card.flipped .flip-card-inner { transform: rotateY(180deg); }
        
        /* FRONT CARD */
        .flip-card-front {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            border-radius: 16px;
            background: linear-gradient(to bottom, #d9d9d9 0%, #888888 100%);
            overflow: hidden;
        }
        .front-edit-btn {
            position: absolute;
            top: 16px;
            right: 16px;
            color: #000000;
            font-size: 18px;
            z-index: 10;
        }
        .front-photo-area {
            width: 100%;
            height: 85%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .front-photo-area img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .front-name-overlay {
            position: absolute;
            bottom: 15%;
            left: 32px;
            text-align: left;
            color: #000000;
        }
        .front-first-name {
            font-size: 48px;
            font-weight: 300;
            line-height: 1;
        }
        .front-username {
            font-size: 44px;
            font-weight: 400;
            line-height: 1.1;
        }
        .front-full-name {
            font-size: 26px;
            font-weight: 400;
            margin-top: 6px;
        }
        .front-brand {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: #000000;
            padding: 16px;
            text-align: center;
        }
        .front-brand-text {
            font-size: 42px;
            font-weight: 900;
            color: white;
            letter-spacing: 8px;
        }
        
        /* BACK CARD */
        .flip-card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            -webkit-backface-visibility: hidden;
            backface-visibility: hidden;
            transform: rotateY(180deg);
            border-radius: 16px;
            background: linear-gradient(to bottom, #e0e0e0 0%, #707070 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px 32px;
        }
        .back-brand-top {
            font-size: 48px;
            font-weight: 900;
            color: #000000;
            letter-spacing: 8px;
            margin-bottom: 48px;
        }
        .back-info-line {
            color: #000000;
            font-size: 17px;
            margin-bottom: 16px;
            text-align: left;
        }
        
        h1 { font-size: 36px; font-weight: bold; }
        h2 { font-size: 26px; font-weight: 600; }
    </style>
</head>
<body class="flex">

<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="toggleFlip()">
            <img src="<?= htmlspecialchars(picPath($user['profile_pic'] ?? '')) ?>" 
                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-400" alt="Profile">
            <div>
                <p class="font-bold text-white text-lg"><?= htmlspecialchars($user['username']) ?></p>
                <p class="text-base text-gray-400">CLIENT</p>
            </div>
        </div>
        <a href="client_home.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-home w-5 text-center text-gray-300"></i><span>Home</span>
        </a>
        <a href="client_services.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-scissors w-5 text-center text-gray-400"></i><span>Services</span>
        </a>
        <a href="client_schedule.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
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

    <!-- Flip Card -->
    <div class="flip-card mb-12" id="profileCard" onclick="toggleFlip()">
        <div class="flip-card-inner">
            
            <!-- FRONT CARD -->
            <div class="flip-card-front">
                <a href="client_edit_profile.php" class="front-edit-btn" onclick="event.stopPropagation()">Edit</a>
                
                <div class="front-photo-area">
                    <img src="<?= htmlspecialchars(picPath($user['profile_pic'] ?? '')) ?>" 
                         alt="Profile Photo">
                </div>
                
                <div class="front-name-overlay">
                    <div class="front-username"><?= htmlspecialchars($user['username'] ?? 'user') ?></div>
                    <div class="front-full-name">
                        <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>
                    </div>
                </div>
                
                <div class="front-brand">
                    <span class="front-brand-text">BRB</span>
                </div>
            </div>

            <!-- BACK CARD -->
            <div class="flip-card-back">
                <div class="back-brand-top">BRB</div>
                
                <div class="back-info-line">
                    Birthday: <?= !empty($user['birthday']) ? date('F j, Y', strtotime($user['birthday'])) : 'Not set' ?>
                </div>
                <div class="back-info-line">
                    Age: <?= $user['age'] ?? 'Not set' ?>
                </div>
                <div class="back-info-line">
                    Gender: <?= htmlspecialchars($user['gender'] ?? 'Not set') ?>
                </div>
                <div class="back-info-line">
                    Contact: <?= htmlspecialchars($user['contact_number'] ?? 'Not set') ?>
                </div>
                <div class="back-info-line">
                    Email Address: <?= htmlspecialchars($user['email']) ?>
                </div>
                <div class="back-info-line">
                    Address: <?= htmlspecialchars($user['address'] ?? 'Not set') ?>
                </div>
            </div>
            
        </div>
    </div>

    <!-- Appointment History -->
    <div class="max-w-4xl mx-auto">
        <h2 class="text-center mb-6">Appointment History</h2>
        
        <!-- Search Bar -->
        <form method="GET" class="mb-8 flex gap-4">
            <input type="text" name="search" placeholder="Search by service or date..." 
                   value="<?= htmlspecialchars($search) ?>"
                   class="flex-1 px-6 py-3 border border-gray-300 rounded-lg text-lg">
            <button type="submit" class="btn-black px-6 py-3 rounded-lg">
                <i class="fa-solid fa-search"></i> Search
            </button>
        </form>

        <?php if (empty($history)): ?>
        <div class="glass-card p-10 text-center">
            <i class="fa-solid fa-clock-rotate-left text-5xl text-gray-300 mb-4"></i>
            <p class="text-xl text-gray-500">No completed appointments yet.</p>
        </div>
        <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($history as $item): ?>
            <div class="glass-card p-5 flex justify-between items-center">
                <div>
                    <p class="text-xl font-semibold"><?= htmlspecialchars($item['service_name']) ?></p>
                    <p class="text-gray-600">
                        <i class="fa-solid fa-calendar mr-2"></i>
                        <?= date('l, F j, Y', strtotime($item['appointment_date'])) ?>
                    </p>
                </div>
                <span class="px-4 py-2 bg-green-100 text-green-800 rounded-full font-medium">Completed</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function toggleFlip() {
    document.getElementById('profileCard').classList.toggle('flipped');
}
</script>

</body>
</html>
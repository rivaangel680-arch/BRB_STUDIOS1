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
function serviceImage($service) {
    $img = $service['image'] ?? '';
    if ($img === '') {
        return 'https://via.placeholder.com/400x500?text=' . urlencode($service['name']);
    }
    if (preg_match('#^(https?:)?//#', $img)) {
        return $img;
    }
    return '../' . ltrim($img, '/');
}


// Get all active services grouped by category
$stmt = $conn->prepare("SELECT * FROM services WHERE status = 'Active' ORDER BY category, name");
$stmt->execute();
$allServices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$packages = [];
$solo = [];
foreach ($allServices as $s) {
    if ($s['category'] === 'Package') {
        $packages[] = $s;
    } else {
        $solo[] = $s;
    }
}

// Get user info for sidebar
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Services — BRB Studio</title>
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
        .service-card { 
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
            background: #ffffff;
            border: 1px solid #e5e5e5;
        }
        .service-card:hover { 
            transform: translateY(-5px); 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        h1 { font-size: 36px; font-weight: bold; }
        h2 { font-size: 26px; font-weight: 600; }
        h3 { font-size: 28px; font-weight: normal; letter-spacing: 0.05em; }
    </style>
</head>
<body class="flex">

<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="window.location.href='client_profile.php'">
            <img src="<?= htmlspecialchars(profilePicUrl($user['profile_pic'] ?? '')) ?>" 
                 class="w-12 h-12 rounded-full object-cover border-2 border-gray-400" alt="Profile">
            <div>
                <p class="font-bold text-white text-lg"><?= htmlspecialchars($user['username']) ?></p>
                <p class="text-base text-gray-400">CLIENT</p>
            </div>
        </div>
        <a href="client_home.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-home w-5 text-center text-gray-300"></i><span>Home</span>
        </a>
        <a href="client_services.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
            <i class="fa-solid fa-scissors w-5 text-center text-gray-400"></i><span>Services</span>
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
    <h1 class="text-center mb-12">Our Services</h1>

    <!-- PACKAGES -->
    <h2 class="mb-6 text-gray-700">Packages</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
        <?php if (empty($packages)): ?>
        <p class="text-gray-500 text-lg">No packages available at the moment.</p>
        <?php else: ?>
        <?php foreach ($packages as $service): ?>
        <div class="service-card p-6 text-center" onclick="window.location.href='client_appointment.php?service_id=<?= $service['id'] ?>'">
            <img src="<?= htmlspecialchars(serviceImage($service)) ?>" 
                 class="w-full h-80 object-cover mb-6" alt="<?= htmlspecialchars($service['name']) ?>">
            <h3 class="mb-3"><?= htmlspecialchars($service['name']) ?></h3>
            <p class="text-xl text-gray-800 mb-4">Price: ₱<?= number_format($service['price'], 0) ?></p>
            <p class="text-gray-500 text-sm mb-6 leading-relaxed">
                <?= htmlspecialchars($service['description']) ?>
            </p>
            <div class="text-3xl font-bold tracking-widest">BRB</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- SOLO SERVICES -->
    <h2 class="mb-6 text-gray-700">Solo Services</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        <?php if (empty($solo)): ?>
        <p class="text-gray-500 text-lg">No solo services available at the moment.</p>
        <?php else: ?>
        <?php foreach ($solo as $service): ?>
        <div class="service-card p-6 text-center" onclick="window.location.href='client_appointment.php?service_id=<?= $service['id'] ?>'">
            <img src="<?= htmlspecialchars(serviceImage($service)) ?>" 
                 class="w-full h-80 object-cover mb-6" alt="<?= htmlspecialchars($service['name']) ?>">
            <h3 class="mb-3"><?= htmlspecialchars($service['name']) ?></h3>
            <p class="text-xl text-gray-800 mb-4">Price: ₱<?= number_format($service['price'], 0) ?></p>
            <p class="text-gray-500 text-sm mb-6 leading-relaxed">
                <?= htmlspecialchars($service['description']) ?>
            </p>
            <div class="text-3xl font-bold tracking-widest">BRB</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</main>

</body>
</html>
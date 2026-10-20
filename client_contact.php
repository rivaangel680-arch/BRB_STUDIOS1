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

$success = '';
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    if (!empty($name) && !empty($email) && !empty($subject) && !empty($message)) {
        $stmt = $conn->prepare("INSERT INTO contact_messages (user_id, name, email, subject, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $name, $email, $subject, $message]);
        $success = 'Your message has been sent successfully! We will get back to you as soon as possible.';
    }
}

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us — BRB Studio</title>
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
        <a href="client_services.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-scissors w-5 text-center text-gray-400"></i><span>Services</span>
        </a>
        <a href="client_schedule.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-check w-5 text-center text-gray-400"></i><span>Schedule</span>
        </a>
        <a href="client_calendar.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-days w-5 text-center text-gray-400"></i><span>Calendar</span>
        </a>
        <a href="client_contact.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
    <h1 class="text-center mb-10">Contact Us</h1>

    <?php if ($success): ?>
    <div class="glass-card p-6 max-w-2xl mx-auto bg-green-50 border-green-200 mb-8">
        <p class="text-green-700 text-lg"><?= htmlspecialchars($success) ?></p>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 max-w-5xl mx-auto">
        <!-- Contact Info -->
        <div class="glass-card p-8">
            <h2 class="mb-6">Get In Touch</h2>
            <div class="space-y-6">
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-location-dot text-2xl mt-1"></i>
                    <div>
                        <p class="font-semibold">Address</p>
                        <p class="text-gray-600">123 Studio Lane, Calamba, Laguna, Philippines</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-phone text-2xl mt-1"></i>
                    <div>
                        <p class="font-semibold">Phone</p>
                        <p class="text-gray-600">+63 917 123 4567</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-envelope text-2xl mt-1"></i>
                    <div>
                        <p class="font-semibold">Email</p>
                        <p class="text-gray-600">contact@brbstudio.com</p>
                    </div>
                </div>
                <div class="flex items-start gap-4">
                    <i class="fa-solid fa-clock text-2xl mt-1"></i>
                    <div>
                        <p class="font-semibold">Business Hours</p>
                        <p class="text-gray-600">Monday — Saturday: 8:00 AM — 6:00 PM</p>
                        <p class="text-gray-600">Sunday: Closed</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Contact Form -->
        <div class="glass-card p-8">
            <h2 class="mb-6">Send Us a Message</h2>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block mb-2">Your Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''))) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                </div>
                <div>
                    <label class="block mb-2">Your Email</label>
                    <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                </div>
                <div>
                    <label class="block mb-2">Subject</label>
                    <input type="text" name="subject" placeholder="How can we help you?"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg" required>
                </div>
                <div>
                    <label class="block mb-2">Message</label>
                    <textarea name="message" rows="5" placeholder="Write your message here..."
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg" required></textarea>
                </div>
                <button type="submit" class="btn-black px-8 py-3 rounded-lg w-full text-lg">Send Message</button>
            </form>
        </div>
    </div>
</main>

</body>
</html>
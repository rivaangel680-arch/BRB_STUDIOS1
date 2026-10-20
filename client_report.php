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
    $rating = intval($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating >= 1 && $rating <= 5 && !empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO feedback (user_id, rating, comment, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$_SESSION['user_id'], $rating, $comment]);

        if (function_exists('logActivity')) {
            logActivity('Submitted Feedback', "Rating: $rating stars");
        }

        $success = 'Thank you for your feedback! We appreciate your time and input.';
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
    <title>Feedback — BRB Studio</title>
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
        .star { font-size: 2rem; cursor: pointer; color: #d1d5db; transition: color 0.2s; }
        .star:hover, .star.active { color: #f59e0b; }
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
        <a href="client_contact.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-envelope w-5 text-center text-gray-400"></i><span>Contact</span>
        </a>
        <a href="client_report.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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

<main class="flex-1 p-6 md:p-10 overflow-y-auto flex items-center justify-center">
    <div class="w-full max-w-lg">
        <h1 class="text-center mb-10">Share Your Feedback</h1>

        <?php if ($success): ?>
        <div class="glass-card p-6 bg-green-50 border-green-200 mb-8 text-center">
            <i class="fa-solid fa-check-circle text-green-600 text-4xl mb-4"></i>
            <p class="text-green-700 text-lg"><?= htmlspecialchars($success) ?></p>
        </div>
        <?php endif; ?>

        <div class="glass-card p-8">
            <form method="POST" id="feedbackForm">
                <div class="text-center mb-8">
                    <p class="text-lg mb-4">How would you rate your experience with us?</p>
                    <div class="flex justify-center gap-2" id="starRating">
                        <span class="star" data-value="1"><i class="fa-solid fa-star"></i></span>
                        <span class="star" data-value="2"><i class="fa-solid fa-star"></i></span>
                        <span class="star" data-value="3"><i class="fa-solid fa-star"></i></span>
                        <span class="star" data-value="4"><i class="fa-solid fa-star"></i></span>
                        <span class="star" data-value="5"><i class="fa-solid fa-star"></i></span>
                    </div>
                    <input type="hidden" name="rating" id="ratingInput" value="0" required>
                </div>

                <div class="mb-6">
                    <label class="block mb-2">Your Feedback</label>
                    <textarea name="comment" rows="6" placeholder="Tell us what you think... What did we do well, and where can we improve? We value every word you share!"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg" required></textarea>
                </div>

                <button type="submit" class="btn-black px-8 py-3 rounded-lg w-full text-lg font-bold">Submit Feedback</button>
            </form>
        </div>
    </div>
</main>

<script>
const stars = document.querySelectorAll('.star');
const ratingInput = document.getElementById('ratingInput');
let selectedRating = 0;

stars.forEach(star => {
    star.addEventListener('mouseover', () => {
        const value = parseInt(star.dataset.value);
        highlightStars(value);
    });
    star.addEventListener('mouseout', () => {
        highlightStars(selectedRating);
    });
    star.addEventListener('click', () => {
        selectedRating = parseInt(star.dataset.value);
        ratingInput.value = selectedRating;
        highlightStars(selectedRating);
    });
});

function highlightStars(count) {
    stars.forEach((star, index) => {
        if (index < count) {
            star.classList.add('active');
        } else {
            star.classList.remove('active');
        }
    });
}

document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    if (selectedRating === 0) {
        e.preventDefault();
        alert('Please select a star rating before submitting.');
    }
});
</script>

</body>
</html>
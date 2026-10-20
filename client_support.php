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

$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Support — BRB Studio</title>
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
        details[open] summary { margin-bottom: 0.5rem; }
        summary { cursor: pointer; font-weight: 600; list-style: none; }
        summary::-webkit-details-marker { display: none; }
        h1 { font-size: 36px; font-weight: bold; }
        h2 { font-size: 26px; font-weight: 600; }
        h3 { font-size: 22px; font-weight: 600; }
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
        <a href="client_report.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-star w-5 text-center text-gray-400"></i><span>Feedback</span>
        </a>
        <a href="client_support.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
    <h1 class="text-center mb-10">Help & Support</h1>

    <div class="max-w-3xl mx-auto space-y-6">
        <!-- Profile Section -->
        <div class="glass-card p-6">
            <h2 class="mb-4 flex items-center gap-2"><i class="fa-solid fa-user"></i> Client Profile</h2>
            <p class="mb-4">You can view your Client Profile by clicking your picture located at the top of the navigation bar.</p>
            <p class="mb-2">Before making an appointment, please complete and edit your profile with the following details:</p>
            <ul class="list-disc list-inside text-gray-700 space-y-1 ml-4">
                <li>Birthday</li>
                <li>Age</li>
                <li>Gender</li>
                <li>Contact Number</li>
                <li>Email Address</li>
                <li>Address</li>
                <li>Profile Picture</li>
            </ul>
        </div>

        <!-- Appointment Section -->
        <div class="glass-card p-6">
            <h2 class="mb-4 flex items-center gap-2"><i class="fa-solid fa-calendar-check"></i> Appointment</h2>
            <ul class="space-y-4 text-gray-700">
                <li>
                    <strong>•</strong> When you enter the appointment page, you will immediately be able to select a service. However, you may also browse the services first if you wish. Once you have chosen the service you want to book, simply click on it, and you will be directed straight to the appointment form.
                </li>
                <li>
                    <strong>•</strong> On the appointment page, you will clearly see which dates are already taken, dates that are unavailable, and dates that are open for booking. Simply click on an available date to proceed.
                </li>
                <li>
                    <strong>•</strong> After selecting a date, you will be shown a summary of your booking including the service, date, and your personal information. Double-check everything and click Submit Appointment when you're ready.
                </li>
                <li>
                    <strong>•</strong> Once submitted, your appointment will be marked as Pending. Please wait for approval from our team. You can check the status of your appointments anytime on your Schedule page.
                </li>
            </ul>
        </div>

        <!-- FAQ Section -->
        <div class="glass-card p-6">
            <h2 class="mb-4 flex items-center gap-2"><i class="fa-solid fa-circle-question"></i> Frequently Asked Questions</h2>
            <div class="space-y-4">
                <details class="border-b border-gray-200 pb-3">
                    <summary class="flex justify-between items-center">
                        Can I cancel my appointment?
                        <i class="fa-solid fa-chevron-down text-sm"></i>
                    </summary>
                    <p class="text-gray-600 mt-2">Yes — you can cancel your appointment as long as it is still in Pending status. Go to your Schedule page, find your appointment, and click the Cancel button.</p>
                </details>
                <details class="border-b border-gray-200 pb-3">
                    <summary class="flex justify-between items-center">
                        <i class="fa-solid fa-chevron-down text-sm"></i>
                        How will I know if my appointment is approved?
                    </summary>
                    <p class="text-gray-600 mt-2">You can check the status anytime on your Schedule page. You will also receive a notification at your registered email address once your appointment has been reviewed.</p>
                </details>
                <details class="border-b border-gray-200 pb-3">
                    <summary class="flex justify-between items-center">
                        <i class="fa-solid fa-chevron-down text-sm"></i>
                        What should I do if I need to reschedule?
                    </summary>
                    <p class="text-gray-600 mt-2">If your appointment has already been approved, please contact us through the Contact page or send us a message, and we will assist you with rescheduling. You may also cancel your pending appointment and book a new one.</p>
                </details>
                <details class="border-b border-gray-200 pb-3">
                    <summary class="flex justify-between items-center">
                        <i class="fa-solid fa-chevron-down text-sm"></i>
                        Why is it important to complete my profile?
                    </summary>
                    <p class="text-gray-600 mt-2">Your profile information helps us serve you better. Your details will be included automatically every time you book an appointment, so keeping them up to date ensures everything goes smoothly.</p>
                </details>
            </div>
        </div>

        <!-- Need More Help -->
        <div class="glass-card p-6 text-center">
            <h3 class="mb-4">Need More Help?</h3>
            <p class="text-gray-600 mb-6">If you have any other questions or concerns, please don't hesitate to reach out to us.</p>
            <a href="client_contact.php" class="btn-black px-8 py-3 rounded-lg inline-block">Contact Us</a>
        </div>
    </div>
</main>

</body>
</html>
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

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get session data
$serviceId = $_SESSION['selected_service_id'] ?? null;
$serviceName = $_SESSION['selected_service_name'] ?? null;
$selectedDate = $_SESSION['selected_date'] ?? null;
$bookingType = $_SESSION['booking_type'] ?? null;          // 'Solo' or 'Group'
$selectedTime = $_SESSION['selected_time'] ?? null;        // Modeling Solo only, e.g. 10:00:00
$timeSetByBrb = !empty($_SESSION['time_set_by_brb']);      // Modeling Group: BRB sets 9 AM or 3 PM

if (!$serviceId || !$selectedDate || !$bookingType) {
    header("Location: client_services.php");
    exit;
}

// Get service details
$stmt = $conn->prepare("SELECT * FROM services WHERE id = ?");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

// Same rules as client_appointment.php: Modeling = 7 slots, other services = 4 (hidden)
$isModeling = stripos($service['name'], 'model') !== false;
$maxSlots = $isModeling ? 7 : 4;
$confirmError = '';

// A Modeling SOLO session lasts this many hours (same as client_appointment.php)
$soloDuration = 3;

// True if a session starting at $time would overlap any existing Solo session
function soloOverlaps($time, $takenStarts, $hours) {
    $toMin = function ($t) { [$h, $m] = array_map('intval', explode(':', substr($t, 0, 5))); return $h * 60 + $m; };
    $d = $hours * 60;
    $t = $toMin($time);
    foreach ($takenStarts as $s) {
        $ss = $toMin($s);
        if ($t < $ss + $d && $t + $d > $ss) return true;
    }
    return false;
}

// Handle submission
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['submit_appointment'])) {
    // Re-check availability right before saving (someone else may have booked meanwhile)
    $stmt = $conn->prepare("
        SELECT booking_type, appointment_time, COUNT(*) AS cnt
        FROM appointments
        WHERE service_id = ? AND appointment_date = ? AND status IN ('Pending', 'Approved')
        GROUP BY booking_type, appointment_time
    ");
    $stmt->execute([$serviceId, $selectedDate]);
    $total = 0; $group = 0; $takenSoloTimes = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $total += (int)$r['cnt'];
        if ($r['booking_type'] === 'Solo') {
            if (!empty($r['appointment_time'])) $takenSoloTimes[] = substr($r['appointment_time'], 0, 5);
        } else {
            $group += (int)$r['cnt'];
        }
    }
    if ($isModeling) {
        if ($bookingType === 'Solo') {
            // Solo: the chosen 3 hours must still be free
            $remaining = 1;
            $okType = $selectedTime && !soloOverlaps($selectedTime, $takenSoloTimes, $soloDuration);
        } else {
            // Group: needs one of the 7 slots
            $remaining = max(0, $maxSlots - $group);
            $okType = true;
        }
    } else {
        $remaining = max(0, $maxSlots - $total);
        $okType = true;
    }

    if ($remaining <= 0 || !$okType) {
        $confirmError = 'Sorry, that time (or part of it) was just taken by someone else. Please go back and choose again.';
    } else {
        $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        $birthday = $user['birthday'] ?? null;
        $age = $user['age'] ?? null;
        $gender = $user['gender'] ?? null;
        $contactNumber = $user['contact_number'] ?? '';
        $email = $user['email'] ?? '';
        $address = $user['address'] ?? '';

        // Values we would like to save
        $data = [
            'user_id'          => $_SESSION['user_id'],
            'service_id'       => $serviceId,
            'full_name'        => $fullName,
            'birthday'         => $birthday,
            'age'              => $age,
            'gender'           => $gender,
            'contact_number'   => $contactNumber,
            'email'            => $email,
            'address'          => $address,
            'appointment_date' => $selectedDate,
            'booking_type'     => $bookingType,
            'status'           => 'Pending',
        ];
        // Modeling Solo has a chosen time; Modeling Group time is set by BRB later (left empty)
        if ($selectedTime) {
            $data['appointment_time'] = $selectedTime;
        }

        // Only save into columns that really exist in the appointments table
        // (client details are already stored in the users table and joined when needed)
        $existing = $conn->query("SHOW COLUMNS FROM appointments")->fetchAll(PDO::FETCH_COLUMN);
        $data = array_intersect_key($data, array_flip($existing));

        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $stmt = $conn->prepare("INSERT INTO appointments (" . implode(', ', array_keys($data)) . ") VALUES ($placeholders)");
        $stmt->execute(array_values($data));

        $appointmentId = $conn->lastInsertId();
        // Activity log is optional: skip it if config.php has no logActivity() function
        if (function_exists('logActivity')) {
            try {
                logActivity('Created Appointment', "Appointment #$appointmentId - {$service['name']} ($bookingType) on $selectedDate");
            } catch (Throwable $e) {
                // never block the booking because of logging
            }
        }

        // Clear session
        unset($_SESSION['selected_service_id']);
        unset($_SESSION['selected_service_name']);
        unset($_SESSION['selected_date']);
        unset($_SESSION['booking_type']);
        unset($_SESSION['selected_time']);
        unset($_SESSION['time_set_by_brb']);

        header("Location: client_finished.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Appointment — BRB Studio</title>
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
    <h1 class="text-center mb-10">Confirm Your Appointment</h1>
    
    <div class="max-w-2xl mx-auto space-y-8">
        <?php if ($confirmError): ?>
        <div class="glass-card p-4 bg-red-50 border-red-200 text-red-600"><?= htmlspecialchars($confirmError) ?></div>
        <?php endif; ?>

        <!-- Selected Service -->
        <div class="glass-card p-8">
            <h2 class="mb-6">Selected Service</h2>
            <p class="text-xl font-semibold mb-2"><?= htmlspecialchars($service['name']) ?></p>
            <p class="text-gray-600 mb-4"><?= htmlspecialchars($service['description']) ?></p>
            <p class="text-2xl font-bold">₱<?= number_format($service['price'], 2) ?> — <?= $service['duration_minutes'] ?> minutes</p>
        </div>

        <!-- Appointment Date -->
        <div class="glass-card p-8">
            <h2 class="mb-6">Appointment Date</h2>
            <p class="text-xl font-semibold"><?= date('F j, Y', strtotime($selectedDate)) ?></p>
            <?php if ($isModeling): ?>
            <p class="mt-3"><span class="text-gray-600">Booking Type:</span> <span class="font-semibold"><?= htmlspecialchars($bookingType) ?> Session</span></p>
            <?php if ($selectedTime): ?>
            <p class="mt-1"><span class="text-gray-600">Time:</span> <span class="font-semibold"><?= date('g:i A', strtotime($selectedTime)) ?> – <?= date('g:i A', strtotime($selectedTime) + $soloDuration * 3600) ?> (<?= $soloDuration ?> hours)</span></p>
            <?php elseif ($timeSetByBrb): ?>
            <p class="mt-1"><span class="text-gray-600">Time:</span> <span class="font-semibold">9:00 AM or 3:00 PM — BRB will set the time</span></p>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Your Information -->
        <div class="glass-card p-8">
            <h2 class="mb-6">Your Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600">Full Name</p>
                    <p class="font-semibold"><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Not set') ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Birthday</p>
                    <p class="font-semibold"><?= $user['birthday'] ? date('F j, Y', strtotime($user['birthday'])) : 'Not set' ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Age</p>
                    <p class="font-semibold"><?= $user['age'] ?: 'Not set' ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Gender</p>
                    <p class="font-semibold"><?= htmlspecialchars($user['gender'] ?: 'Not set') ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Contact Number</p>
                    <p class="font-semibold"><?= htmlspecialchars($user['contact_number'] ?: 'Not set') ?></p>
                </div>
                <div>
                    <p class="text-gray-600">Email Address</p>
                    <p class="font-semibold"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <div class="md:col-span-2">
                    <p class="text-gray-600">Address</p>
                    <p class="font-semibold"><?= htmlspecialchars($user['address'] ?: 'Not set') ?></p>
                </div>
            </div>
            <p class="text-base text-gray-500 mt-6">
                <a href="client_profile.php" class="underline">Edit your profile</a> if any information needs updating.
            </p>
        </div>

        <!-- Submit Buttons -->
        <div class="flex flex-col md:flex-row gap-4 justify-center pt-4">
            <a href="client_appointment.php?service_id=<?= (int)$serviceId ?>" class="px-8 py-3 border border-black rounded-lg text-center">Back to Calendar</a>
            <form method="POST" class="inline">
                <button type="submit" name="submit_appointment" class="btn-black px-10 py-3 rounded-lg text-lg font-bold">
                    Submit Appointment
                </button>
            </form>
        </div>
    </div>
</main>

</body>
</html>
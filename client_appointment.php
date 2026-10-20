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
        return 'https://via.placeholder.com/300x400?text=' . urlencode($service['name']);
    }
    if (preg_match('#^(https?:)?//#', $img)) {
        return $img;
    }
    return '../' . ltrim($img, '/');
}


// Get user profile info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Get selected service
if (!isset($_GET['service_id'])) {
    header("Location: client_services.php");
    exit;
}

$stmt = $conn->prepare("SELECT * FROM services WHERE id = ? AND status = 'Active'");
$stmt->execute([$_GET['service_id']]);
$service = $stmt->fetch();

if (!$service) {
    header("Location: client_services.php");
    exit;
}

// Get unavailable dates
$unavailableDates = $conn->query("SELECT date FROM unavailable_dates")->fetchAll(PDO::FETCH_COLUMN);

// Slots per day, counted per service: Modeling = 7 slots, every other service = 4
$isModeling    = stripos($service['name'], 'model') !== false;
$isPhotography = stripos($service['name'], 'photo') !== false;
$isMakeup      = (bool)preg_match('/make[\s-]?up/i', $service['name']);
// Photography and Makeup are booked by TIME (client picks a start time, no Solo / Group choice)
$isTimed = $isPhotography || $isMakeup;
$maxSlotsPerDay = $isModeling ? 7 : 4;

// Bookings for THIS service, grouped by date, booking type and time
$bookedStmt = $conn->prepare("
    SELECT appointment_date, booking_type, appointment_time, COUNT(*) AS cnt
    FROM appointments
    WHERE service_id = ? AND status IN ('Pending', 'Approved')
    GROUP BY appointment_date, booking_type, appointment_time
");
$bookedStmt->execute([$service['id']]);
$bookedData = [];
while ($row = $bookedStmt->fetch(PDO::FETCH_ASSOC)) {
    $date = $row['appointment_date'];
    if (!isset($bookedData[$date])) {
        $bookedData[$date] = ['total' => 0, 'solo' => 0, 'group' => 0, 'solo_times' => []];
    }
    $cnt = (int)$row['cnt'];
    $bookedData[$date]['total'] += $cnt;
    if ($row['booking_type'] === 'Solo') {
        $bookedData[$date]['solo'] += $cnt;
        if (!empty($row['appointment_time'])) {
            $bookedData[$date]['solo_times'][] = substr($row['appointment_time'], 0, 5);   // e.g. 13:00
        }
    } else {
        $bookedData[$date]['group'] += $cnt;   // Group (and any older bookings without a type)
    }
}

// Group slots left on a date.
// Modeling: only Group bookings use the 7 slots (Solo is booked by time, see $soloTimes).
// Other services: slots are not shown to the client; each booking just takes 1 (hidden limit).
function slotsRemaining($info, $max, $isModeling) {
    if (!$info) return $max;
    if ($isModeling) {
        return max(0, $max - $info['group']);
    }
    return max(0, $max - $info['total']);
}

// Times a client can pick for a Modeling SOLO session (edit to match the studio's hours)
$soloTimes = ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00', '17:00'];

// How many hours one booking lasts (the chosen hour + the next ones become taken):
// Photography = 2 hours, Makeup = 3 hours, Modeling SOLO = 3 hours
$soloDuration = $isPhotography ? 2 : 3;

// True if a new session starting at $time would overlap any existing Solo session
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

$bookingError = '';

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $selectedDate = $_POST['selected_date'] ?? '';
    $bookingType = $_POST['booking_type'] ?? '';
    $selectedTime = $_POST['selected_time'] ?? '';
    if (!$isModeling) {
        $bookingType = 'Solo';   // only Modeling offers a Solo / Group choice
    }

    $info = $bookedData[$selectedDate] ?? null;
    $slotsLeft = slotsRemaining($info, $maxSlotsPerDay, $isModeling);
    $pickedTime = substr($selectedTime, 0, 5);

    // Photography / Makeup always need a time; Modeling needs one only for Solo
    $needsTime = $isTimed || ($isModeling && $bookingType === 'Solo');

    if ($needsTime) {
        // The chosen time must be a real option and its hours must not overlap another booked session
        $typeOk = in_array($pickedTime, $soloTimes, true)
            && !soloOverlaps($pickedTime, $info['solo_times'] ?? [], $soloDuration);
    } else {
        // Group (and other services): needs a free slot
        $typeOk = $slotsLeft > 0;
    }

    $valid = in_array($bookingType, ['Solo', 'Group'], true)
        && preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)
        && $selectedDate >= date('Y-m-d')
        && !in_array($selectedDate, $unavailableDates)
        && $typeOk;

    if ($valid) {
        $_SESSION['selected_service_id'] = $service['id'];
        $_SESSION['selected_service_name'] = $service['name'];
        $_SESSION['selected_date'] = $selectedDate;
        $_SESSION['booking_type'] = $bookingType;
        // Photography / Makeup / Modeling Solo: time chosen by the client. Modeling Group: 9 AM or 3 PM, set by BRB later.
        $_SESSION['selected_time'] = $needsTime ? $pickedTime . ':00' : null;
        $_SESSION['time_set_by_brb'] = ($isModeling && $bookingType === 'Group');
        header("Location: client_confirm.php");
        exit;
    }
    $bookingError = 'Sorry, that date or booking type is no longer available. Please choose again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment — BRB Studio</title>
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
        .day-cell { min-height: 85px; cursor: pointer; border: 1px solid #e5e5e5; text-align: center; padding: 0.4rem; transition: all 0.2s; }
        .day-cell:hover { background: #e0f2fe; border-color: #93c5fd; }
        .day-unavailable { background: #e5e5e5; color: #999; cursor: not-allowed; }
        .day-taken { background: #fee2e2; color: #991b1b; cursor: not-allowed; border-color: #fecaca; }
        .day-available { background: #ffffff; }
        .day-selected { background: #dbeafe; border-color: #3b82f6; }
        .slot-text { font-size: 11px; line-height: 1.2; margin-top: 2px; }
        .slot-remaining { color: #16a34a; font-weight: 600; }
        .slot-full { color: #dc2626; font-weight: 600; }
        .booking-option { transition: all 0.2s; }
        .booking-option:hover { border-color: #000; }
        .booking-option.selected { border-color: #000; background: #f9fafb; }
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
        <a href="client_services.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
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
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
        
        <!-- LEFT: Selected Service & Client Info -->
        <div class="space-y-6">
            <!-- Selected Service -->
            <div class="text-center">
                <img src="<?= htmlspecialchars(serviceImage($service)) ?>" 
                     class="w-full h-80 object-cover mb-6" alt="<?= htmlspecialchars($service['name']) ?>">
                <h3 class="mb-3"><?= htmlspecialchars($service['name']) ?></h3>
                <p class="text-xl text-gray-800 mb-4">Price: ₱<?= number_format($service['price'], 0) ?></p>
                <div class="text-3xl font-bold tracking-widest mb-8">BRB</div>
            </div>

            <!-- Your Information -->
            <div class="text-left space-y-2 text-lg">
                <p><strong>Name:</strong> <?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Not set') ?></p>
                <p><strong>Age:</strong> <?= $user['age'] ?: 'Not set' ?></p>
                <p><strong>Gender:</strong> <?= htmlspecialchars($user['gender'] ?: 'Not set') ?></p>
                <p><strong>Contact:</strong> <?= htmlspecialchars($user['contact_number'] ?: 'Not set') ?></p>
                <p class="text-sm text-gray-500 mt-4">
                    <a href="client_profile.php" class="underline">Edit your profile</a> to update this information
                </p>
                <hr class="my-4">
                <a href="client_services.php" class="text-black underline text-base">← Change Service</a>
            </div>
        </div>

        <!-- RIGHT: Calendar & Booking Options -->
        <div class="lg:col-span-2 space-y-6">
            <?php if ($bookingError): ?>
            <div class="glass-card p-4 bg-red-50 border-red-200 text-red-600"><?= htmlspecialchars($bookingError) ?></div>
            <?php endif; ?>
            <!-- Calendar -->
            <div class="glass-card p-8">
                <h2 class="text-center mb-8 text-2xl font-normal tracking-wider" id="monthTitle">JANUARY (2026)</h2>
                <?php if ($isModeling): ?>
                <p class="text-center text-gray-500 -mt-4 mb-6"><?= $maxSlotsPerDay ?> slots per day for <?= htmlspecialchars($service['name']) ?></p>
                <?php elseif ($isTimed): ?>
                <p class="text-center text-gray-500 -mt-4 mb-6">Each <?= htmlspecialchars($service['name']) ?> session takes <?= (int)$soloDuration ?> hours</p>
                <?php endif; ?>

                <!-- Calendar Legend -->
                <div class="flex flex-wrap gap-4 mb-6 text-sm justify-center">
                    <div class="flex items-center gap-2"><div class="w-4 h-4 bg-white border border-gray-200 rounded"></div> Available</div>
                    <div class="flex items-center gap-2"><div class="w-4 h-4 rounded" style="background:#fee2e2;border:1px solid #fecaca"></div> Full / Taken</div>
                    <div class="flex items-center gap-2"><div class="w-4 h-4 rounded" style="background:#e5e5e5;border:1px solid #e5e5e5"></div> Unavailable</div>
                </div>

                <!-- Calendar Grid -->
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="selected_date" id="selectedDateInput">
                    <input type="hidden" name="booking_type" id="bookingTypeInput">
                    <input type="hidden" name="selected_time" id="selectedTimeInput">
                    
                    <div class="grid grid-cols-7 gap-1 mb-2 text-center text-lg font-normal">
                        <div class="py-2 text-blue-500">SUN</div>
                        <div class="py-2 text-blue-500">MON</div>
                        <div class="py-2 text-blue-500">TUE</div>
                        <div class="py-2 text-blue-500">WED</div>
                        <div class="py-2 text-blue-500">THU</div>
                        <div class="py-2 text-blue-500">FRI</div>
                        <div class="py-2 text-blue-500">SAT</div>
                    </div>
                    <div id="calendarGrid" class="grid grid-cols-7 gap-1">
                        <!-- Generated by JavaScript -->
                    </div>

                    <!-- Month Navigation -->
                    <div class="flex justify-between items-center mt-8">
                        <button type="button" onclick="prevMonth()" class="text-gray-600 hover:text-black">
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </button>
                        <button type="button" onclick="nextMonth()" class="text-gray-600 hover:text-black">
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Booking Type Selection — Shows after date is selected -->
            <div id="bookingTypeSection" class="glass-card p-6 hidden">
                <h3 class="text-xl font-semibold mb-4"><?= $isModeling ? 'Select Booking Type' : ($isTimed ? 'Select Your Time' : 'Confirm Your Date') ?></h3>
                <p class="text-gray-500 mb-4" id="selectedDateDisplay"></p>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4<?= $isModeling ? '' : ' hidden' ?>">
                    <!-- Solo Option -->
                    <div id="soloOption" class="booking-option border-2 border-gray-200 rounded-xl p-5 cursor-pointer" onclick="selectBookingType('Solo')">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-semibold text-lg">Solo Session</h4>
                                <p class="text-sm text-gray-500 mt-1">Exclusive time — just you!</p>
                            </div>
                            <i class="fa-solid fa-user text-xl text-gray-400"></i>
                        </div>
                        <div id="soloStatus" class="mt-3 text-sm font-medium"></div>
                    </div>
                    
                    <!-- Group Option -->
                    <div id="groupOption" class="booking-option border-2 border-gray-200 rounded-xl p-5 cursor-pointer" onclick="selectBookingType('Group')">
                        <div class="flex items-start justify-between">
                            <div>
                                <h4 class="font-semibold text-lg">Group Session</h4>
                                <p class="text-sm text-gray-500 mt-1">Bring friends or join others</p>
                            </div>
                            <i class="fa-solid fa-users text-xl text-gray-400"></i>
                        </div>
                        <div id="groupStatus" class="mt-3 text-sm font-medium"></div>
                    </div>
                </div>
                
                <!-- Solo: client picks a time -->
                <div id="soloTimeSection" class="mt-6 hidden">
                    <label class="block mb-1 font-medium" for="soloTimeSelect">Select your time</label>
                    <p class="text-sm text-gray-500 mb-2">Each Solo session takes <?= (int)$soloDuration ?> hours — those hours become unavailable to others.</p>
                    <select id="soloTimeSelect" onchange="onSoloTimeChange()" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">— Choose a time —</option>
                        <!-- times are filled in by JavaScript so taken times can be disabled -->
                    </select>
                    <p id="soloTakenNote" class="text-sm text-red-600 mt-2"></p>
                </div>

                <!-- Group: time is set by BRB -->
                <div id="groupTimeNote" class="mt-6 hidden p-4 rounded-lg bg-gray-50 border border-gray-200 text-gray-700">
                    <i class="fa-solid fa-clock mr-2"></i>Group sessions are held at <strong>9:00 AM or 3:00 PM</strong>. BRB will set the time for your group.
                </div>

                <button type="button" id="continueBtn" onclick="submitBooking()" class="btn-black px-6 py-2 rounded-lg mt-6 w-full disabled:opacity-50" disabled>
                    Continue to Confirmation <i class="fa-solid fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</main>

<script>
const unavailableDates = <?= json_encode($unavailableDates) ?>;
const bookedData = <?= json_encode($bookedData) ?>;
const MAX_SLOTS = <?= $maxSlotsPerDay ?>;
const IS_MODELING = <?= $isModeling ? 'true' : 'false' ?>;
const IS_TIMED = <?= $isTimed ? 'true' : 'false' ?>;   // Photography / Makeup: pick a time
const SOLO_TIMES = <?= json_encode($soloTimes) ?>;
const SOLO_DURATION = <?= (int)$soloDuration ?>;   // hours per booking (Photography 2, Makeup 3, Modeling Solo 3)

let currentDate = new Date();
let selectedDate = null;
let selectedBookingType = null;

function fmtTime(t) {
    let [h, m] = t.split(':').map(Number);
    const ap = (h % 24) >= 12 ? 'PM' : 'AM';
    h = h % 12 || 12;
    return h + (m ? ':' + String(m).padStart(2, '0') : '') + ' ' + ap;
}

function toMin(t) {
    const [h, m] = t.split(':').map(Number);
    return h * 60 + m;
}

function addHours(t, hours) {
    const total = toMin(t) + hours * 60;
    return String(Math.floor(total / 60) % 24).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
}

function fmtRange(t) {
    return fmtTime(t) + ' – ' + fmtTime(addHours(t, SOLO_DURATION));
}

// Why a Solo start time can't be picked: 'taken' (inside a booked session),
// 'overlap' (a session of SOLO_DURATION hours from here would run into a booked one), or null if free
function soloBlock(t, takenStarts) {
    const d = SOLO_DURATION * 60, ts = toMin(t);
    let reason = null;
    for (const s of takenStarts) {
        const ss = toMin(s);
        if (ts >= ss && ts < ss + d) return 'taken';
        if (ts < ss && ts + d > ss) reason = 'overlap';
    }
    return reason;
}

function getSlotInfo(dateStr) {
    const info = bookedData[dateStr] || { total: 0, solo: 0, group: 0, solo_times: [] };
    const takenTimes = info.solo_times || [];

    if (IS_MODELING) {
        // Group bookings use the 7 slots; Solo is booked by time (each Solo session blocks 3 hours)
        const groupLeft = Math.max(0, MAX_SLOTS - info.group);
        const soloLeft = SOLO_TIMES.filter(t => !soloBlock(t, takenTimes)).length;
        return { remaining: groupLeft, groupLeft, soloLeft, takenTimes, isFull: groupLeft <= 0 && soloLeft <= 0 };
    }
    if (IS_TIMED) {
        // Photography / Makeup: booked by time; each booking blocks SOLO_DURATION hours
        const soloLeft = SOLO_TIMES.filter(t => !soloBlock(t, takenTimes)).length;
        return { remaining: soloLeft, groupLeft: 0, soloLeft, takenTimes, isFull: soloLeft <= 0 };
    }
    // Other services: every booking takes 1 slot (not shown to the client)
    const remaining = Math.max(0, MAX_SLOTS - info.total);
    return { remaining, groupLeft: remaining, soloLeft: 0, takenTimes: [], isFull: remaining <= 0 };
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    const months = ['JANUARY','FEBRUARY','MARCH','APRIL','MAY','JUNE','JULY','AUGUST','SEPTEMBER','OCTOBER','NOVEMBER','DECEMBER'];
    document.getElementById('monthTitle').textContent = `${months[month]} (${year})`;
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';
    
    for (let i = 0; i < firstDay; i++) {
        grid.innerHTML += '<div class="day-cell border-transparent"></div>';
    }
    
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
        const thisDate = new Date(year, month, day);
        const isPast = thisDate < today;
        const isUnavailable = unavailableDates.includes(dateStr);
        const slots = getSlotInfo(dateStr);
        const isFull = slots.isFull;
        
        let cellClass = 'day-cell';
        let slotDisplay = '';
        let onclick = '';
        
        if (isPast || isUnavailable) {
            cellClass += ' day-unavailable';
            slotDisplay = '<span class="text-xs">—</span>';
        } else if (isFull) {
            cellClass += ' day-taken';
            slotDisplay = '<span class="slot-text slot-full">FULLY BOOKED</span>';
        } else {
            cellClass += ' day-available';
            // Only Modeling shows the slot count
            if (IS_MODELING) {
                slotDisplay = slots.groupLeft > 0
                    ? `<span class="slot-text slot-remaining">${slots.groupLeft} slot${slots.groupLeft > 1 ? 's' : ''} left</span>`
                    : '<span class="slot-text slot-full">Group full</span>';
                if (slots.takenTimes.length) {
                    const shown = slots.takenTimes.slice(0, 2).map(fmtRange).join(', ');
                    const more = slots.takenTimes.length > 2 ? ` +${slots.takenTimes.length - 2}` : '';
                    slotDisplay += `<br><span class="slot-text" style="color:#b91c1c">Solo taken: ${shown}${more}</span>`;
                }
            } else if (IS_TIMED) {
                slotDisplay = `<span class="slot-text slot-remaining">${slots.soloLeft} time${slots.soloLeft > 1 ? 's' : ''} open</span>`;
                if (slots.takenTimes.length) {
                    const shown = slots.takenTimes.slice(0, 2).map(fmtRange).join(', ');
                    const more = slots.takenTimes.length > 2 ? ` +${slots.takenTimes.length - 2}` : '';
                    slotDisplay += `<br><span class="slot-text" style="color:#b91c1c">Booked: ${shown}${more}</span>`;
                }
            } else {
                slotDisplay = '';
            }
            onclick = `selectCalendarDate('${dateStr}', this)`;
        }
        
        grid.innerHTML += `
            <div class="${cellClass}" ${onclick ? `onclick="${onclick}"` : ''}>
                <div class="font-medium">${day}</div>
                ${slotDisplay}
            </div>
        `;
    }
}

function selectCalendarDate(dateStr, cellEl) {
    // Remove previous selection
    document.querySelectorAll('.day-selected').forEach(el => el.classList.remove('day-selected'));
    
    selectedDate = dateStr;
    selectedBookingType = null;
    
    // Highlight selected
    cellEl.classList.add('day-selected');
    
    // Show booking type section
    document.getElementById('bookingTypeSection').classList.remove('hidden');
    document.getElementById('selectedDateDisplay').textContent = 'Date: ' + new Date(dateStr).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    
    if (IS_MODELING) {
        updateBookingOptions(dateStr);          // show Solo / Group choice
    } else if (IS_TIMED) {
        selectedBookingType = 'Solo';           // Photography / Makeup: go straight to picking a time
        renderSoloTimes();
        document.getElementById('groupTimeNote').classList.add('hidden');
        document.getElementById('soloTimeSection').classList.remove('hidden');
        onSoloTimeChange();                     // Continue stays disabled until a time is chosen
    } else {
        selectedBookingType = 'Solo';           // other services: no choice, just continue
        document.getElementById('continueBtn').disabled = false;
    }
}

function renderSoloTimes() {
    const taken = getSlotInfo(selectedDate).takenTimes;
    const sel = document.getElementById('soloTimeSelect');
    sel.innerHTML = '<option value="">— Choose a time —</option>';
    SOLO_TIMES.forEach(t => {
        const reason = soloBlock(t, taken);
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = fmtRange(t) + (reason === 'taken' ? ' — Already taken' : reason === 'overlap' ? ' — Overlaps a booking' : '');
        opt.disabled = !!reason;
        sel.appendChild(opt);
    });
    document.getElementById('soloTakenNote').textContent =
        taken.length ? 'Already booked: ' + taken.map(fmtRange).join(', ') : '';
}

function updateBookingOptions(dateStr) {
    const slots = getSlotInfo(dateStr);

    const soloOption = document.getElementById('soloOption');
    const groupOption = document.getElementById('groupOption');
    const soloStatus = document.getElementById('soloStatus');
    const groupStatus = document.getElementById('groupStatus');

    hideTimeSections();

    // Reset styles
    soloOption.classList.remove('opacity-50', 'cursor-not-allowed', 'selected');
    groupOption.classList.remove('opacity-50', 'cursor-not-allowed', 'selected');
    document.getElementById('continueBtn').disabled = true;
    selectedBookingType = null;

    // Solo: available while at least one time is still free
    if (slots.soloLeft <= 0) {
        soloOption.classList.add('opacity-50', 'cursor-not-allowed');
        soloStatus.innerHTML = '<span class="text-red-600">All solo times are taken</span>';
        soloOption.onclick = null;
    } else {
        soloStatus.innerHTML = `<span class="text-green-600">Available — pick your ${SOLO_DURATION}-hour time (${slots.soloLeft} start time${slots.soloLeft > 1 ? 's' : ''} open)</span>`;
        soloOption.onclick = () => selectBookingType('Solo');
    }

    // Group: available while slots remain, uses 1 slot
    if (slots.groupLeft <= 0) {
        groupOption.classList.add('opacity-50', 'cursor-not-allowed');
        groupStatus.innerHTML = '<span class="text-red-600">No slots available</span>';
        groupOption.onclick = null;
    } else {
        groupStatus.innerHTML = `<span class="text-green-600">${slots.groupLeft} slot${slots.groupLeft > 1 ? 's' : ''} left — this booking uses 1</span>`;
        groupOption.onclick = () => selectBookingType('Group');
    }
}

function hideTimeSections() {
    document.getElementById('soloTimeSection').classList.add('hidden');
    document.getElementById('groupTimeNote').classList.add('hidden');
    document.getElementById('soloTimeSelect').value = '';
}

function onSoloTimeChange() {
    // Solo can continue only after a time is chosen
    document.getElementById('continueBtn').disabled =
        !(selectedBookingType === 'Solo' && document.getElementById('soloTimeSelect').value);
}

function selectBookingType(type) {
    selectedBookingType = type;

    // Update UI
    document.querySelectorAll('.booking-option').forEach(el => el.classList.remove('selected'));
    document.getElementById(type.toLowerCase() + 'Option').classList.add('selected');

    if (type === 'Solo') {
        // Solo: client picks a time (taken times are disabled)
        renderSoloTimes();
        document.getElementById('soloTimeSection').classList.remove('hidden');
        document.getElementById('groupTimeNote').classList.add('hidden');
        onSoloTimeChange();
    } else {
        // Group: 9 AM or 3 PM, set by BRB
        document.getElementById('soloTimeSection').classList.add('hidden');
        document.getElementById('soloTimeSelect').value = '';
        document.getElementById('groupTimeNote').classList.remove('hidden');
        document.getElementById('continueBtn').disabled = false;
    }
}

function submitBooking() {
    if (!selectedDate || !selectedBookingType) return;
    const time = document.getElementById('soloTimeSelect').value;
    const needsTime = IS_TIMED || (IS_MODELING && selectedBookingType === 'Solo');
    if (needsTime && !time) return;

    document.getElementById('selectedDateInput').value = selectedDate;
    document.getElementById('bookingTypeInput').value = selectedBookingType;
    document.getElementById('selectedTimeInput').value = needsTime ? time : '';
    document.getElementById('bookingForm').submit();
}

function prevMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
    resetSelection();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
    resetSelection();
}

function resetSelection() {
    selectedDate = null;
    selectedBookingType = null;
    hideTimeSections();
    document.getElementById('bookingTypeSection').classList.add('hidden');
    document.querySelectorAll('.day-selected').forEach(el => el.classList.remove('day-selected'));
}

renderCalendar();
</script>

</body>
</html>
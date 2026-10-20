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

// Get all approved appointments to show in calendar
$stmt = $conn->prepare("
    SELECT a.*, s.name as service_name, u.username as client_name 
    FROM appointments a
    JOIN services s ON a.service_id = s.id
    JOIN users u ON a.user_id = u.id
    WHERE a.status = 'Approved'
    ORDER BY a.appointment_date ASC
");
$stmt->execute();
$allAppointments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unavailable dates set by admin
$unavailableDates = $conn->query("SELECT date FROM unavailable_dates")->fetchAll(PDO::FETCH_COLUMN) ?: [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar — BRB Studio</title>
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
        .day-cell { min-height: 90px; }
        .day-unavailable { background: #e5e5e5; color: #888; }
        .day-has-event { background: #fef3c7; }
        .event-dot { width: 8px; height: 8px; border-radius: 50%; background: #92400e; display: inline-block; margin-right: 4px; }
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
        <a href="client_schedule.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-calendar-check w-5 text-center text-gray-400"></i><span>Schedule</span>
        </a>
        <a href="client_calendar.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
    <h1 class="text-center mb-8">Calendar of Appointments</h1>

    <!-- Legend -->
    <div class="flex flex-wrap gap-6 mb-8 justify-center">
        <div class="flex items-center gap-2">
            <div class="w-5 h-5 rounded bg-yellow-100 border border-yellow-400"></div>
            <span>Has Appointment</span>
        </div>
        <div class="flex items-center gap-2">
            <div class="w-5 h-5 rounded bg-gray-200 border border-gray-400"></div>
            <span>Unavailable</span>
        </div>
    </div>

    <!-- Month Navigation -->
    <div class="flex justify-between items-center mb-8 max-w-4xl mx-auto">
        <button type="button" onclick="prevMonth()" class="btn-black px-6 py-2 rounded-lg">
            <i class="fa-solid fa-chevron-left"></i> Previous
        </button>
        <select id="monthSelect" onchange="jumpToMonth()" class="px-4 py-2 border border-gray-300 rounded-lg text-lg">
            <!-- Populated by JavaScript -->
        </select>
        <button type="button" onclick="nextMonth()" class="btn-black px-6 py-2 rounded-lg">
            Next <i class="fa-solid fa-chevron-right"></i>
        </button>
    </div>

    <!-- Calendar Grid -->
    <div class="glass-card p-8 max-w-4xl mx-auto">
        <div class="grid grid-cols-7 gap-2 mb-4 text-center">
            <div class="py-3 font-bold text-gray-700 text-lg">SUN</div>
            <div class="py-3 font-bold text-gray-700 text-lg">MON</div>
            <div class="py-3 font-bold text-gray-700 text-lg">TUE</div>
            <div class="py-3 font-bold text-gray-700 text-lg">WED</div>
            <div class="py-3 font-bold text-gray-700 text-lg">THU</div>
            <div class="py-3 font-bold text-gray-700 text-lg">FRI</div>
            <div class="py-3 font-bold text-gray-700 text-lg">SAT</div>
        </div>
        <div id="calendarGrid" class="grid grid-cols-7 gap-2">
            <!-- Generated by JavaScript -->
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div id="eventModal" class="fixed inset-0 bg-black/50 hidden items-center justify-center z-50">
        <div class="glass-card p-8 max-w-md w-full mx-4">
            <h3 id="modalDate" class="text-xl font-bold mb-4"></h3>
            <div id="modalContent"></div>
            <button onclick="closeModal()" class="btn-black px-6 py-2 rounded-lg mt-6 w-full">Close</button>
        </div>
    </div>
</main>

<script>
const appointments = <?= json_encode($allAppointments) ?>;
const unavailableDates = <?= json_encode($unavailableDates) ?>;
let currentDate = new Date();

// Build month options
function buildMonthOptions() {
    const select = document.getElementById('monthSelect');
    const months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    select.innerHTML = '';
    const startYear = new Date().getFullYear() - 1;
    const endYear = new Date().getFullYear() + 1;
    for (let y = startYear; y <= endYear; y++) {
        for (let m = 0; m < 12; m++) {
            const opt = document.createElement('option');
            opt.value = `${y}-${String(m+1).padStart(2,'0')}`;
            opt.textContent = `${months[m]} ${y}`;
            if (y === currentDate.getFullYear() && m === currentDate.getMonth()) opt.selected = true;
            select.appendChild(opt);
        }
    }
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Update month selector
    document.getElementById('monthSelect').value = `${year}-${String(month+1).padStart(2,'0')}`;
    
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';
    
    // Empty cells before first day
    for (let i = 0; i < firstDay; i++) {
        grid.innerHTML += '<div class="day-cell"></div>';
    }
    
    // Days of month
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month+1).padStart(2,'0')}-${String(day).padStart(2,'0')}`;
        const dayApps = appointments.filter(a => a.appointment_date === dateStr);
        const isUnavailable = unavailableDates.includes(dateStr);
        
        let cellClass = 'day-cell border border-gray-200 rounded-lg p-2 flex flex-col';
        if (isUnavailable) {
            cellClass += ' day-unavailable';
        } else if (dayApps.length > 0) {
            cellClass += ' day-has-event cursor-pointer';
        }
        
        let eventsHtml = '';
        if (dayApps.length > 0) {
            eventsHtml = `<div class="text-xs mt-1 space-y-1 max-h-16 overflow-hidden">`;
            dayApps.forEach(app => {
                eventsHtml += `<div><span class="event-dot"></span>${app.service_name}</div>`;
            });
            eventsHtml += `</div>`;
        }
        
        const onclick = dayApps.length > 0 ? `onclick="showEvents('${dateStr}')"` : '';
        
        grid.innerHTML += `
            <div class="${cellClass}" ${onclick}>
                <span class="font-semibold text-lg">${day}</span>
                ${eventsHtml}
            </div>
        `;
    }
}

function showEvents(dateStr) {
    const dayApps = appointments.filter(a => a.appointment_date === dateStr);
    document.getElementById('modalDate').textContent = new Date(dateStr + 'T00:00:00').toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    let html = '';
    if (dayApps.length === 0) {
        html = '<p class="text-gray-500">No appointments on this day.</p>';
    } else {
        dayApps.forEach(a => {
            html += `
                <div class="mb-4 pb-4 border-b border-gray-200 last:border-b-0">
                    <p class="font-semibold text-lg">${a.service_name}</p>
                    <p class="text-gray-600">Client: ${a.client_name}</p>
                </div>
            `;
        });
    }
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('eventModal').style.display = 'flex';
}

function closeModal() {
    document.getElementById('eventModal').style.display = 'none';
}

function prevMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    buildMonthOptions();
    renderCalendar();
}

function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    buildMonthOptions();
    renderCalendar();
}

function jumpToMonth() {
    const val = document.getElementById('monthSelect').value.split('-');
    currentDate = new Date(parseInt(val[0]), parseInt(val[1]) - 1, 1);
    renderCalendar();
}

buildMonthOptions();
renderCalendar();
</script>

</body>
</html>
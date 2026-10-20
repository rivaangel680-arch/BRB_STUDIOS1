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
function picPath($path) {
    if (!$path || $path === 'uploads/profile_default.png') $path = DEFAULT_PROFILE_PIC;
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}


$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$errors = [];
$success = '';

if (!function_exists('logActivity')) {
    function logActivity($activity, $details = '') {
        global $conn;

        if (empty($_SESSION['user_id']) || !$conn) {
            return false;
        }

        try {
            $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
            return $stmt->execute([
                $_SESSION['user_id'],
                $activity,
                $details
            ]);
        } catch (Exception $e) {
            return false;
        }
    }
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $birthday = !empty($_POST['birthday']) ? $_POST['birthday'] : null;
    $age = !empty($_POST['age']) ? intval($_POST['age']) : null;
    $gender = $_POST['gender'] ?? '';
    $contactNumber = trim($_POST['contact_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Check username uniqueness
    if ($username !== $user['username']) {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $check->execute([$username, $_SESSION['user_id']]);
        if ($check->rowCount() > 0) {
            $errors['username'] = 'Username is already taken.';
        }
    }
    
    // Handle profile picture upload
    $profilePic = $user['profile_pic'] ?? null;
    if (!empty($_FILES['profile_pic']['name'])) {
        $targetDir = __DIR__ . '/../uploads/';   // real folder on disk (root/uploads)
        if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
        $ext = strtolower(pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = 'profile_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $targetDir . $filename)) {
                $profilePic = 'uploads/' . $filename;   // path saved in the database
            }
        }
    }
    
    // Update password if provided
    if (!empty($_POST['new_password'])) {
        if ($_POST['new_password'] !== $_POST['confirm_password']) {
            $errors['password'] = 'Passwords do not match.';
        } elseif (strlen($_POST['new_password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } else {
            $hashed = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed, $_SESSION['user_id']]);
            $success = 'Password updated successfully. ';
        }
    }
    
    if (empty($errors)) {
        $stmt = $conn->prepare("UPDATE users 
            SET first_name = ?, last_name = ?, username = ?, birthday = ?, age = ?, gender = ?, contact_number = ?, address = ?, profile_pic = ?
            WHERE id = ?");
        $stmt->execute([
            $firstName, $lastName, $username, $birthday, $age, $gender, $contactNumber, $address, $profilePic, $_SESSION['user_id']
        ]);
        
        // Update session username
        $_SESSION['username'] = $username;
        if ($profilePic) $_SESSION['profile_pic'] = $profilePic;
        
        logActivity('Updated Profile', 'User updated their profile information');
        $success .= 'Profile updated successfully!';
        header("Location: client_profile.php?success=" . urlencode($success));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile — BRB Studio</title>
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
        label { font-weight: 500; }
        input, select, textarea { font-size: 16px; }
    </style>
</head>
<body class="flex">

<aside class="w-64 sidebar-bg text-white flex flex-col justify-between p-4 min-h-screen hidden md:flex">
    <div class="space-y-1">
        <div class="flex items-center space-x-3 px-4 py-4 mb-4 cursor-pointer" onclick="window.location.href='client_profile.php'">
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
    <h1 class="text-center mb-10">Edit Your Profile</h1>

    <?php if (!empty($errors)): ?>
    <div class="glass-card p-6 mb-6 max-w-2xl mx-auto bg-red-50 border-red-200">
        <?php foreach ($errors as $err): ?>
        <p class="text-red-600 mb-1">• <?= htmlspecialchars($err) ?></p>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="max-w-2xl mx-auto space-y-8">
        <!-- Profile Picture -->
        <div class="glass-card p-6">
            <h2 class="mb-4">Profile Picture</h2>
            <div class="flex items-center gap-6">
                <img src="<?= htmlspecialchars(picPath($user['profile_pic'] ?? '')) ?>" 
                     class="w-28 h-28 rounded-full object-cover border-2 border-gray-300" alt="Current Picture">
                <div class="flex-1">
                    <input type="file" name="profile_pic" accept="image/*" class="w-full text-base">
                    <p class="text-sm text-gray-500 mt-2">Upload a new profile picture (JPG, PNG, GIF only)</p>
                </div>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="glass-card p-6">
            <h2 class="mb-4">Personal Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-2">First Name</label>
                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2">Last Name</label>
                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2">Username</label>
                    <input type="text" name="username" value="<?= htmlspecialchars($user['username']) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2">Birthday</label>
                    <input type="date" name="birthday" value="<?= htmlspecialchars($user['birthday'] ?? '') ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2">Age</label>
                    <input type="number" name="age" value="<?= htmlspecialchars($user['age'] ?? '') ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div>
                    <label class="block mb-2">Gender</label>
                    <select name="gender" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                        <option value="">Select Gender</option>
                        <option value="Male" <?= ($user['gender'] ?? '') === 'Male' ? 'selected' : '' ?>>Male</option>
                        <option value="Female" <?= ($user['gender'] ?? '') === 'Female' ? 'selected' : '' ?>>Female</option>
                        <option value="Other" <?= ($user['gender'] ?? '') === 'Other' ? 'selected' : '' ?>>Other</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-2">Contact Number</label>
                    <input type="text" name="contact_number" value="<?= htmlspecialchars($user['contact_number'] ?? '') ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                </div>
                <div class="md:col-span-2">
                    <label class="block mb-2">Address</label>
                    <textarea name="address" rows="3" 
                              class="w-full px-4 py-2 border border-gray-300 rounded-lg"><?= htmlspecialchars($user['address'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Change Password -->
        <div class="glass-card p-6">
            <h2 class="mb-4">Change Password</h2>
            <p class="text-sm text-gray-500 mb-4">Leave blank if you do not want to change your password.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block mb-2">New Password</label>
                    <input type="password" name="new_password" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="At least 8 characters">
                </div>
                <div>
                    <label class="block mb-2">Confirm New Password</label>
                    <input type="password" name="confirm_password" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Re-enter new password">
                </div>
            </div>
        </div>

        <!-- Buttons -->
        <div class="flex gap-4 justify-center pt-4">
            <a href="client_profile.php" class="px-8 py-3 border border-black rounded-lg">Cancel</a>
            <button type="submit" class="btn-black px-10 py-3 rounded-lg text-lg font-bold">Save Changes</button>
        </div>
    </form>
</main>

</body>
</html>
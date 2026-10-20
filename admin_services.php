<?php
require __DIR__ . '/../config.php';
requireLogin();
requireRole(['Admin']);

// Image path helper: external URLs stay as-is, local files live in the root folder
function picPath($path, $fallback = 'uploads/profile_default.png') {
    $path = $path ?: $fallback;
    if (preg_match('#^(https?:)?//#', $path)) {
        return $path;
    }
    return '../' . ltrim($path, '/');
}

$search = $_GET['search'] ?? '';

// Handle Add/Edit/Delete
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST['add_service'])) {
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $price = $_POST['price'];
        $duration = max(1, (int) ($_POST['duration_minutes'] ?? 60));
        $status = 'Active'; // Laging Active kapag bago
        
        // Handle picture upload
        $imagePath = null;
        if (!empty($_FILES['service_image']['name'])) {
            $targetDir = __DIR__ . '/../uploads/services/';   // real folder on disk
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['service_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'service_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['service_image']['tmp_name'], $targetDir . $filename)) {
                    $imagePath = 'uploads/services/' . $filename;   // path saved in the database
                }
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO services (name, category, description, price, duration_minutes, status, image) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $category, $description, $price, $duration, $status, $imagePath]);
        header("Location: admin_services.php");
        exit;
    }
    
    if (isset($_POST['edit_service'])) {
        $id = $_POST['service_id'];
        $name = trim($_POST['name']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $price = $_POST['price'];
        $duration = max(1, (int) ($_POST['duration_minutes'] ?? 60));
        $status = $_POST['status'];
        
        // Handle picture upload
        $imageUpdate = '';
        $params = [$name, $category, $description, $price, $duration, $status];
        
        if (!empty($_FILES['service_image']['name'])) {
            $targetDir = __DIR__ . '/../uploads/services/';   // real folder on disk
            if (!is_dir($targetDir)) mkdir($targetDir, 0755, true);
            $ext = strtolower(pathinfo($_FILES['service_image']['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($ext, $allowed)) {
                $filename = 'service_' . $id . '_' . time() . '.' . $ext;
                if (move_uploaded_file($_FILES['service_image']['tmp_name'], $targetDir . $filename)) {
                    $imageUpdate = ", image = ?";
                    $params[] = 'uploads/services/' . $filename;   // path saved in the database
                }
            }
        }
        $params[] = $id;
        
        $stmt = $conn->prepare("UPDATE services SET name=?, category=?, description=?, price=?, duration_minutes=?, status=? $imageUpdate WHERE id=?");
        $stmt->execute($params);
        header("Location: admin_services.php");
        exit;
    }
    
    if (isset($_POST['delete_service'])) {
        $id = $_POST['service_id'];
        $stmt = $conn->prepare("DELETE FROM services WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin_services.php");
        exit;
    }
}

// Get services
$where = "1=1";
$params = [];
if (!empty($search)) {
    $where .= " AND (name LIKE ? OR description LIKE ? OR category LIKE ?)";
    $params = ["%$search%", "%$search%", "%$search%"];
}
$stmt = $conn->prepare("SELECT * FROM services WHERE $where ORDER BY category, name");
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Admin info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$admin = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management — BRB Studio</title>
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
        .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 50; align-items: center; justify-content: center; }
        .modal.active { display: flex; }
        .service-img { width: 100%; height: 160px; object-fit: cover; border-radius: 12px; background: #f3f4f6; }
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
        <a href="admin_services.php" class="flex items-center space-x-3 px-4 py-3 active-nav-item text-white text-lg">
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
        <a href="admin_reports.php" class="flex items-center space-x-3 px-4 py-3 rounded-lg hover:bg-white/10 text-lg">
            <i class="fa-solid fa-star w-5 text-center text-gray-400"></i><span>Report</span>
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
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 gap-4">
        <h1>Service Management</h1>
        <div class="flex gap-4">
            <form method="GET" class="flex">
                <input type="text" name="search" placeholder="Search service..." value="<?= htmlspecialchars($search) ?>" 
                       class="px-4 py-2 border border-gray-300 rounded-l-lg focus:outline-none">
                <button type="submit" class="btn-black px-4 py-2 rounded-r-lg"><i class="fa-solid fa-search"></i></button>
            </form>
            <button onclick="openModal('addModal')" class="btn-black px-4 py-2 rounded-lg whitespace-nowrap">
                <i class="fa-solid fa-plus mr-2"></i> Add Service
            </button>
        </div>
    </div>

    <!-- Services Grid -->
    <?php if (empty($services)): ?>
    <div class="glass-card p-8 text-center text-gray-500">No services found.</div>
    <?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($services as $s): ?>
        <div class="glass-card p-6">
            <!-- Service Image -->
            <?php if (!empty($s['image'])): ?>
            <img src="<?= htmlspecialchars(picPath($s['image'])) ?>" alt="<?= htmlspecialchars($s['name']) ?>" class="service-img mb-4">
            <?php else: ?>
            <div class="service-img mb-4 flex items-center justify-center text-gray-400">
                <i class="fa-solid fa-image text-4xl"></i>
            </div>
            <?php endif; ?>
            
            <div class="flex justify-between items-start mb-3">
                <span class="px-2 py-1 bg-gray-100 text-gray-600 rounded text-xs uppercase"><?= htmlspecialchars($s['category']) ?></span>
                <span class="px-2 py-1 rounded text-xs font-medium <?= $s['status'] === 'Active' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                    <?= htmlspecialchars($s['status']) ?>
                </span>
            </div>
            <h3 class="text-xl font-semibold mb-2"><?= htmlspecialchars($s['name']) ?></h3>
            <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?= htmlspecialchars($s['description']) ?></p>
            <p class="text-2xl font-bold mb-4">₱<?= number_format($s['price'], 2) ?></p>
            <div class="flex gap-2">
                <button onclick="editService(<?= htmlspecialchars(json_encode($s), ENT_QUOTES) ?>)" class="flex-1 text-center py-2 border border-black rounded-lg hover:bg-gray-50">
                    <i class="fa-solid fa-pen-to-square mr-1"></i> Edit
                </button>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this service?')">
                    <input type="hidden" name="service_id" value="<?= $s['id'] ?>">
                    <button type="submit" name="delete_service" class="px-3 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</main>

<!-- Add Modal -->
<div id="addModal" class="modal">
    <div class="glass-card p-6 w-full max-w-md m-4 max-h-screen overflow-y-auto">
        <h2 class="mb-6">Add New Service</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <div>
                <label class="block mb-2">Service Image</label>
                <input type="file" name="service_image" accept="image/*" class="w-full">
            </div>
            <div>
                <label class="block mb-2">Service Name</label>
                <input type="text" name="name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">Category</label>
                <select name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="Solo">Solo</option>
                    <option value="Package">Package</option>
                </select>
            </div>
            <div>
                <label class="block mb-2">Description</label>
                <textarea name="description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
            </div>
            <div>
                <label class="block mb-2">Price (₱)</label>
                <input type="number" name="price" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">Duration (minutes)</label>
                <input type="number" name="duration_minutes" value="60" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <input type="hidden" name="status" value="Active">
            <div class="bg-green-50 p-3 rounded-lg text-green-700 text-sm">
                <i class="fa-solid fa-info-circle mr-1"></i> This service will be set as <strong>Active</strong> by default.
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('addModal')" class="px-4 py-2 border border-gray-300 rounded-lg">Cancel</button>
                <button type="submit" name="add_service" class="btn-black px-4 py-2 rounded-lg">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
    <div class="glass-card p-6 w-full max-w-md m-4 max-h-screen overflow-y-auto">
        <h2 class="mb-6">Edit Service</h2>
        <form method="POST" enctype="multipart/form-data" class="space-y-4">
            <input type="hidden" name="service_id" id="edit_service_id">
            
            <div id="currentImagePreview" class="mb-3 hidden">
                <label class="block mb-2 text-sm">Current Image</label>
                <img id="edit_current_img" class="w-full h-40 object-cover rounded-lg mb-2">
            </div>
            <div>
                <label class="block mb-2">Change Image (Optional)</label>
                <input type="file" name="service_image" accept="image/*" class="w-full">
            </div>
            <div>
                <label class="block mb-2">Service Name</label>
                <input type="text" name="name" id="edit_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">Category</label>
                <select name="category" id="edit_category" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="Solo">Solo</option>
                    <option value="Package">Package</option>
                </select>
            </div>
            <div>
                <label class="block mb-2">Description</label>
                <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg"></textarea>
            </div>
            <div>
                <label class="block mb-2">Price (₱)</label>
                <input type="number" name="price" id="edit_price" step="0.01" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">Duration (minutes)</label>
                <input type="number" name="duration_minutes" id="edit_duration" min="1" required class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block mb-2">Status</label>
                <select name="status" id="edit_status" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
            </div>
            <div class="flex justify-end gap-3">
                <button type="button" onclick="closeModal('editModal')" class="px-4 py-2 border border-gray-300 rounded-lg">Cancel</button>
                <button type="submit" name="edit_service" class="btn-black px-4 py-2 rounded-lg">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).classList.add('active'); }
function closeModal(id) { document.getElementById(id).classList.remove('active'); }
function editService(s) {
    document.getElementById('edit_service_id').value = s.id;
    document.getElementById('edit_name').value = s.name;
    document.getElementById('edit_category').value = s.category;
    document.getElementById('edit_description').value = s.description;
    document.getElementById('edit_price').value = s.price;
    document.getElementById('edit_duration').value = s.duration_minutes || 60;
    document.getElementById('edit_status').value = s.status;
    
    // Show current image if exists
    const previewDiv = document.getElementById('currentImagePreview');
    const imgEl = document.getElementById('edit_current_img');
    if (s.image) {
        imgEl.src = s.image.match(/^(https?:)?\/\//) ? s.image : '../' + s.image;
        previewDiv.classList.remove('hidden');
    } else {
        previewDiv.classList.add('hidden');
    }
    
    openModal('editModal');
}
window.onclick = function(e) {
    if (e.target.classList.contains('modal')) e.target.classList.remove('active');
}
</script>

</body>
</html>
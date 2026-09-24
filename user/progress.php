<?php
session_start();
require_once '../includes/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $weight = $_POST['weight'];
    $body_fat = $_POST['body_fat'] ?? null;
    $muscle_mass = $_POST['muscle_mass'] ?? null;
    $notes = $_POST['notes'] ?? null;
    $entry_date = $_POST['entry_date'];

    // Handle image uploads
    $before_image = null;
    $after_image = null;

    try {
        // Handle before image upload
        if (isset($_FILES['before_image']) && $_FILES['before_image']['error'] === 0) {
            $before_image = handleImageUpload($_FILES['before_image'], 'before');
            error_log("Before image uploaded: " . $before_image);
        } else if (isset($_FILES['before_image'])) {
            error_log("Before image upload error: " . $_FILES['before_image']['error']);
        }

        // Handle after image upload
        if (isset($_FILES['after_image']) && $_FILES['after_image']['error'] === 0) {
            $after_image = handleImageUpload($_FILES['after_image'], 'after');
            error_log("After image uploaded: " . $after_image);
        } else if (isset($_FILES['after_image'])) {
            error_log("After image upload error: " . $_FILES['after_image']['error']);
        }

        // Insert into database
        $stmt = $conn->prepare("INSERT INTO member_progress (member_id, entry_date, weight, body_fat, muscle_mass, before_image, after_image, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isddssss", $user_id, $entry_date, $weight, $body_fat, $muscle_mass, $before_image, $after_image, $notes);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Progress entry added successfully!";
            error_log("Progress entry added successfully. Before image: " . $before_image . ", After image: " . $after_image);
        } else {
            $_SESSION['error'] = "Error adding progress entry: " . $stmt->error;
            error_log("Error adding progress entry: " . $stmt->error);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error: " . $e->getMessage();
        error_log("Exception in progress entry: " . $e->getMessage());
    }
    
    header("Location: progress.php");
    exit();
}

// Function to handle image upload
function handleImageUpload($file, $type) {
    if (!$file || $file['error'] !== 0) {
        error_log("File upload error: " . $file['error']);
        return null;
    }

    // Create uploads directory if it doesn't exist
    $base_dir = dirname(__FILE__) . "/../uploads";
    $target_dir = $base_dir . "/progress";
    
    // Create directories recursively with proper permissions
    if (!file_exists($base_dir)) {
        if (!mkdir($base_dir, 0777, true)) {
            error_log("Failed to create base uploads directory");
            throw new Exception("Failed to create uploads directory");
        }
    }
    
    if (!file_exists($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            error_log("Failed to create progress uploads directory");
            throw new Exception("Failed to create progress uploads directory");
        }
    }
    
    // Ensure directory is writable
    if (!is_writable($target_dir)) {
        error_log("Uploads directory is not writable");
        throw new Exception("Uploads directory is not writable");
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        error_log("Invalid file type: " . $file_extension);
        throw new Exception("Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.");
    }
    
    // Generate a unique filename with timestamp and random string
    $timestamp = time();
    $random_string = bin2hex(random_bytes(4));
    $new_filename = sprintf("%d_%s_%s.%s", $timestamp, $random_string, $type, $file_extension);
    $target_file = $target_dir . '/' . $new_filename;
    
    // Debug information
    error_log("Uploading file: " . $file["name"]);
    error_log("Target file: " . $target_file);
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        error_log("File uploaded successfully: " . $new_filename);
        return $new_filename;
    }
    
    error_log("Failed to upload file: " . $file["name"]);
    throw new Exception("Failed to upload image.");
}

// Fetch progress data for charts
$progress_data = [];
try {
    $stmt = $conn->prepare("SELECT entry_date, weight, body_fat, muscle_mass FROM member_progress WHERE member_id = ? ORDER BY entry_date ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $progress_data[] = $row;
    }
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching progress data: " . $e->getMessage();
}

// Fetch latest progress entries
try {
    $stmt = $conn->prepare("SELECT * FROM member_progress WHERE member_id = ? ORDER BY entry_date DESC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $latest_entries = $stmt->get_result();
} catch (Exception $e) {
    $_SESSION['error'] = "Error fetching latest entries: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Progress - FitClub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .gradient-header {
            background: linear-gradient(135deg, rgb(17, 24, 39) 0%, rgb(31, 41, 55) 100%);
        }
        .upload-area {
            border: 2px dashed #CBD5E0;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #4F46E5;
            background-color: #F8FAFC;
        }
    </style>
</head>
<body class="bg-gray-100">
    <?php include 'components/navigation.php'; ?>

    <!-- Gradient Header -->
    <div class="gradient-header text-white py-12 mb-8">
        <div class="container mx-auto px-4">
            <h1 class="text-4xl font-bold mb-2">My Progress</h1>
            <p class="text-lg opacity-90">Track your fitness journey and celebrate your achievements</p>
        </div>
    </div>

    <div class="container mx-auto px-4 py-8">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-8">
            <!-- Left Column - Charts -->
            <div class="space-y-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-weight text-indigo-600 text-xl mr-2"></i>
                        <h2 class="text-xl font-semibold">Weight Progress</h2>
                    </div>
                    <canvas id="weightChart"></canvas>
                </div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <i class="fas fa-chart-line text-indigo-600 text-xl mr-2"></i>
                        <h2 class="text-xl font-semibold">Body Composition</h2>
                    </div>
                    <canvas id="compositionChart"></canvas>
                </div>
            </div>

            <!-- Right Column - Progress Entry Form -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex items-center mb-4">
                    <i class="fas fa-plus-circle text-indigo-600 text-xl mr-2"></i>
                    <h2 class="text-xl font-semibold">Add Progress Entry</h2>
                </div>
                <form action="" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="far fa-calendar-alt mr-1"></i> Date
                            </label>
                            <input type="date" name="entry_date" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-weight mr-1"></i> Weight (kg)
                            </label>
                            <input type="number" name="weight" step="0.01" required 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-percentage mr-1"></i> Body Fat (%)
                            </label>
                            <input type="number" name="body_fat" step="0.01" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">
                                <i class="fas fa-dumbbell mr-1"></i> Muscle Mass (kg)
                            </label>
                            <input type="number" name="muscle_mass" step="0.01" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="upload-area p-4 rounded-lg text-center">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-image mr-1"></i> Before Image
                            </label>
                            <input type="file" name="before_image" accept="image/*" 
                                   class="hidden" id="before_image">
                            <label for="before_image" 
                                   class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-upload mr-2"></i> Choose File
                            </label>
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG or GIF (max. 5MB)</p>
                        </div>
                        <div class="upload-area p-4 rounded-lg text-center">
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-image mr-1"></i> After Image
                            </label>
                            <input type="file" name="after_image" accept="image/*" 
                                   class="hidden" id="after_image">
                            <label for="after_image" 
                                   class="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                                <i class="fas fa-upload mr-2"></i> Choose File
                            </label>
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG or GIF (max. 5MB)</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">
                            <i class="fas fa-sticky-note mr-1"></i> Notes
                        </label>
                        <textarea name="notes" rows="3" 
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"></textarea>
                    </div>

                    <button type="submit" 
                            class="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition-colors duration-200">
                        <i class="fas fa-save mr-2"></i> Save Progress Entry
                    </button>
                </form>
            </div>
        </div>

        <!-- Progress Entries Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php while ($entry = $latest_entries->fetch_assoc()): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden transform hover:scale-105 transition-transform duration-200">
                <div class="p-4">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold text-gray-800">
                            <i class="far fa-calendar-alt mr-2 text-indigo-600"></i>
                            <?php echo date('M d, Y', strtotime($entry['entry_date'])); ?>
                        </h3>
                        <span class="text-sm text-gray-500">
                            <i class="fas fa-weight mr-1"></i>
                            <?php echo $entry['weight']; ?> kg
                        </span>
                    </div>
                    
                    <!-- Before/After Images -->
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <?php if (!empty($entry['before_image'])): ?>
                        <div class="relative aspect-square group">
                            <?php 
                            $before_image_path = dirname(__FILE__) . "/../uploads/progress/" . htmlspecialchars($entry['before_image']);
                            error_log("Checking before image path: " . $before_image_path);
                            if (file_exists($before_image_path)): 
                            ?>
                                <img src="../uploads/progress/<?php echo htmlspecialchars($entry['before_image']); ?>" 
                                     alt="Before" 
                                     class="w-full h-full object-cover rounded-lg">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                                    <span class="text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-sm font-medium">
                                        Before
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="w-full h-full bg-gray-100 rounded-lg flex items-center justify-center">
                                    <span class="text-gray-400">Image not found</span>
                                    <?php error_log("Before image not found at path: " . $before_image_path); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($entry['after_image'])): ?>
                        <div class="relative aspect-square group">
                            <?php 
                            $after_image_path = dirname(__FILE__) . "/../uploads/progress/" . htmlspecialchars($entry['after_image']);
                            error_log("Checking after image path: " . $after_image_path);
                            if (file_exists($after_image_path)): 
                            ?>
                                <img src="../uploads/progress/<?php echo htmlspecialchars($entry['after_image']); ?>" 
                                     alt="After" 
                                     class="w-full h-full object-cover rounded-lg">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 flex items-center justify-center">
                                    <span class="text-white opacity-0 group-hover:opacity-100 transition-opacity duration-200 text-sm font-medium">
                                        After
                                    </span>
                                </div>
                            <?php else: ?>
                                <div class="w-full h-full bg-gray-100 rounded-lg flex items-center justify-center">
                                    <span class="text-gray-400">Image not found</span>
                                    <?php error_log("After image not found at path: " . $after_image_path); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="grid grid-cols-2 gap-2 mb-4">
                        <?php if ($entry['body_fat']): ?>
                        <div class="bg-gray-50 p-2 rounded">
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-percentage mr-1"></i> Body Fat
                            </span>
                            <p class="font-semibold"><?php echo $entry['body_fat']; ?>%</p>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($entry['muscle_mass']): ?>
                        <div class="bg-gray-50 p-2 rounded">
                            <span class="text-sm text-gray-500">
                                <i class="fas fa-dumbbell mr-1"></i> Muscle Mass
                            </span>
                            <p class="font-semibold"><?php echo $entry['muscle_mass']; ?> kg</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($entry['notes']): ?>
                    <div class="border-t pt-4">
                        <p class="text-sm text-gray-600">
                            <i class="fas fa-sticky-note mr-1 text-indigo-600"></i>
                            <?php echo nl2br(htmlspecialchars($entry['notes'])); ?>
                        </p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <script>
        // Prepare data for charts
        const progressData = <?php echo json_encode($progress_data); ?>;
        const dates = progressData.map(entry => entry.entry_date);
        const weights = progressData.map(entry => entry.weight);
        const bodyFat = progressData.map(entry => entry.body_fat);
        const muscleMass = progressData.map(entry => entry.muscle_mass);

        // Weight Chart
        new Chart(document.getElementById('weightChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Weight (kg)',
                    data: weights,
                    borderColor: '#4F46E5',
                    backgroundColor: 'rgba(79, 70, 229, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // Body Composition Chart
        new Chart(document.getElementById('compositionChart'), {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Body Fat (%)',
                    data: bodyFat,
                    borderColor: '#EF4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Muscle Mass (kg)',
                    data: muscleMass,
                    borderColor: '#22C55E',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        beginAtZero: false,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        // File input preview
        document.querySelectorAll('input[type="file"]').forEach(input => {
            input.addEventListener('change', function(e) {
                const label = this.nextElementSibling;
                const fileName = this.files[0]?.name;
                if (fileName) {
                    label.innerHTML = `<i class="fas fa-check mr-2"></i>${fileName}`;
                } else {
                    label.innerHTML = `<i class="fas fa-upload mr-2"></i>Choose File`;
                }
            });
        });
    </script>
</body>
</html>

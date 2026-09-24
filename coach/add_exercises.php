<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Check if user is coach
if (!isCoach()) {
    header("Location: ../index.php");
    exit();
}

// Get coach ID
$coach_id = 0;
$user_id = $_SESSION['user_id'];
$stmt = $conn->prepare("SELECT id FROM coaches WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $coach_id = $row['id'];
}
$stmt->close();

// Check if plan_id is provided
if (!isset($_GET['plan_id']) || !is_numeric($_GET['plan_id'])) {
    header("Location: workout_plans.php");
    exit();
}

$plan_id = (int)$_GET['plan_id'];

// Verify the workout plan belongs to this coach
$stmt = $conn->prepare("SELECT * FROM workout_plans WHERE id = ? AND coach_id = ?");
$stmt->bind_param("ii", $plan_id, $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$plan = $result->fetch_assoc()) {
    header("Location: workout_plans.php");
    exit();
}
$stmt->close();

// Handle form submission
$alert = "";
$alert_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $sets = (int)($_POST['sets'] ?? 0);
    $reps = (int)($_POST['reps'] ?? 0);
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 0);
    $exercise_order = (int)($_POST['exercise_order'] ?? 0);
    $gif_path = null;
    
    $errors = [];
    
    if (empty($name)) {
        $errors[] = "Exercise name is required";
    }
    
    if ($sets <= 0) {
        $errors[] = "Number of sets must be greater than 0";
    }
    
    if ($reps <= 0) {
        $errors[] = "Number of reps must be greater than 0";
    }
    
    if ($duration_minutes <= 0) {
        $errors[] = "Duration must be greater than 0 minutes";
    }

    // Handle GIF upload
    if (isset($_FILES['gif']) && $_FILES['gif']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gif'];
        $file_type = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Check if file is a GIF
        if ($file_type !== 'gif') {
            $errors[] = "Only GIF files are allowed";
        } else {
            // Create uploads directory if it doesn't exist
            $upload_dir = '../uploads/exercises/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $target_path = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                $gif_path = 'uploads/exercises/' . $filename;
            } else {
                $errors[] = "Error uploading GIF file";
            }
        }
    }
    
    // If no errors, add the exercise
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO exercises (workout_plan_id, name, description, sets, reps, duration_minutes, exercise_order, gif_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issiiiss", $plan_id, $name, $description, $sets, $reps, $duration_minutes, $exercise_order, $gif_path);
            
            if ($stmt->execute()) {
                $alert = "Exercise added successfully!";
                $alert_type = "success";
            } else {
                $alert = "Error adding exercise: " . $stmt->error;
                $alert_type = "danger";
            }
            $stmt->close();
        } catch (Exception $e) {
            $alert = "Error: " . $e->getMessage();
            $alert_type = "danger";
        }
    } else {
        $alert = implode("<br>", $errors);
        $alert_type = "danger";
    }
}

// Get all exercises for this plan
$stmt = $conn->prepare("SELECT * FROM exercises WHERE workout_plan_id = ? ORDER BY exercise_order ASC");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$exercises = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Exercises - <?php echo htmlspecialchars($plan['name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .exercise-card {
            transition: all 0.3s ease;
        }
        .exercise-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .form-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Add Exercises</h1>
                    <p class="mt-2 text-sm text-gray-600">Add exercises to <?php echo htmlspecialchars($plan['name']); ?></p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="view_workout_plan.php?id=<?php echo $plan_id; ?>" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-eye mr-2"></i> View Plan
                    </a>
                    <a href="workout_plans.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Plans
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($alert)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>

        <!-- Add Exercise Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900">New Exercise</h2>
            </div>
            <form method="POST" action="" enctype="multipart/form-data" class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Exercise Name -->
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Exercise Name</label>
                        <input type="text" id="name" name="name" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none">
                    </div>
                    
                    <!-- Sets -->
                    <div>
                        <label for="sets" class="block text-sm font-medium text-gray-700 mb-1">Sets</label>
                        <input type="number" id="sets" name="sets" min="1" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none">
                    </div>
                    
                    <!-- Reps -->
                    <div>
                        <label for="reps" class="block text-sm font-medium text-gray-700 mb-1">Reps</label>
                        <input type="number" id="reps" name="reps" min="1" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none">
                    </div>
                    
                    <!-- Duration -->
                    <div>
                        <label for="duration_minutes" class="block text-sm font-medium text-gray-700 mb-1">Duration (min)</label>
                        <input type="number" id="duration_minutes" name="duration_minutes" min="1" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none">
                    </div>
                    
                    <!-- Exercise Order -->
                    <div>
                        <label for="exercise_order" class="block text-sm font-medium text-gray-700 mb-1">Order</label>
                        <input type="number" id="exercise_order" name="exercise_order" min="1" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo count($exercises) + 1; ?>">
                    </div>

                    <!-- GIF Upload -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Exercise GIF</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-indigo-500 transition-colors">
                            <div class="space-y-1 text-center">
                                <i class="fas fa-upload text-gray-400 text-3xl mb-2"></i>
                                <div class="flex text-sm text-gray-600">
                                    <label for="gif" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                        <span>Upload a file</span>
                                        <input id="gif" name="gif" type="file" accept=".gif" class="sr-only">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500">GIF files only</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"></textarea>
                </div>
                
                <div class="mt-6">
                    <button type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center font-medium">
                        <i class="fas fa-plus mr-2"></i> Add Exercise
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Current Exercises -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900">Current Exercises</h2>
            </div>
            
            <?php if (empty($exercises)): ?>
                <div class="p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-dumbbell text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-lg font-medium text-gray-900 mb-2">No exercises added yet</p>
                    <p class="text-sm text-gray-500">Use the form to add your first exercise</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($exercises as $exercise): ?>
                        <div class="exercise-card p-6 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <?php if (!empty($exercise['gif_path'])): ?>
                                        <div class="flex-shrink-0">
                                            <img src="../<?php echo htmlspecialchars($exercise['gif_path']); ?>" 
                                                 alt="Exercise GIF" 
                                                 class="w-24 h-24 object-cover rounded-lg shadow-sm">
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                #<?php echo $exercise['exercise_order']; ?>
                                            </span>
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($exercise['name']); ?>
                                            </h3>
                                        </div>
                                        <?php if (!empty($exercise['description'])): ?>
                                            <p class="mt-1 text-sm text-gray-600">
                                                <?php echo htmlspecialchars($exercise['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                            <span class="flex items-center">
                                                <i class="fas fa-dumbbell mr-1"></i>
                                                <?php echo $exercise['sets']; ?> sets × <?php echo $exercise['reps']; ?> reps
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-clock mr-1"></i>
                                                <?php echo $exercise['duration_minutes']; ?> min
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <a href="edit_exercise.php?id=<?php echo $exercise['id']; ?>" 
                                       class="text-indigo-600 hover:text-indigo-900 p-2 rounded-lg hover:bg-indigo-50 transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_exercise.php?id=<?php echo $exercise['id']; ?>" 
                                       class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors"
                                       onclick="return confirm('Are you sure you want to delete this exercise?')">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html>
<?php
session_start();
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

// Function to handle image uploads
function handleImageUpload($file, $type) {
    // Create target directory if it doesn't exist
    $target_dir = "../uploads/diet_plans/";
    if ($type === 'meal') {
        $target_dir .= "meals/";
    }
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }

    // Generate unique filename
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $new_filename = uniqid() . '.' . $file_extension;
    $target_file = $target_dir . $new_filename;

    // Check if file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if ($check === false) {
        throw new Exception("File is not an image.");
    }

    // Check file size (5MB max)
    if ($file["size"] > 5000000) {
        throw new Exception("File is too large. Maximum size is 5MB.");
    }

    // Move uploaded file
    if (!move_uploaded_file($file["tmp_name"], $target_file)) {
        throw new Exception("Error uploading file.");
    }

    // Return relative path for database storage
    return str_replace("../uploads/", "", $target_file);
}

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
    header("Location: diet_plans.php");
    exit();
}

$plan_id = (int)$_GET['plan_id'];

// Verify the diet plan belongs to this coach
$stmt = $conn->prepare("SELECT * FROM diet_plans WHERE id = ? AND coach_id = ?");
$stmt->bind_param("ii", $plan_id, $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$plan = $result->fetch_assoc()) {
    header("Location: diet_plans.php");
    exit();
}
$stmt->close();

// Handle form submission
$alert = "";
$alert_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate form data
    $meal_type = $_POST['meal_type'] ?? '';
    $meal_name = trim($_POST['meal_name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $calories = (int)($_POST['calories'] ?? 0);
    $protein = (int)($_POST['protein'] ?? 0);
    $carbs = (int)($_POST['carbs'] ?? 0);
    $fats = (int)($_POST['fats'] ?? 0);
    $meal_order = (int)($_POST['meal_order'] ?? 0);
    
    $errors = [];
    
    if (empty($meal_type)) {
        $errors[] = "Meal type is required";
    }
    
    if (empty($meal_name)) {
        $errors[] = "Meal name is required";
    }
    
    if ($calories <= 0) {
        $errors[] = "Calories must be greater than 0";
    }
    
    if ($protein < 0) {
        $errors[] = "Protein cannot be negative";
    }
    
    if ($carbs < 0) {
        $errors[] = "Carbs cannot be negative";
    }
    
    if ($fats < 0) {
        $errors[] = "Fats cannot be negative";
    }
    
    if ($meal_order < 0) {
        $errors[] = "Meal order cannot be negative";
    }

    // Handle image upload
    $image_path = null;
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === UPLOAD_ERR_OK) {
        try {
            $image_path = handleImageUpload($_FILES['meal_image'], 'meal');
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    // If no errors, insert the meal
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("INSERT INTO diet_plan_meals (diet_plan_id, meal_type, meal_name, description, calories, protein, carbs, fats, meal_order, image_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssiiiiis", $plan_id, $meal_type, $meal_name, $description, $calories, $protein, $carbs, $fats, $meal_order, $image_path);
            
            if ($stmt->execute()) {
                $alert = "Meal added successfully!";
                $alert_type = "success";
            } else {
                $alert = "Error adding meal: " . $stmt->error;
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

// Get existing meals for this plan
$meals = [];
$stmt = $conn->prepare("SELECT * FROM diet_plan_meals WHERE diet_plan_id = ? ORDER BY meal_order ASC");
$stmt->bind_param("i", $plan_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $meals[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Meals - <?php echo htmlspecialchars($plan['name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        .meal-card {
            transition: all 0.3s ease;
        }
        .meal-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        .form-input:focus {
            border-color: #4f46e5;
            box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.1);
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-10px); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        .animate-fade-out {
            animation: fadeOut 0.3s ease-out;
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
                    <h1 class="text-3xl font-bold text-gray-900">Add Meals</h1>
                    <p class="mt-2 text-sm text-gray-600">Add meals to <?php echo htmlspecialchars($plan['name']); ?></p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="view_diet_plan.php?id=<?php echo $plan_id; ?>" 
                       class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                        <i class="fas fa-eye mr-2"></i> View Plan
                    </a>
                    <a href="diet_plans.php" 
                       class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Diet Plans
                    </a>
                </div>
            </div>
        </div>

        <?php if (!empty($alert)): ?>
            <div class="mb-6 p-4 rounded-lg <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>

        <!-- Add Meal Form -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 mb-8">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900">New Meal</h2>
            </div>
            <form method="POST" action="" class="p-6" enctype="multipart/form-data">
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Meal Type -->
                    <div>
                        <label for="meal_type" class="block text-sm font-medium text-gray-700 mb-1">Meal Type</label>
                        <select id="meal_type" name="meal_type" required
                                class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none">
                            <option value="">Select Meal Type</option>
                            <option value="breakfast">Breakfast</option>
                            <option value="lunch">Lunch</option>
                            <option value="dinner">Dinner</option>
                            <option value="snack">Snack</option>
                        </select>
                    </div>
                    
                    <!-- Meal Name -->
                    <div>
                        <label for="meal_name" class="block text-sm font-medium text-gray-700 mb-1">Meal Name</label>
                        <input type="text" id="meal_name" name="meal_name" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo htmlspecialchars($_POST['meal_name'] ?? ''); ?>">
                    </div>
                    
                    <!-- Calories -->
                    <div>
                        <label for="calories" class="block text-sm font-medium text-gray-700 mb-1">Calories</label>
                        <input type="number" id="calories" name="calories" min="1" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo htmlspecialchars($_POST['calories'] ?? ''); ?>">
                    </div>
                    
                    <!-- Protein -->
                    <div>
                        <label for="protein" class="block text-sm font-medium text-gray-700 mb-1">Protein (g)</label>
                        <input type="number" id="protein" name="protein" min="0" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo htmlspecialchars($_POST['protein'] ?? ''); ?>">
                    </div>
                    
                    <!-- Carbs -->
                    <div>
                        <label for="carbs" class="block text-sm font-medium text-gray-700 mb-1">Carbs (g)</label>
                        <input type="number" id="carbs" name="carbs" min="0" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo htmlspecialchars($_POST['carbs'] ?? ''); ?>">
                    </div>
                    
                    <!-- Fats -->
                    <div>
                        <label for="fats" class="block text-sm font-medium text-gray-700 mb-1">Fats (g)</label>
                        <input type="number" id="fats" name="fats" min="0" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo htmlspecialchars($_POST['fats'] ?? ''); ?>">
                    </div>

                    <!-- Meal Order -->
                    <div>
                        <label for="meal_order" class="block text-sm font-medium text-gray-700 mb-1">Meal Order</label>
                        <input type="number" id="meal_order" name="meal_order" min="0" required
                               class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"
                               value="<?php echo htmlspecialchars($_POST['meal_order'] ?? count($meals)); ?>">
                    </div>

                    <!-- Meal Image -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Meal Image</label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-indigo-500 transition-colors group">
                            <div class="space-y-1 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 group-hover:text-indigo-500 transition-colors" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 mt-2">
                                        <label for="meal_image" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500">
                                            <span class="px-4 py-2 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors">Upload an image</span>
                                            <input id="meal_image" name="meal_image" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                        </label>
                                        <p class="pl-1 self-center">or drag and drop</p>
                                    </div>
                                </div>
                                <p class="text-xs text-gray-500">PNG, JPG, JPEG up to 5MB</p>
                            </div>
                        </div>
                        <div id="imagePreview" class="mt-4 hidden">
                            <div class="relative inline-block">
                                <img id="preview" src="#" alt="Preview" class="max-h-48 rounded-lg shadow-md">
                                <button type="button" onclick="removeImage()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors shadow-lg">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="mt-6">
                    <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="description" name="description" rows="3"
                              class="form-input w-full px-4 py-2 rounded-lg border border-gray-300 focus:outline-none"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>
                
                <div class="mt-6">
                    <button type="submit" 
                            class="w-full bg-indigo-600 text-white py-3 px-4 rounded-lg hover:bg-indigo-700 transition-colors flex items-center justify-center font-medium">
                        <i class="fas fa-plus mr-2"></i> Add Meal
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Current Meals -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100">
            <div class="p-6 border-b border-gray-100">
                <h2 class="text-xl font-semibold text-gray-900">Current Meals</h2>
            </div>
            
            <?php if (empty($meals)): ?>
                <div class="p-8 text-center">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-gray-100 mb-4">
                        <i class="fas fa-utensils text-2xl text-gray-400"></i>
                    </div>
                    <p class="text-lg font-medium text-gray-900 mb-2">No meals added yet</p>
                    <p class="text-sm text-gray-500">Use the form to add your first meal</p>
                </div>
            <?php else: ?>
                <div class="divide-y divide-gray-100">
                    <?php foreach ($meals as $meal): ?>
                        <div class="meal-card p-6 hover:bg-gray-50">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start space-x-4">
                                    <?php if ($meal['image_path']): ?>
                                        <div class="flex-shrink-0">
                                            <img src="../uploads/<?php echo htmlspecialchars($meal['image_path']); ?>" 
                                                 alt="<?php echo htmlspecialchars($meal['meal_name']); ?>" 
                                                 class="w-24 h-24 object-cover rounded-lg shadow-sm">
                                        </div>
                                    <?php else: ?>
                                        <div class="flex-shrink-0 w-24 h-24 bg-gray-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-utensils text-2xl text-gray-400"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="flex items-center space-x-2">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800">
                                                <?php echo ucfirst($meal['meal_type']); ?>
                                            </span>
                                            <h3 class="text-lg font-medium text-gray-900">
                                                <?php echo htmlspecialchars($meal['meal_name']); ?>
                                            </h3>
                                        </div>
                                        <?php if (!empty($meal['description'])): ?>
                                            <p class="mt-1 text-sm text-gray-600">
                                                <?php echo htmlspecialchars($meal['description']); ?>
                                            </p>
                                        <?php endif; ?>
                                        <div class="mt-2 flex items-center space-x-4 text-sm text-gray-500">
                                            <span class="flex items-center">
                                                <i class="fas fa-fire mr-1"></i>
                                                <?php echo $meal['calories']; ?> calories
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-drumstick-bite mr-1"></i>
                                                P: <?php echo $meal['protein']; ?>g
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-bread-slice mr-1"></i>
                                                C: <?php echo $meal['carbs']; ?>g
                                            </span>
                                            <span class="flex items-center">
                                                <i class="fas fa-cheese mr-1"></i>
                                                F: <?php echo $meal['fats']; ?>g
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <a href="edit_meal.php?id=<?php echo $meal['id']; ?>" 
                                       class="text-indigo-600 hover:text-indigo-900 p-2 rounded-lg hover:bg-indigo-50 transition-colors">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="delete_meal.php?id=<?php echo $meal['id']; ?>" 
                                       class="text-red-600 hover:text-red-900 p-2 rounded-lg hover:bg-red-50 transition-colors"
                                       onclick="return confirm('Are you sure you want to delete this meal?')">
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

    <script>
    function previewImage(input) {
        const preview = document.getElementById('preview');
        const previewDiv = document.getElementById('imagePreview');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            
            reader.onload = function(e) {
                preview.src = e.target.result;
                previewDiv.classList.remove('hidden');
                previewDiv.classList.add('animate-fade-in');
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        const input = document.getElementById('meal_image');
        const preview = document.getElementById('preview');
        const previewDiv = document.getElementById('imagePreview');
        
        previewDiv.classList.add('animate-fade-out');
        setTimeout(() => {
            input.value = '';
            preview.src = '#';
            previewDiv.classList.add('hidden');
            previewDiv.classList.remove('animate-fade-out');
        }, 300);
    }

    // Add drag and drop functionality
    const dropZone = document.querySelector('.border-dashed');
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, unhighlight, false);
    });

    function highlight(e) {
        dropZone.classList.add('border-indigo-500', 'bg-indigo-50');
    }

    function unhighlight(e) {
        dropZone.classList.remove('border-indigo-500', 'bg-indigo-50');
    }

    dropZone.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        const input = document.getElementById('meal_image');
        input.files = files;
        previewImage(input);
    }
    </script>
</body>
</html> 
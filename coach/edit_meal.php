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

    // Validate file
    $allowed_types = ['jpg', 'jpeg', 'png'];
    if (!in_array($file_extension, $allowed_types)) {
        throw new Exception("Only JPG, JPEG & PNG files are allowed.");
    }

    // Check if image file is a actual image
    if (!getimagesize($file["tmp_name"])) {
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

// Check if meal_id is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: diet_plans.php");
    exit();
}

$meal_id = (int)$_GET['id'];

// Get meal details and verify it belongs to this coach's diet plan
$stmt = $conn->prepare("
    SELECT m.*, p.coach_id 
    FROM diet_plan_meals m 
    JOIN diet_plans p ON m.diet_plan_id = p.id 
    WHERE m.id = ? AND p.coach_id = ?
");
$stmt->bind_param("ii", $meal_id, $coach_id);
$stmt->execute();
$result = $stmt->get_result();
if (!$meal = $result->fetch_assoc()) {
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
    $image_path = $meal['image_path']; // Keep existing image by default
    if (isset($_FILES['meal_image']) && $_FILES['meal_image']['error'] === UPLOAD_ERR_OK) {
        try {
            // Delete old image if exists
            if ($meal['image_path']) {
                $old_image_path = "../uploads/" . $meal['image_path'];
                if (file_exists($old_image_path)) {
                    unlink($old_image_path);
                }
            }
            $image_path = handleImageUpload($_FILES['meal_image'], 'meal');
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
    }
    
    // If no errors, update the meal
    if (empty($errors)) {
        try {
            $stmt = $conn->prepare("UPDATE diet_plan_meals SET meal_type = ?, meal_name = ?, description = ?, calories = ?, protein = ?, carbs = ?, fats = ?, meal_order = ?, image_path = ? WHERE id = ?");
            $stmt->bind_param("sssiiiiisi", $meal_type, $meal_name, $description, $calories, $protein, $carbs, $fats, $meal_order, $image_path, $meal_id);
            
            if ($stmt->execute()) {
                $alert = "Meal updated successfully!";
                $alert_type = "success";
                
                // Update the meal data
                $meal['meal_type'] = $meal_type;
                $meal['meal_name'] = $meal_name;
                $meal['description'] = $description;
                $meal['calories'] = $calories;
                $meal['protein'] = $protein;
                $meal['carbs'] = $carbs;
                $meal['fats'] = $fats;
                $meal['meal_order'] = $meal_order;
                $meal['image_path'] = $image_path;
            } else {
                $alert = "Error updating meal: " . $stmt->error;
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Meal - <?php echo htmlspecialchars($meal['meal_name']); ?> - FitClub Coach</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Edit Meal</h1>
            <div class="flex space-x-4">
                <a href="add_meals.php?plan_id=<?php echo $meal['diet_plan_id']; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Meals
                </a>
            </div>
        </div>
        
        <?php if (!empty($alert)): ?>
            <div class="mb-4 p-4 rounded <?php echo $alert_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                <?php echo $alert; ?>
            </div>
        <?php endif; ?>
        
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <form method="POST" action="" class="p-6" enctype="multipart/form-data">
                <div class="space-y-4">
                    <!-- Meal Image -->
                    <div>
                        <label for="meal_image" class="block text-sm font-medium text-gray-700 mb-1">Meal Image</label>
                        <?php if ($meal['image_path']): ?>
                            <div class="mb-4 relative inline-block group">
                                <img src="../uploads/<?php echo htmlspecialchars($meal['image_path']); ?>" 
                                     alt="Current meal image" 
                                     class="h-48 w-48 object-cover rounded-lg shadow-md">
                                <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-30 transition-all duration-200 rounded-lg flex items-center justify-center opacity-0 group-hover:opacity-100">
                                    <span class="text-white text-sm font-medium">Current Image</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-indigo-500 transition-colors duration-200 group">
                            <div class="space-y-1 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="mx-auto h-12 w-12 text-gray-400 group-hover:text-indigo-500 transition-colors duration-200" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <div class="flex text-sm text-gray-600 mt-2">
                                        <label for="meal_image" class="relative cursor-pointer bg-white rounded-md font-medium text-indigo-600 hover:text-indigo-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-indigo-500">
                                            <span class="px-4 py-2 bg-indigo-50 rounded-md hover:bg-indigo-100 transition-colors duration-200">Upload a new image</span>
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
                                <button type="button" onclick="removeImage()" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 transition-colors duration-200 shadow-lg">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Meal Type -->
                    <div>
                        <label for="meal_type" class="block text-sm font-medium text-gray-700 mb-1">Meal Type</label>
                        <select id="meal_type" name="meal_type" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="">Select Meal Type</option>
                            <option value="breakfast" <?php echo $meal['meal_type'] === 'breakfast' ? 'selected' : ''; ?>>Breakfast</option>
                            <option value="lunch" <?php echo $meal['meal_type'] === 'lunch' ? 'selected' : ''; ?>>Lunch</option>
                            <option value="dinner" <?php echo $meal['meal_type'] === 'dinner' ? 'selected' : ''; ?>>Dinner</option>
                            <option value="snack" <?php echo $meal['meal_type'] === 'snack' ? 'selected' : ''; ?>>Snack</option>
                        </select>
                    </div>
                    
                    <!-- Meal Name -->
                    <div>
                        <label for="meal_name" class="block text-sm font-medium text-gray-700 mb-1">Meal Name</label>
                        <input type="text" id="meal_name" name="meal_name" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo htmlspecialchars($meal['meal_name']); ?>">
                    </div>
                    
                    <!-- Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <textarea id="description" name="description" rows="3"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"><?php echo htmlspecialchars($meal['description']); ?></textarea>
                    </div>
                    
                    <!-- Calories -->
                    <div>
                        <label for="calories" class="block text-sm font-medium text-gray-700 mb-1">Calories</label>
                        <input type="number" id="calories" name="calories" min="1" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo $meal['calories']; ?>">
                    </div>
                    
                    <!-- Nutrients -->
                    <div class="grid grid-cols-3 gap-4">
                        <div>
                            <label for="protein" class="block text-sm font-medium text-gray-700 mb-1">Protein (g)</label>
                            <input type="number" id="protein" name="protein" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $meal['protein']; ?>">
                        </div>
                        <div>
                            <label for="carbs" class="block text-sm font-medium text-gray-700 mb-1">Carbs (g)</label>
                            <input type="number" id="carbs" name="carbs" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $meal['carbs']; ?>">
                        </div>
                        <div>
                            <label for="fats" class="block text-sm font-medium text-gray-700 mb-1">Fats (g)</label>
                            <input type="number" id="fats" name="fats" min="0" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                                   value="<?php echo $meal['fats']; ?>">
                        </div>
                    </div>
                    
                    <!-- Meal Order -->
                    <div>
                        <label for="meal_order" class="block text-sm font-medium text-gray-700 mb-1">Meal Order</label>
                        <input type="number" id="meal_order" name="meal_order" min="0" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500"
                               value="<?php echo $meal['meal_order']; ?>">
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end">
                    <a href="add_meals.php?plan_id=<?php echo $meal['diet_plan_id']; ?>" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 mr-3">
                        Cancel
                    </a>
                    <button type="submit" class="bg-indigo-600 py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Update Meal
                    </button>
                </div>
            </form>
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
                // Add animation
                previewDiv.classList.add('animate-fade-in');
            }
            
            reader.readAsDataURL(input.files[0]);
        }
    }

    function removeImage() {
        const input = document.getElementById('meal_image');
        const preview = document.getElementById('preview');
        const previewDiv = document.getElementById('imagePreview');
        
        // Add fade out animation
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

    <style>
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
</body>
</html> 
<?php
session_start();
require_once '../includes/config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: ../index.php");
    exit();
}

$coach_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get coach information
$stmt = $conn->prepare("
    SELECT c.*, u.fullname, u.email, u.phone 
    FROM coaches c 
    JOIN users u ON c.user_id = u.id 
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $coach_id);
$stmt->execute();
$coach = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $specialization = $_POST['specialization'];
    $experience_years = (int)$_POST['experience_years'];
    
    // Handle password change if provided
    $password_changed = false;
    if (!empty($_POST['current_password']) && !empty($_POST['new_password']) && !empty($_POST['confirm_password'])) {
        // Verify current password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $coach_id);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if (password_verify($_POST['current_password'], $result['password'])) {
            if ($_POST['new_password'] === $_POST['confirm_password']) {
                $new_password_hash = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
                $password_changed = true;
            } else {
                $error_message = "New passwords do not match!";
            }
        } else {
            $error_message = "Current password is incorrect!";
        }
    }
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Update users table
        if ($password_changed) {
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $fullname, $email, $phone, $new_password_hash, $coach_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $fullname, $email, $phone, $coach_id);
        }
        $stmt->execute();
        
        // Update coaches table
        $stmt = $conn->prepare("UPDATE coaches SET specialization = ?, experience_years = ? WHERE user_id = ?");
        $stmt->bind_param("sii", $specialization, $experience_years, $coach_id);
        $stmt->execute();
        
        $conn->commit();
        $success_message = "Profile updated successfully!";
        
        // Refresh coach data
        $stmt = $conn->prepare("
            SELECT c.*, u.fullname, u.email, u.phone 
            FROM coaches c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.user_id = ?
        ");
        $stmt->bind_param("i", $coach_id);
        $stmt->execute();
        $coach = $stmt->get_result()->fetch_assoc();
        
    } catch (Exception $e) {
        $conn->rollback();
        $error_message = "Error updating profile. Please try again.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Coach Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .profile-header {
            background: linear-gradient(135deg, rgb(17, 24, 39) 0%, rgb(31, 41, 55) 100%);
        }
        .input-focus-ring {
            transition: all 0.3s ease;
        }
        .input-focus-ring:focus {
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2);
        }
        .profile-card {
            transition: transform 0.3s ease;
        }
        .profile-card:hover {
            transform: translateY(-2px);
        }
        .specialization-badge {
            transition: all 0.3s ease;
        }
        .specialization-badge:hover {
            transform: scale(1.05);
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'components/navigation.php'; ?>

    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Profile Header -->
            <div class="profile-header rounded-2xl p-8 mb-8 text-white">
                <div class="flex items-center space-x-6">
                    <div class="w-24 h-24 bg-white rounded-full flex items-center justify-center">
                        <i class="fas fa-user-tie text-4xl text-indigo-600"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($coach['fullname']); ?></h1>
                        <p class="text-indigo-100">Professional Coach</p>
                        <div class="mt-2 flex items-center space-x-2">
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-dumbbell mr-1"></i>
                                <?php echo ucfirst($coach['specialization']); ?>
                            </span>
                            <span class="px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-clock mr-1"></i>
                                <?php echo $coach['experience_years']; ?> Years Experience
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Quick Stats -->
                <div class="md:col-span-1 space-y-4">
                    <div class="profile-card bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Account Info</h3>
                        <div class="space-y-3">
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-user-circle w-6"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($coach['fullname']); ?></span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-envelope w-6"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($coach['email']); ?></span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-phone w-6"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($coach['phone'] ?? 'Not set'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-card bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Links</h3>
                        <div class="space-y-3">
                            <a href="members.php" class="flex items-center text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-users w-6"></i>
                                <span class="ml-2">My Members</span>
                            </a>
                            <a href="diet_plans.php" class="flex items-center text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-calendar-alt w-6"></i>
                                <span class="ml-2">Diet Plans</span>
                            </a>
                            <a href="workout_plans.php" class="flex items-center text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-dumbbell w-6"></i>
                                <span class="ml-2">Workout Plans</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <div class="md:col-span-2">
                    <div class="profile-card bg-white rounded-xl shadow-sm p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-6">Edit Profile</h2>
                        <form method="POST" class="space-y-6">
                            <!-- Personal Information -->
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-user text-gray-400"></i>
                                            </div>
                                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($coach['fullname']); ?>" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-envelope text-gray-400"></i>
                                            </div>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($coach['email']); ?>" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-phone text-gray-400"></i>
                                            </div>
                                            <input type="tel"  maxlength="11" name="phone" value="<?php echo htmlspecialchars($coach['phone'] ?? ''); ?>" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Professional Information -->
                            <div class="border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Professional Information</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Specialization</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-dumbbell text-gray-400"></i>
                                            </div>
                                            <select name="specialization" 
                                                    class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                                <option value="bulk" <?php echo $coach['specialization'] === 'bulk' ? 'selected' : ''; ?>>Bulk</option>
                                                <option value="weight_loss" <?php echo $coach['specialization'] === 'weight_loss' ? 'selected' : ''; ?>>Weight Loss</option>
                                                <option value="strength" <?php echo $coach['specialization'] === 'strength' ? 'selected' : ''; ?>>Strength</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Years of Experience</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-clock text-gray-400"></i>
                                            </div>
                                            <input type="number" name="experience_years" value="<?php echo htmlspecialchars($coach['experience_years']); ?>" 
                                                   min="0" max="50"
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Password Change -->
                            <div class="border-t pt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Change Password</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Current Password</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-lock text-gray-400"></i>
                                            </div>
                                            <input type="password" name="current_password" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">New Password</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-key text-gray-400"></i>
                                            </div>
                                            <input type="password" name="new_password" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Confirm New Password</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-key text-gray-400"></i>
                                            </div>
                                            <input type="password" name="confirm_password" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>
                                </div>
                                <p class="mt-2 text-sm text-gray-500">Leave password fields empty if you don't want to change it</p>
                            </div>

                            <div class="flex justify-end">
                                <button type="submit" 
                                        class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors flex items-center">
                                    <i class="fas fa-save mr-2"></i>
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <footer class="bg-white mt-12 py-6 border-t">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <p class="text-center text-gray-500 text-sm">
                &copy; <?php echo date('Y'); ?> FitClub. All rights reserved.
            </p>
        </div>
    </footer>
</body>
</html> 
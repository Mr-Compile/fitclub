<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Initialize database connection
$database = new Database();
$db = $database->getConnection();

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    try {
        // Start transaction
        $db->beginTransaction();

        // Get current user data
        $query = "SELECT * FROM users WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $_SESSION['user_id']);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify current password if changing password
        if (!empty($current_password)) {
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception("Current password is incorrect");
            }

            if (empty($new_password) || empty($confirm_password)) {
                throw new Exception("New password and confirmation are required");
            }

            if ($new_password !== $confirm_password) {
                throw new Exception("New passwords do not match");
            }

            if (strlen($new_password) < 8) {
                throw new Exception("Password must be at least 8 characters long");
            }
        }

        // Update user information
        $query = "UPDATE users SET 
                  username = :username,
                  email = :email,
                  fullname = :fullname,
                  phone = :phone";

        // Add password update if changing password
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $query .= ", password = :password";
        }

        $query .= " WHERE id = :id";

        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':fullname', $fullname);
        $stmt->bindParam(':phone', $phone);
        $stmt->bindParam(':id', $_SESSION['user_id']);

        if (!empty($new_password)) {
            $stmt->bindParam(':password', $hashed_password);
        }

        $stmt->execute();

        // Update session variables
        $_SESSION['username'] = $username;

        $db->commit();
        $success_message = "Profile updated successfully!";
    } catch (Exception $e) {
        $db->rollBack();
        $error_message = $e->getMessage();
    }
}

// Get current user data
$query = "SELECT * FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - FitClub</title>
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
        .admin-badge {
            transition: all 0.3s ease;
        }
        .admin-badge:hover {
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
                        <i class="fas fa-user-shield text-4xl text-indigo-600"></i>
                    </div>
                    <div>
                        <h1 class="text-3xl font-bold mb-2"><?php echo htmlspecialchars($user['fullname']); ?></h1>
                        <p class="text-indigo-100">System Administrator</p>
                        <div class="mt-2 flex items-center space-x-2">
                            <span class="admin-badge px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-shield-alt mr-1"></i>
                                Admin Access
                            </span>
                            <span class="admin-badge px-3 py-1 bg-white/20 rounded-full text-sm">
                                <i class="fas fa-user-cog mr-1"></i>
                                Full Control
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
                                <span class="ml-2"><?php echo htmlspecialchars($user['username']); ?></span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-envelope w-6"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($user['email']); ?></span>
                            </div>
                            <div class="flex items-center text-gray-600">
                                <i class="fas fa-phone w-6"></i>
                                <span class="ml-2"><?php echo htmlspecialchars($user['phone'] ?? 'Not set'); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="profile-card bg-white rounded-xl shadow-sm p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Quick Links</h3>
                        <div class="space-y-3">
                            <a href="dashboard.php" class="flex items-center text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-chart-line w-6"></i>
                                <span class="ml-2">Dashboard</span>
                            </a>
                            <a href="members.php" class="flex items-center text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-users w-6"></i>
                                <span class="ml-2">Manage Users</span>
                            </a>
                            <a href="settings.php" class="flex items-center text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-cog w-6"></i>
                                <span class="ml-2">System Settings</span>
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
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Username</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-user text-gray-400"></i>
                                            </div>
                                            <input type="text" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-envelope text-gray-400"></i>
                                            </div>
                                            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-user-tag text-gray-400"></i>
                                            </div>
                                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user['fullname'] ?? ''); ?>" 
                                                   class="input-focus-ring pl-10 w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-phone text-gray-400"></i>
                                            </div>
                                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
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
</html> 